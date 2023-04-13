<?php

namespace NormanSeibert\Ldap\Domain\Model\LdapUser;

/*
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the textfile GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 *
 * @package   ldap
 * @author	  Norman Seibert <seibert@entios.de>
 * @copyright 2020 Norman Seibert
 */

use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\FrontendUserRepository;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\FrontendUserGroupRepository;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserRepository;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserGroupRepository;
use NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser;
use NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser;
use NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationUsers;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Log\LoggerInterface;
use NormanSeibert\Ldap\Utility\Helpers;

/**
 * Model for users read from LDAP server.
 */
abstract class LdapUser extends LdapEntity
{
    private LoggerInterface $logger;

    const ERROR = 2;
    const WARNING = 1;
    const OK = 0;
    const INFO = -1;
    const NOTICE = -2;

    /**
     * @var FrontendUserRepository | BackendUserRepository
     */
    protected $userRepository;

    /**
     * @var FrontendUserGroupRepository | BackendUserGroupRepository
     */
    protected $usergroupRepository;

    /**
     * @var BeGroup | FeGroup
     */
    protected $groupObject;

    /**
     * @var BackendUser | FrontendUser
     */
    protected $typo3User;

    /**
     * @var ServerConfigurationUsers
     */
    protected $userRules;

    /**
     * @var bool
     */
    protected $importGroups;

    /**
     * @var int
     */
    protected $pid;

    /**
     * @var ObjectStorage
     */
    protected $ObjectStorage;

    /**
     * @var int
     */
    protected $logLevel;

    public function setLoglevel(int $logLevel)
    {
        $this->logLevel = $logLevel;
    }

    public function setUser(BackendUser | FrontendUser $typo3User)
    {
        $this->typo3User = $typo3User;
    }

    /**
     * @return FrontendUser | BackendUser
     */
    public function getUser()
    {
        return $this->typo3User;
    }

    /**
     * Tries to load the TYPO3 typo3User based on DN or username.
     */
    public function loadUser()
    {
        $pid = $this->userRules->getPid();
        $msg = 'Search for typo3User record with DN = '.$this->dn.' in page '.$pid;
        if ($this->logLevel >= 2) {
            $this->logger->debug($msg);
        }
        // search for DN
        $typo3User = $this->userRepository->findByDn($this->dn, $pid);
        // search for Username if no record with DN found
        if (is_object($typo3User)) {
            $msg = 'User record already existing: '.$typo3User->getUid();
            if (3 == $this->logLevel) {
                $this->logger->debug($msg);
            }
        } else {
            $mapping = $this->userRules->getMapping();
            $rawLdapUsername = $mapping['username.']['data'];
            $ldapUsername = str_replace('field:', '', $rawLdapUsername);
            $username = $this->getAttribute($ldapUsername);
            $typo3User = $this->userRepository->findByUsername($username, $pid);
            if (is_object($typo3User)) {
                $msg = 'User record already existing: '.$typo3User->getUid();
                if (3 == $this->logLevel) {
                    $this->logger->debug($msg);
                }
            }
        }
        $this->typo3User = $typo3User;
    }

