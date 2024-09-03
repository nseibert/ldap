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

use NormanSeibert\Ldap\Domain\Model\LdapServer\LdapServer;
use NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\FrontendUserGroupRepository;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserGroupRepository;
use NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUserGroup;
use NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUserGroup;
use NormanSeibert\Ldap\Domain\Model\LdapUser\LdapFeGroup;
use NormanSeibert\Ldap\Domain\Model\LdapUser\LdapBeGroup;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Log\LoggerInterface;
use NormanSeibert\Ldap\Service\Mapping\GenericMapper;
use NormanSeibert\Ldap\Service\LdapHandler;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * Maps groups read from LDAP to TYPO3 groups
 */

/**
 * Maps groups read from LDAP to TYPO3 groups.
 */
class LdapTypo3GroupMapper
{
    private LoggerInterface $logger;

    const ERROR = 2;
    const WARNING = 1;
    const OK = 0;
    const INFO = -1;
    const NOTICE = -2;

    protected GenericMapper $mapper;

    protected LdapHandler $ldapHandler;

    protected int $logLevel;

    public function __construct()
    {
        $conf = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ExtensionConfiguration')->get('ldap');
        $this->logLevel = $conf['logLevel'];

        $this->mapper = new GenericMapper();
        $this->ldapHandler = new LdapHandler();

        $this->logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * Tries to load the TYPO3 group based on DN or group title.
     */
    public function loadGroup(LdapFeGroup | LdapBeGroup $ldapGroup): FrontendUserGroup | BackendUserGroup
    {
        if ($ldapGroup->getUserType() == 'be') {
            $groupRepository = GeneralUtility::makeInstance(BackendUserGroupRepository::class);
            $groupRules = $ldapGroup->getLdapServer()->getConfiguration()->getBeUserRules()->getGroupRules();
        } else {
            $groupRepository = GeneralUtility::makeInstance(FrontendUserGroupRepository::class);
            $groupRules = $ldapGroup->getLdapServer()->getConfiguration()->getFeUserRules()->getGroupRules();
        }
        $pid = $groupRules->getPid();

        $msg = 'Search for group record with DN = ' . $ldapGroup->getDN() . ' in page ' . $pid;
        if ($this->logLevel >= 2) {
            $this->logger->debug($msg);
        }
        // search for DN
        $group = $groupRepository->findByDn($ldapGroup->getDN(), $pid);
        // search for group title if no record with DN found
        if (is_object($group)) {
            $msg = 'Group record already existing: ' . $group->getUid();
            if (3 == $this->logLevel) {
                $this->logger->debug($msg);
            }
        } else {
            $mapping = $groupRules->getMapping();
            $rawLdapGroupTitle = $mapping['title.']['data'];
            $ldapGroupTitle = str_replace('field:', '', $rawLdapGroupTitle);
            $groupTitle = $ldapGroup->getAttribute($ldapGroupTitle);
            $group = $groupRepository->findByGroupTitle($groupTitle, $pid);
            if (is_object($group)) {
                $msg = 'Group record already existing: ' . $group->getUid();
                if (3 == $this->logLevel) {
                    $this->logger->debug($msg);
                }
            }
        }
        return $group;
    }

    /**
     * adds a new TYPO3 group.
     *
     * @param string $lastRun
     */
    public function addGroup($lastRun = null)
    {
        $mapping = $this->usergroupRules->getMapping();
        $rawLdapGroupTitle = $mapping['title.']['data'];
        $ldapGroupTitle = str_replace('field:', '', $rawLdapGroupTitle);

        $groupTitle = $this->getAttribute($ldapGroupTitle);

        $createGroup = false;

        if ($groupTitle) {
            $this->group = GeneralUtility::makeInstance($this->groupObject::class);
            $this->group->setServerUid($this->ldapServer->getConfiguration()->getUid());
            $this->group->setTitle($groupTitle);
            $this->group->setDN($this->dn);

            $pid = $this->usergroupRules->getPid();
            if (empty($pid)) {
                $pid = 0;
            }
            $this->group->setPid($pid);

            // LDAP attributes from mapping
            $insertArray = $this->mapAttributes();
            foreach ($insertArray as $field => $value) {
                $ret = $this->user->_setProperty($field, $value);
                if (!$ret) {
                    $msg = 'Property "'.$field.'" is unknown to Extbase.';
                    if ($this->logLevel >= 1) {
                        $this->logger->warning($msg);
                    }
                }
            }

            if ($lastRun) {
                $this->group->setLastRun($lastRun);
            }

            if ($this->usergroupRules->getRestrictToGroups()) {
                $groupOK = false;
                $groupOK = $this->checkGroupName($groupTitle);
                if ($groupOK) {
                    $createGroup = true;
                }
            } else {
                $createGroup = true;
            }
        } else {
            // error condition. There should always be a group title
            $msg = 'No group title (Server: '.$this->ldapServer->getConfiguration()->getUid().', DN: '.$this->dn.')';
            if ($this->logLevel >= 1) {
                $this->logger->notice($msg);
            }
            Helpers::addError(self::WARNING, $msg, $this->ldapServer->getConfiguration()->getUid());
        }

        if ($createGroup) {
            $this->usergroupRepository->add($this->group);
            $msg = 'Create group record "'.$groupTitle.'" (DN: '.$this->dn.')';
            if ($this->logLevel >= 3) {
                $debugData = (array) $this->user;
                $this->logger->debug($msg, $debugData);
            } elseif (2 == $this->logLevel) {
                $this->logger->debug($msg);
            }
        }
    }

    /**
     * updates a TYPO3 group.
     *
     * @param string $lastRun
     */
    public function updateGroup($lastRun = null)
    {
        $mapping = $this->usergroupRules->getMapping();
        $rawLdapGroupTitle = $mapping['title.']['data'];
        $ldapGroupTitle = str_replace('field:', '', $rawLdapGroupTitle);

        $groupTitle = $this->getAttribute($ldapGroupTitle);

        $updateGroup = false;

        if ($groupTitle) {
            $this->group->setTitle($groupTitle);
            $this->group->setServerUid($this->ldapServer->getConfiguration()->getUid());
            $this->group->setDN($this->dn);

            $pid = $this->usergroupRules->getPid();
            if (!empty($pid)) {
                $this->user->setPid($pid);
            }

            // LDAP attributes from mapping
            $insertArray = $this->mapAttributes();
            foreach ($insertArray as $field => $value) {
                $ret = $this->user->_setProperty($field, $value);
                if (!$ret) {
                    $msg = 'Property "'.$field.'" is unknown to Extbase.';
                    if ($this->logLevel >= 1) {
                        $this->logger->warning($msg);
                    }
                }
            }

            if ($lastRun) {
                $this->group->setLastRun($lastRun);
            }

            if ($this->usergroupRules->getRestrictToGroups()) {
                $groupOK = false;
                $groupOK = $this->checkGroupName($groupTitle);
                if ($groupOK) {
                    $updateGroup = true;
                }
            } else {
                $updateGroup = true;
            }
        } else {
            // error condition. There should always be a group title
            $msg = 'No group title (Server: '.$this->ldapServer->getConfiguration()->getUid().', DN: '.$this->dn.')';
            if ($this->logLevel >= 1) {
                $this->logger->notice($msg);
            }
            \NormanSeibert\Ldap\Utility\Helpers::addError(self::WARNING, $msg, $this->ldapServer->getConfiguration()->getUid());
        }

        if ($updateGroup) {
            $this->usergroupRepository->update($this->group);
            $msg = 'Update group record "'.$groupTitle.'" (DN: '.$this->dn.')';
            if ($this->logLevel >= 3) {
                $debugData = (array) $this->user;
                $this->logger->debug($msg, $debugData);
            } elseif (2 == $this->logLevel) {
                $this->logger->debug($msg);
            }
        }
    }

    /**
     * adds new TYPO3 usergroups.
     */
    public function addNewGroups(
        LdapServer $ldapServer,
        string $userType,
        array | string $newGroups,
        array | string $existingGroups,
        string $lastRun = null): array
    {
        if ($userType == 'be') {
            $groupRules = $ldapServer->getConfiguration()->getBeUserRules()->getGroupRules();
            $groupRepository = GeneralUtility::makeInstance(BackendUserGroupRepository::class);
            $groupObject = 'NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUserGroup';
        } else {
            $groupRules = $ldapServer->getConfiguration()->getFeUserRules()->getGroupRules();
            $groupRepository = GeneralUtility::makeInstance(FrontendUserGroupRepository::class);
            $groupObject = 'NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUserGroup';
        }
        
        if (!is_array($existingGroups)) {
            $existingGroups = [];
        }
        $assignedGroups = $existingGroups;
        $addnewgroups = $groupRules->getImportGroups();

        $pid = $groupRules->getPid();

        if ((is_array($newGroups)) && ($addnewgroups)) {
            foreach ($newGroups as $group) {
                $newGroup = GeneralUtility::makeInstance($groupObject);
                $newGroup->setPid($pid);
                $newGroup->setTitle($group['title']);
                $newGroup->setDN($group['dn']);
                $newGroup->setServerUid($ldapServer->getUid());
                if ($lastRun) {
                    $newGroup->setLastRun($lastRun);
                }
                // LDAP attributes from mapping
                if ($group['groupObject']) {
                    $groupObject = $group['groupObject'];
                    $insertArray = $groupObject->getAttributes();
                    unset($insertArray['field']);
                    foreach ($insertArray as $field => $value) {
                        $ret = $newGroup->_setProperty($field, $value);
                        if (!$ret) {
                            $msg = 'Property "' . $field . '" is unknown to Extbase.';
                            if ($this->logLevel >= 2) {
                                $this->logger->warning($msg);
                            }
                        }
                    }
                }
                $groupRepository->add($newGroup);
                $msg = 'Insert user group "' . $group['title'] . ')';
                if ($this->logLevel >= 3) {
                    $debugData = (array) $newGroup;
                    $this->logger->debug($msg, $debugData);
                } elseif (2 == $this->logLevel) {
                    $this->logger->debug($msg);
                }
                $assignedGroups[] = $newGroup;
                $groupRepository->add($newGroup);
            }
            $persistenceManager = GeneralUtility::makeInstance(persistenceManager::class);
            $persistenceManager->persistAll();
        }

        return $assignedGroups;
    }
    
    public function assignGroups(
        LdapServer $ldapServer,
        string $userType,
        string $userDN,
        array $userAttributes,
        string $lastRun = null): array
    {
        $ret = [];

        if ($userType == 'be') {
            $groupRules = $ldapServer->getConfiguration()->getBeUserRules()->getGroupRules();
            $groupRepository = GeneralUtility::makeInstance(BackendUserGroupRepository::class);
        } else {
            $groupRules = $ldapServer->getConfiguration()->getFeUserRules()->getGroupRules();
            $groupRepository = GeneralUtility::makeInstance(FrontendUserGroupRepository::class);
        }
        $mapping = $groupRules->getMapping();

        if (is_array($mapping)) {
            if ($groupRules->getReverseMapping()) {
                $ret = $this->reverseAssignGroups($ldapServer, $userType, $groupRules, $userAttributes);
            } else {
                switch (strtolower($mapping['field'])) {
                    case 'text':
                        $ret = $this->assignGroupsText($ldapServer, $userType, $groupRules, $userAttributes);

                        break;

                    case 'parent':
                        $ret = $this->assignGroupsParent($ldapServer, $userType, $groupRules, $userDN);

                        break;

                    case 'dn':
                        $ret = $this->assignGroupsDN($ldapServer, $userType, $groupRules, $userAttributes);

                        break;

                    default:
                }
            }
        } else {
            $ret = [
                'newGroups' => [],
                'existingGroups' => [],
            ];

            $msg = 'No mapping for usergroups found';
            if ($this->logLevel >= 2) {
                $this->logger->notice($msg);
            }
        }

        if (!isset($ret['newGroups'])) {
            $ret['newGroups'] = '';
        }
        if (!isset($ret['existingGroups'])) {
            $ret['existingGroups'] = '';
        }

        $assignedGroups = $this->addNewGroups($ldapServer, $userType, $ret['newGroups'], $ret['existingGroups'], $lastRun);

        if ($groupRules->getAddToGroups()) {
            $addToGroups = $groupRules->getAddToGroups();
            $groupsToAdd = $groupRepository->findByUids(explode(',', $addToGroups));
            $usergroups = array_merge($assignedGroups, $groupsToAdd);
        } else {
            $usergroups = $assignedGroups;
        }

        return $usergroups;
    }

    /** Checks whether a usergroup is in the list of allowed groups.
     */
    public function checkGroupName(ServerConfigurationGroups $groupRules, string $groupname): bool
    {
        $ret = false;
        $onlygroup = $groupRules->getRestrictToGroups();
        if (empty($onlygroup)) {
            $ret = true;
        } else {
            $onlygrouparray = explode(',', $onlygroup);
            if (is_array($onlygrouparray)) {
                $logArray = [];
                foreach ($onlygrouparray as $value) {
                    $regExResult = preg_match(trim($value), $groupname);
                    if ($regExResult) {
                        $ret = true;
                    }
                    $logArray[$groupname] = $regExResult;
                    if ($ret) {
                        break;
                    }
                }
            }
            if ((!$ret) && (3 == $this->logLevel)) {
                $msg = 'Filtered out: ' . $groupname;
                $this->logger->debug($msg, $logArray);
            }
        }

        return $ret;
    }

    private function resolveGroup(
        LdapServer $ldapServer,
        string $userType,
        ServerConfigurationGroups $groupRules,
        string $attribute,
        string $selector,
        string $usergroup,
        string $dn = null,
        object $obj = null
    ): array
    {
        $groupFound = false;
        $resolvedGroup = false;
        $newGroup = false;

        if ($userType == 'be') {
            $groupRepository = GeneralUtility::makeInstance(BackendUserGroupRepository::class);
        } else {
            $groupRepository = GeneralUtility::makeInstance(FrontendUserGroupRepository::class);
        }

        $allGroups = $groupRepository->findLdapImportedByServer($ldapServer->getUid());

        foreach ($allGroups as $group) {
            $attrValue = $group->_getProperty($attribute);
            if ($selector == $attrValue) {
                $groupFound = $group;
            }
        }

        if (is_object($groupFound)) {
            if ($this->checkGroupName($groupRules, $groupFound->getTitle())) {
                $resolvedGroup = $groupFound;
            }
        } elseif ($usergroup) {
            if ($this->checkGroupName($groupRules, $usergroup)) {
                $newGroup = [
                    'title' => $usergroup,
                    'dn' => $dn,
                    'groupObject' => $obj,
                ];
            }
        }

        return [
            'newGroup' => $newGroup,
            'existingGroup' => $resolvedGroup,
        ];
    }

    /** Assigns TYPO3 usergroups to the current TYPO3 user by additionally querying the LDAP server for groups.
     */
    private function reverseAssignGroups(
        LdapServer $ldapServer,
        string $userType,
        ServerConfigurationGroups $groupRules,
        array $userAttributes
    ): array
    {
        $msg = 'Use reverse mapping for usergroups';
        if (3 == $this->logLevel) {
            $this->logger->debug($msg);
        }

        $ret = [];
        $mapping = $groupRules->getMapping();
        $searchAttribute = $groupRules->getSearchAttribute();

        if (!$searchAttribute) {
            $searchAttribute = 'dn';
        }

        if (is_array($userAttributes) && $userAttributes[$searchAttribute]) {
            $searchFor = mb_strtolower($userAttributes[$searchAttribute]);

            $ldapGroups = $this->ldapHandler->getGroups($ldapServer, $searchFor);

            if (is_array($ldapGroups)) {
                unset($ldapGroups['count']);
                if (0 == count($ldapGroups)) {
                    $msg = 'No usergroups found for reverse mapping';
                    if ($this->logLevel >= 2) {
                        $this->logger->notice($msg);
                    }
                } else {
                    $msg = 'Usergroups found for reverse mapping';
                    if ($this->logLevel >= 2) {
                        $this->logger->debug($msg);
                    }
                    $msg = 'Usergroups for reverse mapping';
                    if (3 == $this->logLevel) {
                        $this->logger->debug($msg, $ldapGroups);
                    }
                    foreach ($ldapGroups as $group) {
                        $usergroup = $this->mapper->mapAttribute($mapping, 'title', $group);

                        $msg = 'Try to add usergroup "' . $usergroup . '" to user';
                        if (3 == $this->logLevel) {
                            $this->logger->debug($msg);
                        }

                        if ($usergroup) {
                            $tmp = $this->resolveGroup($ldapServer, $userType, $groupRules, 'title', $usergroup, $usergroup, $group['dn']);
                            if ($tmp['newGroup']) {
                                $ret['newGroups'][] = $tmp['newGroup'];
                            }
                            if ($tmp['existingGroup']) {
                                $ret['existingGroups'][] = $tmp['existingGroup'];
                            }
                        } else {
                            $msg = 'Usergroup mapping did not result in a title';
                            if ($this->logLevel >= 1) {
                                $this->logger->warning($msg);
                            }
                        }
                    }
                }
            } else {
                $msg = 'No usergroups found for reverse mapping';
                if ($this->logLevel >= 2) {
                    $this->logger->notice($msg);
                }
            }

            $msg = 'Resulting usergroups to add or update';
            if (3 == $this->logLevel) {
                $this->logger->debug($msg, $ret);
            }
        } else {
            $msg = 'Record is missing attribute "' . $searchAttribute . '" and reverseMapping cannot search for groups';
            if ($this->logLevel >= 1) {
                $this->logger->warning($msg);
            }
        }

        return $ret;
    }
    
    private function assignGroupsText(
        LdapServer $ldapServer,
        string $userType,
        ServerConfigurationGroups $groupRules,
        array $userAttributes
    ): array
    {
        $msg = 'Use text based mapping for usergroups';
        if (3 == $this->logLevel) {
            $this->logger->debug($msg);
        }
        $ret = [];
        $mapping = $groupRules->getMapping();

        $result = $this->mapper->getAttributeMapping($mapping, 'title', $userAttributes);

        if (is_array($result)) {
            unset($result['count']);
            $attr = [];
            foreach ($result as $v) {
                $attr[] = $v;
            }
            $result = $attr;
        } elseif ('Array' == $result) {
            $tmp = explode(':', $mapping['title.']['data']);
            $attrname = $tmp[1];
            $result = $userAttributes[$attrname];
            unset($result['count']);
            $attr = [];
            foreach ($result as $v) {
                $attr[] = $v;
            }
            $result = $attr;
        } else {
            $result = $result;
        }
        $usergroups = $result;

        if (is_array($usergroups)) {
            unset($usergroups['count']);
            foreach ($usergroups as $group) {
                $tmp = $this->resolveGroup($ldapServer, $userType, $groupRules, 'title', $group, $group);
                if ($tmp['newGroup']) {
                    $ret['newGroups'][] = $tmp['newGroup'];
                }
                if ($tmp['existingGroup']) {
                    $ret['existingGroups'][] = $tmp['existingGroup'];
                }
            }
        } elseif ($usergroups) {
            $tmp = $this->resolveGroup($ldapServer, $userType, $groupRules, 'title', $usergroups, $usergroups);
            if ($tmp['newGroup']) {
                $ret['newGroups'][] = $tmp['newGroup'];
            }
            if ($tmp['existingGroup']) {
                $ret['existingGroups'][] = $tmp['existingGroup'];
            }
        }

        return $ret;
    }

    /** Determines usergroups based on the user records parent record.
     */
    private function assignGroupsParent(
        LdapServer $ldapServer,
        string $userType,
        ServerConfigurationGroups $groupRules,
        string $userDN
    ): array
    {
        $msg = 'Use parent node for usergroup';
        if (3 == $this->logLevel) {
            $this->logger->debug($msg);
        }
        $ret = [];
        $mapping = $groupRules->getMapping();

        $path = explode(',', $userDN);
        unset($path[0]);
        $parentDN = implode(',', $path);
        $ldapGroup = $this->ldapHandler->getGroup($ldapServer, $parentDN);

        $usergroup = $this->mapper->mapAttribute($mapping, 'title', $ldapGroup);

        if ($usergroup) {
            $tmp = $this->resolveGroup($ldapServer, $userType, $groupRules, 'title', $usergroup, $usergroup, $ldapGroup['dn']);
            if ($tmp['newGroup']) {
                $ret['newGroups'][] = $tmp['newGroup'];
            }
            if ($tmp['existingGroup']) {
                $ret['existingGroups'][] = $tmp['existingGroup'];
            }
        }

        return $ret;
    }

    /** Determines usergroups based on DNs in an attribute of the user's record.
     */
    private function assignGroupsDN(
        LdapServer $ldapServer,
        string $userType,
        ServerConfigurationGroups $groupRules,
        array $userAttributes
    ): array
    {
        $msg = 'Find usergroup DNs in user attribute for mapping';
        if (3 == $this->logLevel) {
            $this->logger->debug($msg);
        }
        $ret = [];
        $mapping = $groupRules->getMapping();

        $groupDNs = $this->mapper->getAttributeMapping($mapping, 'field', $userAttributes);

        if (is_array($groupDNs)) {
            unset($groupDNs['count']);
            foreach ($groupDNs as $groupDN) {
                $ldapGroup = $this->ldapHandler->getGroup($ldapServer, $groupDN);
                if (is_array($ldapGroup)) {
                    $usergroup = $this->mapper->getAttributeMapping($mapping, 'title', $ldapGroup);
                    $tmp = $this->resolveGroup($ldapServer, $userType, $groupRules, 'dn', $groupDN, $usergroup, $groupDN);
                    if ($tmp['newGroup']) {
                        $ret['newGroups'][] = $tmp['newGroup'];
                    }
                    if ($tmp['existingGroup']) {
                        $ret['existingGroups'][] = $tmp['existingGroup'];
                    }
                }
            }
        } elseif ($groupDNs) {
            $ldapGroup = $this->ldapHandler->getGroup($ldapServer, $groupDNs);
            $usergroup = $this->mapper->getAttributeMapping($mapping, 'title', $ldapGroup);
            $tmp = $this->resolveGroup($ldapServer, $userType, $groupRules, 'dn', $groupDNs, $usergroup, $groupDNs);
            if ($tmp['newGroup']) {
                $ret['newGroups'][] = $tmp['newGroup'];
            }
            if ($tmp['existingGroup']) {
                $ret['existingGroups'][] = $tmp['existingGroup'];
            }
        }

        return $ret;
    }
}
