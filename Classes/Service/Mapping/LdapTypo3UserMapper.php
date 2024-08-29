<?php

namespace NormanSeibert\Ldap\Service\Mapping
;

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
use NormanSeibert\Ldap\Domain\Model\LdapServer\LdapServer;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\FrontendUserRepository;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\FrontendUserGroupRepository;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserRepository;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserGroupRepository;
use NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser;
use NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser;
use NormanSeibert\Ldap\Domain\Model\LdapUser\LdapFeUser;
use NormanSeibert\Ldap\Domain\Model\LdapUser\LdapBeUser;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Log\LoggerInterface;
use NormanSeibert\Ldap\Utility\Helpers;
use NormanSeibert\Ldap\Service\Mapping\GenericMapper;
use NormanSeibert\Ldap\Service\Mapping\LdapTypo3GroupMapper;
use NormanSeibert\Ldap\Service\LdapHandler;

/**
 * Maps groups read from LDAP to TYPO3 groups
 */
class LdapTypo3UserMapper
{
    private LoggerInterface $logger;

    const ERROR = 2;
    const WARNING = 1;
    const OK = 0;
    const INFO = -1;
    const NOTICE = -2;

    protected GenericMapper $mapper;

    protected LdapTypo3GroupMapper $groupMapper;

    protected LdapHandler $ldapHandler;

    protected int $logLevel;

    public function __construct()
    {
        $conf = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ExtensionConfiguration')->get('ldap');
        $this->logLevel = $conf['logLevel'];

        $this->mapper = new GenericMapper();
        $this->groupMapper = new LdapTypo3GroupMapper();
        $this->ldapHandler = new LdapHandler();

        $this->logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
    }

    public function setLoglevel(int $logLevel)
    {
        $this->logLevel = $logLevel;
    }

    /**
     * Tries to load the TYPO3 typo3User based on DN or username.
     */
    public function loadUser(LdapFeUser | LdapBeUser $ldapUser): FrontendUser | BackendUser | bool
    {   
        if ($ldapUser->getUserType() == 'be') {
            $userRepository = GeneralUtility::makeInstance(BackendUserRepository::class);
            $userRules = $ldapUser->getLdapServer()->getConfiguration()->getBeUserRules();
        } else {
            $userRepository = GeneralUtility::makeInstance(FrontendUserRepository::class);
            $userRules = $ldapUser->getLdapServer()->getConfiguration()->getFeUserRules();
        }
        $pid = $userRules->getPid();

        $msg = 'Search for typo3User record with DN = ' . $ldapUser->getDN() . ' in page ' . $pid;
        if ($this->logLevel >= 2) {
            $this->logger->debug($msg);
        }
        // search for DN
        $typo3User = $userRepository->findByDn($ldapUser->getDN(), $pid);
        // search for Username if no record with DN found
        if (is_object($typo3User)) {
            $msg = 'User record already existing: ' . $typo3User->getUid();
            if (3 == $this->logLevel) {
                $this->logger->debug($msg);
            }
        } else {
            $mapping = $userRules->getMapping();
            $rawLdapUsername = $mapping['username.']['data'];
            $ldapUsername = str_replace('field:', '', $rawLdapUsername);
            
            $username = $ldapUser->getAttribute($ldapUsername);
            $typo3User = $userRepository->findByUsername($username, $pid);
            
            if (is_object($typo3User)) {
                $msg = 'User record already existing: ' . $typo3User->getUid();
                if (3 == $this->logLevel) {
                    $this->logger->debug($msg);
                }
            }
        }

        return $typo3User;
    }

