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

use Psr\Log\LoggerAwareTrait;

/**
 * Model for users read from LDAP server.
 */
class User extends \NormanSeibert\Ldap\Domain\Model\LdapUser\LdapEntity implements \Psr\Log\LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var \NormanSeibert\Ldap\Domain\Repository\Typo3User\UserRepositoryInterface
     */
    protected $userRepository;

    /**
     * @var \NormanSeibert\Ldap\Domain\Repository\Typo3User\UserGroupRepositoryInterface
     */
    protected $usergroupRepository;

    /**
     * @var \NormanSeibert\Ldap\Domain\Model\LdapUser\Group
     */
    protected $groupObject;

    /**
     * @var \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser|\NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser
     */
    protected $user;

    /**
     * @var \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationUsers
     */
    protected $userRules;

    /**
     * @var bool
     */
    protected $importGroups;

    /**
     * @var string
     */
    protected $userObject;

    /**
     * @var int
     */
    protected $pid;

    public function __construct()
    {
        parent::__construct();
        $this->importGroups = 1;
    }

    public function setUser(\NormanSeibert\Ldap\Domain\Model\Typo3User\UserInterface $user)
    {
        $this->user = $user;
    }

    /**
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\UserInterface
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Tries to load the TYPO3 user based on DN or username.
     */
    public function loadUser()
    {
        $pid = $this->userRules->getPid();
        $msg = 'Search for user record with DN = '.$this->dn.' in page '.$pid;
        if ($this->ldapConfig->logLevel >= 2) {
            $this->logger->debug($msg);
        }
        // search for DN
        $user = $this->userRepository->findByDn($this->dn, $pid);
        // search for Username if no record with DN found
        if (is_object($user)) {
            $msg = 'User record already existing: '.$user->getUid();
            if (3 == $this->ldapConfig->logLevel) {
                $this->logger->debug($msg);
            }
        } else {
            $mapping = $this->userRules->getMapping();
            $rawLdapUsername = $mapping['username.']['data'];
            $ldapUsername = str_replace('field:', '', $rawLdapUsername);
            $username = $this->getAttribute($ldapUsername);
            $user = $this->userRepository->findByUsername($username, $pid);
            if (is_object($user)) {
                $msg = 'User record already existing: '.$user->getUid();
                if (3 == $this->ldapConfig->logLevel) {
                    $this->logger->debug($msg);
                }
            }
        }
        $this->user = $user;
    }

    /**
     * adds a new TYPO3 user.
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
            $this->user = $this->objectManager->get($this->userObject);
            $this->user->setServerUid($this->ldapServer->getConfiguration()->getUid());
            $this->user->setUsername($username);
            $this->user->setDN($this->dn);
            $this->user->generatePassword();

            $pid = $this->userRules->getPid();
            if (empty($pid)) {
                $pid = 0;
            }
            $this->user->setPid($pid);

            // LDAP attributes from mapping
            $insertArray = $this->mapAttributes();
            foreach ($insertArray as $field => $value) {
                $ret = $this->user->_setProperty($field, $value);
                if (!$ret) {
                    $msg = 'Property "'.$field.'" is unknown to Extbase.';
                    if ($this->ldapConfig->logLevel >= 1) {
                        $this->logger->warning($msg);
                    }
                }
            }

            if ($lastRun) {
                $this->user->setLastRun($lastRun);
            }

            $usergroups = $this->addUsergroupsToUserRecord($lastRun);
            $numberOfGroups = count($usergroups);

            if ((0 == $numberOfGroups) && ($this->userRules->getOnlyUsersWithGroup())) {
                $msg = 'User "'.$username.'" (DN: '.$this->dn.') not imported due to missing usergroup';
                if ($this->ldapConfig->logLevel >= 1) {
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
                    if ($this->ldapConfig->logLevel >= 1) {
                        $this->logger->notice($msg);
                    }
                }
            } else {
                $createUser = true;
            }
        } else {
            // error condition. There should always be a username
            $msg = 'No username (Server: '.$this->ldapServer->getConfiguration()->getUid().', DN: '.$this->dn.')';
            if ($this->ldapConfig->logLevel >= 1) {
                $this->logger->notice($msg);
            }
            \NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING, $msg, $this->ldapServer->getConfiguration()->getUid());
        }

        if ($createUser) {
            $this->userRepository->add($this->user);
            $msg = 'Create user record "'.$username.'" (DN: '.$this->dn.')';
            if ($this->ldapConfig->logLevel >= 3) {
                $debugData = (array) $this->user;
                $this->logger->debug($msg, $debugData);
            } elseif (2 == $this->ldapConfig->logLevel) {
                $this->logger->debug($msg);
            }
        }
    }

    /**
     * updates a TYPO3 user.
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
            $this->user->setUsername($username);
            $this->user->setServerUid($this->ldapServer->getConfiguration()->getUid());
            $this->user->setDN($this->dn);

            $pid = $this->userRules->getPid();
            if (!empty($pid)) {
                $this->user->setPid($pid);
            }

            // LDAP attributes from mapping
            $insertArray = $this->mapAttributes();
            foreach ($insertArray as $field => $value) {
                $ret = $this->user->_setProperty($field, $value);
                if (!$ret) {
                    $msg = 'Property "'.$field.'" is unknown to Extbase.';
                    if ($this->ldapConfig->logLevel >= 1) {
                        $this->logger->warning($msg);
                    }
                }
            }

            if ($lastRun) {
                $this->user->setLastRun($lastRun);
            }

            $this->removeUsergroupsFromUserRecord();
            $usergroups = $this->addUsergroupsToUserRecord($lastRun);
            $numberOfGroups = count($usergroups);

            if ((0 == $numberOfGroups) && ($this->userRules->getOnlyUsersWithGroup())) {
                $msg = 'User "'.$username.'" (DN: '.$this->dn.') not updated due to missing usergroup';
                if ($this->ldapConfig->logLevel >= 1) {
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
                    if ($this->ldapConfig->logLevel >= 1) {
                        $this->logger->notice($msg);
                    }
                }
            } else {
                $updateUser = true;
            }
        } else {
            // error condition. There should always be a username
            $msg = 'No username (Server: '.$this->ldapServer->getConfiguration()->getUid().', DN: '.$this->dn.')';
            if ($this->ldapConfig->logLevel >= 1) {
                $this->logger->warning($msg);
            }
            \NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING, $msg, $this->ldapServer->getConfiguration()->getUid());
        }

        if ($updateUser) {
            $this->userRepository->update($this->user);
            $msg = 'Update user record "'.$username.'" (DN: '.$this->dn.')';
            if ($this->ldapConfig->logLevel >= 3) {
                $debugData = (array) $this->user;
                $this->logger->debug($msg, $debugData);
            } elseif (2 == $this->ldapConfig->logLevel) {
                $this->logger->debug($msg);
            }
        }
    }

    /**
     * enables a TYPO3 user.
     */
    public function enableUser()
    {
        $this->user->setIsDisabled(false);
        $this->userRepository->update($this->user);
    }

    /**
     * adds TYPO3 usergroups to the user record.
     *
     * @param string $lastRun
     *
     * @return array
     */
    protected function addUsergroupsToUserRecord($lastRun = null)
    {
        if (is_object($this->userRules->getGroupRules())) {
            $usergroups = $this->groupObject->assignGroups($lastRun, $this->dn, $this->attributes);

            if (count($usergroups) > 0) {
                foreach ($usergroups as $group) {
                    $this->user->addUsergroup($group);
                    if (3 == $this->ldapConfig->logLevel) {
                        $msg = 'Add usergroup to user record "'.$this->user->getUsername().'": '.$group->getTitle();
                        $this->logger->debug($msg);
                    }
                }
            } else {
                if (3 == $this->ldapConfig->logLevel) {
                    $msg = 'User has no LDAP usergroups: '.$this->user->getUsername();
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
     * removes usergroups from the user record.
     */
    protected function removeUsergroupsFromUserRecord()
    {
        $preserveNonLdapGroups = $this->userRules->getGroupRules()->getPreserveNonLdapGroups();
        if ($preserveNonLdapGroups) {
            $usergroup = $this->user->getUsergroup();
            if (is_object($usergroup)) {
                $usergroups = $usergroup->toArray();
                if (is_array($usergroups)) {
                    foreach ($usergroups as $group) {
                        $extendedGroup = $this->usergroupRepository->findByUid($group->getUid());
                        if ($extendedGroup->getServerUid()) {
                            $this->user->removeUsergroup($group);
                        }
                    }
                } else {
                    // @var $usergroup \TYPO3\CMS\Extbase\Persistence\ObjectStorage
                    $usergroup = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\ObjectStorage');
                    $this->user->setUsergroup($usergroup);
                }
            } else {
                // @var $usergroup \TYPO3\CMS\Extbase\Persistence\ObjectStorage
                $usergroup = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\ObjectStorage');
                $this->user->setUsergroup($usergroup);
            }
        } else {
            // @var $usergroup \TYPO3\CMS\Extbase\Persistence\ObjectStorage
            $usergroup = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\ObjectStorage');
            $this->user->setUsergroup($usergroup);
        }
    }
}