    /**
     * adds a new TYPO3 typo3User.
     *
     * @param string $lastRun
     */
    public function addUser($lastRun = null)
    {
        $mapping = $this->userRules->getMapping();
        $rawLdapUsername = $mapping['username.']['data'];
        $ldapUsername = str_replace('field:', '', $rawLdapUsername);

        $username = $this->getAttribute($ldapUsername);

        $createUser = false;

        if ($username) {
            $this->typo3User->setServerUid($this->ldapServer->getConfiguration()->getUid());
            $this->typo3User->setUsername($username);
            $this->typo3User->setDN($this->dn);
            $this->typo3User->generatePassword();

            $pid = $this->userRules->getPid();
            if (empty($pid)) {
                $pid = 0;
            }
            $this->typo3User->setPid($pid);

            // LDAP attributes from mapping
            $insertArray = $this->mapAttributes();
            foreach ($insertArray as $field => $value) {
                $ret = $this->typo3User->_setProperty($field, $value);
                if (!$ret) {
                    $msg = 'Property "'.$field.'" is unknown to Extbase.';
                    if ($this->logLevel >= 1) {
                        $this->logger->warning($msg);
                    }
                }
            }

            if ($lastRun) {
                $this->typo3User->setLastRun($lastRun);
            }

            $usergroups = $this->addUsergroupsToUserRecord($lastRun);
            $numberOfGroups = count($usergroups);

            if ((0 == $numberOfGroups) && ($this->userRules->getOnlyUsersWithGroup())) {
                $msg = 'User "'.$username.'" (DN: '.$this->dn.') not imported due to missing usergroup';
                if ($this->logLevel >= 1) {
                    $this->logger->notice($msg);
                }
            } elseif ($this->userRules->getGroupRules()->getRestrictToGroups()) {
                $groupFound = false;
                reset($usergroups);
                foreach ($usergroups as $group) {
                    $groupFound = $this->checkGroupMembership($group->getTitle());
                    if ($groupFound) {
                        break;
                    }
                }
                if ($groupFound) {
                    $createUser = true;
                } else {
                    $msg = 'User "'.$username.'" (DN: '.$this->dn.') because no usergroup matches "'.$this->userRules->getGroupRules()->getRestrictToGroups().'"';
                    if ($this->logLevel >= 1) {
                        $this->logger->notice($msg);
                    }
                }
            } else {
                $createUser = true;
            }
        } else {
            // error condition. There should always be a username
            $msg = 'No username (Server: '.$this->ldapServer->getConfiguration()->getUid().', DN: '.$this->dn.')';
            if ($this->logLevel >= 1) {
                $this->logger->notice($msg);
            }
            Helpers::addError(self::WARNING, $msg, $this->ldapServer->getConfiguration()->getUid());
        }

        if ($createUser) {
            $this->userRepository->add($this->typo3User);
            $msg = 'Create typo3User record "'.$username.'" (DN: '.$this->dn.')';
            if ($this->logLevel >= 3) {
                $debugData = (array) $this->typo3User;
                $this->logger->debug($msg, $debugData);
            } elseif (2 == $this->logLevel) {
                $this->logger->debug($msg);
            }
        }
    }

    /**
     * updates a TYPO3 typo3User.
     *
     * @param string $lastRun
     */
    public function updateUser($lastRun = null)
    {
        $mapping = $this->userRules->getMapping();
        $rawLdapUsername = $mapping['username.']['data'];
        $ldapUsername = str_replace('field:', '', $rawLdapUsername);

        $username = (string) $this->getAttribute($ldapUsername);

        $updateUser = false;

        if ($username) {
            $this->typo3User->setUsername($username);
            $this->typo3User->setServerUid($this->ldapServer->getConfiguration()->getUid());
            $this->typo3User->setDN($this->dn);

            $pid = $this->userRules->getPid();
            if (!empty($pid)) {
                $this->typo3User->setPid($pid);
            }

            // LDAP attributes from mapping
            $insertArray = $this->mapAttributes();
            foreach ($insertArray as $field => $value) {
                $ret = $this->typo3User->_setProperty($field, $value);
                if (!$ret) {
                    $msg = 'Property "'.$field.'" is unknown to Extbase.';
                    if ($this->logLevel >= 1) {
                        $this->logger->warning($msg);
                    }
                }
            }

            if ($lastRun) {
                $this->typo3User->setLastRun($lastRun);
            }

            $this->removeUsergroupsFromUserRecord();
            $usergroups = $this->addUsergroupsToUserRecord($lastRun);
            $numberOfGroups = count($usergroups);

            if ((0 == $numberOfGroups) && ($this->userRules->getOnlyUsersWithGroup())) {
                $msg = 'User "'.$username.'" (DN: '.$this->dn.') not updated due to missing usergroup';
                if ($this->logLevel >= 1) {
                    $this->logger->notice($msg);
                }
            } elseif ($this->userRules->getGroupRules()->getRestrictToGroups()) {
                $groupFound = false;
                reset($usergroups);
                foreach ($usergroups as $group) {
                    $groupFound = $this->checkGroupMembership($group->getTitle());
                    if ($groupFound) {
                        break;
                    }
                }
                if ($groupFound) {
                    $updateUser = true;
                } else {
                    $msg = 'User "'.$username.'" (DN: '.$this->dn.') because no usergroup matches "'.$this->userRules->getGroupRules()->getRestrictToGroups().'"';
                    if ($this->logLevel >= 1) {
                        $this->logger->notice($msg);
                    }
                }
            } else {
                $updateUser = true;
            }
        } else {
            // error condition. There should always be a username
            $msg = 'No username (Server: '.$this->ldapServer->getConfiguration()->getUid().', DN: '.$this->dn.')';
            if ($this->logLevel >= 1) {
                $this->logger->warning($msg);
            }
            Helpers::addError(self::WARNING, $msg, $this->ldapServer->getConfiguration()->getUid());
        }

        if ($updateUser) {
            $this->userRepository->update($this->typo3User);
            $msg = 'Update typo3User record "'.$username.'" (DN: '.$this->dn.')';
            if ($this->logLevel >= 3) {
                $debugData = (array) $this->typo3User;
                $this->logger->debug($msg, $debugData);
            } elseif (2 == $this->logLevel) {
                $this->logger->debug($msg);
            }
        }
    }