    /**
     * adds a new TYPO3 typo3User.
     */
    public function addUser(LdapServer $ldapServer, LdapFeUser | LdapBeUser $ldapUser, string $lastRun = null)
    {
        $userType = $ldapUser->getUserType();
        if ($userType == 'be') {
            $userRepository = GeneralUtility::makeInstance(BackendUserRepository::class);
            $userRules = $ldapServer->getConfiguration()->getBeUserRules();
            $typo3User = GeneralUtility::makeInstance(BackendUser::class);
        } else {
            $userRepository = GeneralUtility::makeInstance(FrontendUserRepository::class);
            $userRules = $ldapServer->getConfiguration()->getFeUserRules();
            $typo3User = GeneralUtility::makeInstance(FrontendUser::class);
        }

        $mapping = $userRules->getMapping();
        $rawLdapUsername = $mapping['username.']['data'];
        $ldapUsername = str_replace('field:', '', $rawLdapUsername);

        $username = (string) $ldapUser->getAttribute($ldapUsername);

        $createUser = false;

        if ($username) {
            $typo3User->setUsername($username);
            $typo3User->setServerUid($ldapServer->getUid());
            $typo3User->setDN($ldapUser->getDN());
            $typo3User->generatePassword();

            if (!empty($pid)) {
                $typo3User->setPid($pid);
            }

            $pid = $userRules->getPid();
            if (empty($pid)) {
                $pid = 0;
            }
            $typo3User->setPid($pid);

            // LDAP attributes from mapping
            $attributes = $ldapUser->getAttributes();
            $insertArray = $this->mapper->mapAttributes($mapping, $attributes);
            foreach ($insertArray as $field => $value) {
                $ret = $typo3User->_setProperty($field, $value);
                if (!$ret) {
                    $msg = 'Property "' . $field . '" is unknown to Extbase.';
                    if ($this->logLevel >= 1) {
                        $this->logger->warning($msg);
                    }
                }
            }

            if ($lastRun) {
                $typo3User->setLastRun($lastRun);
            }

            $usergroups = $this->addUsergroupsToUserRecord($ldapUser, $typo3User, $lastRun);
            $numberOfGroups = count($usergroups);

            if ((0 == $numberOfGroups) && ($userRules->getOnlyUsersWithGroup())) {
                $msg = 'User "' . $username.'" (DN: ' . $ldapUser->getDN() . ') not imported due to missing usergroup';
                if ($this->logLevel >= 1) {
                    $this->logger->notice($msg);
                }
            } elseif ($userRules->getGroupRules()->getRestrictToGroups()) {
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
                    $msg = 'User "' . $username . '" (DN: ' . $ldapUser->getDN() . ') because no usergroup matches "' . $userRules->getGroupRules()->getRestrictToGroups() . '"';
                    if ($this->logLevel >= 1) {
                        $this->logger->notice($msg);
                    }
                }
            } else {
                $createUser = true;
            }
        } else {
            // error condition. There should always be a username
            $msg = 'No username (Server: ' . $ldapServer->getConfiguration()->getUid() . ', DN: ' . $ldapUser->getDN() . ')';
            if ($this->logLevel >= 1) {
                $this->logger->notice($msg);
            }
            Helpers::addError(self::WARNING, $msg, $ldapServer->getConfiguration()->getUid());
        }

        if ($createUser) {
            $userRepository->add($typo3User);
            $msg = 'Create typo3User record "' . $username . '" (DN: ' . $ldapUser->getDN() . ')';
            if ($this->logLevel >= 3) {
                $debugData = (array) $typo3User;
                $this->logger->debug($msg, $debugData);
            } elseif (2 == $this->logLevel) {
                $this->logger->debug($msg);
            }
        }
    }

    /**
     * updates a TYPO3 typo3User.
     */
    public function updateUser(LdapFeUser | LdapBeUser $ldapUser, FrontendUser | BackendUser $typo3User, $lastRun = null)
    {
        $ldapServer = $ldapUser->getLdapServer();
        $userType = $ldapUser->getUserType();
        if ($userType == 'be') {
            $userRepository = GeneralUtility::makeInstance(BackendUserRepository::class);
            $userRules = $ldapServer->getConfiguration()->getBeUserRules();
        } else {
            $userRepository = GeneralUtility::makeInstance(FrontendUserRepository::class);
            $userRules = $ldapServer->getConfiguration()->getFeUserRules();
        }
        $pid = $userRules->getPid();
        $mapping = $userRules->getMapping();
        $rawLdapUsername = $mapping['username.']['data'];
        $ldapUsername = str_replace('field:', '', $rawLdapUsername);

        $username = (string) $ldapUser->getAttribute($ldapUsername);

        $updateUser = false;

        if ($username) {
            $typo3User->setUsername($username);
            $typo3User->setServerUid($ldapServer->getUid());
            $typo3User->setDN($ldapUser->getDN());

            if (!empty($pid)) {
                $typo3User->setPid($pid);
            }

            // LDAP attributes from mapping
            $insertArray = [];
            // $mapping = $this->userRules->getGroupRules()->getMapping();
            $attributes = $ldapUser->getAttributes();
            $insertArray = $this->mapper->mapAttributes($mapping, $attributes);
                
            foreach ($insertArray as $field => $value) {
                $ret = $typo3User->_setProperty($field, $value);
                if (!$ret) {
                    $msg = 'Property "' . $field . '" is unknown to Extbase.';
                    if ($this->logLevel >= 1) {
                        $this->logger->warning($msg);
                    }
                }
            }

            if ($lastRun) {
                $typo3User->setLastRun($lastRun);
            }

            $this->removeUsergroupsFromUserRecord($ldapServer, $typo3User, $userType);
            $usergroups = $this->addUsergroupsToUserRecord($ldapUser, $typo3User, $lastRun);
            $numberOfGroups = count($usergroups);

            if ((0 == $numberOfGroups) && ($userRules->getOnlyUsersWithGroup())) {
                $msg = 'User "'.$username.'" (DN: ' . $ldapUser->getDN() . ') not updated due to missing usergroup';
                if ($this->logLevel >= 1) {
                    $this->logger->notice($msg);
                }
            } elseif ($userRules->getGroupRules()->getRestrictToGroups()) {
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
                    $msg = 'User "'.$username.'" (DN: ' . $ldapUser->getDN() . ') because no usergroup matches "' . $userRules->getGroupRules()->getRestrictToGroups() . '"';
                    if ($this->logLevel >= 1) {
                        $this->logger->notice($msg);
                    }
                }
            } else {
                $updateUser = true;
            }
        } else {
            // error condition. There should always be a username
            $msg = 'No username (Server: ' . $ldapServer->getConfiguration()->getUid() . ', DN: ' . $ldapUser->getDN() . ')';
            if ($this->logLevel >= 1) {
                $this->logger->warning($msg);
            }
            Helpers::addError(self::WARNING, $msg, $ldapServer->getUid());
        }

        if ($updateUser) {
            $userRepository->update($typo3User);
            $msg = 'Update typo3User record "' . $username . '" (DN: ' . $ldapUser->getDN() . ')';
            if ($this->logLevel >= 3) {
                $debugData = (array) $typo3User;
                $this->logger->debug($msg, $debugData);
            } elseif (2 == $this->logLevel) {
                $this->logger->debug($msg);
            }
        }
    }

    /**
     * enables a TYPO3 typo3User.
     */
    public function enableUser($typo3User, $userRepository)
    {
        $typo3User->setIsDisabled(false);
        $userRepository->update($typo3User);
    }

    /**
     * adds TYPO3 usergroups to the typo3User record.
     *
     * @param string $lastRun
     *
     * @return array
     */
    protected function addUsergroupsToUserRecord(LdapFeUser | LdapBeUser $ldapUser, FrontendUser | BackendUser $typo3User, $lastRun = null)
    {
        $ldapServer = $ldapUser->getLdapServer();
        $userType = $ldapUser->getUserType();
        if ($userType == 'be') {
            $userRules = $ldapServer->getConfiguration()->getBeUserRules();
        } else {
            $userRules = $ldapServer->getConfiguration()->getFeUserRules();
        }
        $groupRules = $userRules->getGroupRules();
        if (is_object($groupRules)) {
            $usergroups = $this->groupMapper->assignGroups($ldapServer, $userType, $ldapUser->getDN(), $ldapUser->getAttributes(), $lastRun);

            if (count($usergroups) > 0) {
                foreach ($usergroups as $group) {
                    $typo3User->addUsergroup($group);
                    if (3 == $this->logLevel) {
                        $msg = 'Add usergroup to typo3User record "' . $typo3User->getUsername() . '": ' . $group->getTitle();
                        $this->logger->debug($msg);
                    }
                }
            } else {
                if (3 == $this->logLevel) {
                    $msg = 'User has no LDAP usergroups: ' . $typo3User->getUsername();
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
        return $this->groupMapper->checkGroupName($groupname);
    }

    /**
     * removes usergroups from the typo3User record.
     */
    protected function removeUsergroupsFromUserRecord(LdapServer $ldapServer, FrontendUser | BackendUser $typo3User, $userType)
    {
        if ($userType == 'be') {
            $usergroupRepository = GeneralUtility::makeInstance(BackendUserGroupRepository::class);
            $userRules = $ldapServer->getConfiguration()->getFeUserRules();
        } else {
            $usergroupRepository = GeneralUtility::makeInstance(FrontendUserGroupRepository::class);
            $userRules = $ldapServer->getConfiguration()->getFeUserRules();
        }
        $groupRules = $userRules->getGroupRules();
        if (is_object($groupRules)) {
            $preserveNonLdapGroups = $groupRules->getPreserveNonLdapGroups();
            if ($preserveNonLdapGroups) {
                $usergroup = $typo3User->getUsergroup();
                if (is_object($usergroup)) {
                    $usergroups = $usergroup->toArray();
                    if (is_array($usergroups)) {
                        foreach ($usergroups as $group) {
                            $extendedGroup = $usergroupRepository->findByUid($group->getUid());
                            if ($extendedGroup->getServerUid()) {
                                $typo3User->removeUsergroup($group);
                            }
                        }
                    } else {
                        $usergroup = GeneralUtility::makeInstance(ObjectStorage::class);
                        $typo3User->setUsergroup($usergroup);
                    }
                } else {
                    $usergroup = GeneralUtility::makeInstance(ObjectStorage::class);
                    $typo3User->setUsergroup($usergroup);
                }
            } else {
                $usergroup = GeneralUtility::makeInstance(ObjectStorage::class);
                $typo3User->setUsergroup($usergroup);
            }
        }
    }
}