    /**
     * enables a TYPO3 typo3User.
     */
    public function enableUser()
    {
        $this->typo3User->setIsDisabled(false);
        $this->userRepository->update($this->typo3User);
    }

    /**
     * adds TYPO3 usergroups to the typo3User record.
     *
     * @param string $lastRun
     *
     * @return array
     */
    protected function addUsergroupsToUserRecord($lastRun = null)
    {
        if (is_object($this->userRules->getGroupRules())) {
            $usergroups = $this->groupObject->assignGroups($this->dn, $this->attributes, $lastRun);

            if (count($usergroups) > 0) {
                foreach ($usergroups as $group) {
                    $this->typo3User->addUsergroup($group);
                    if (3 == $this->logLevel) {
                        $msg = 'Add usergroup to typo3User record "'.$this->typo3User->getUsername().'": '.$group->getTitle();
                        $this->logger->debug($msg);
                    }
                }
            } else {
                if (3 == $this->logLevel) {
                    $msg = 'User has no LDAP usergroups: '.$this->typo3User->getUsername();
                    $this->logger->notice($msg);
                }
            }
        }

        return $usergroups;
    }

    /** Checks whether a usergroup is in the list of allowed groups.
     *
     * @param string $groupname
     *
     * @return bool
     */
    protected function checkGroupMembership($groupname)
    {
        return $this->ldapGroupObject->checkGroupName($groupname);
    }

    /**
     * removes usergroups from the typo3User record.
     */
    protected function removeUsergroupsFromUserRecord()
    {
        $preserveNonLdapGroups = $this->userRules->getGroupRules()->getPreserveNonLdapGroups();
        if ($preserveNonLdapGroups) {
            $usergroup = $this->typo3User->getUsergroup();
            if (is_object($usergroup)) {
                $usergroups = $usergroup->toArray();
                if (is_array($usergroups)) {
                    foreach ($usergroups as $group) {
                        $extendedGroup = $this->usergroupRepository->findByUid($group->getUid());
                        if ($extendedGroup->getServerUid()) {
                            $this->typo3User->removeUsergroup($group);
                        }
                    }
                } else {
                    $usergroup = GeneralUtility::makeInstance(ObjectStorage::class);
                    $this->typo3User->setUsergroup($usergroup);
                }
            } else {
                $usergroup = GeneralUtility::makeInstance(ObjectStorage::class);
                $this->typo3User->setUsergroup($usergroup);
            }
        } else {
            $usergroup = GeneralUtility::makeInstance(ObjectStorage::class);
            $this->typo3User->setUsergroup($usergroup);
        }
    }
}
