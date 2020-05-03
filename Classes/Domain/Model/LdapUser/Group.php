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
 * Model for groups read from LDAP server.
 */
class Group extends \NormanSeibert\Ldap\Domain\Model\LdapUser\LdapObject implements \Psr\Log\LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var \NormanSeibert\Ldap\Domain\Repository\Typo3User\UserGroupRepositoryInterface
     */
    protected $usergroupRepository;

    /**
     * @var \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups
     */
    protected $usergroupRules;

    /**
     * @var string
     */
    protected $groupObject;

    public function __construct()
    {
        parent::__construct();
    }

    public function setGroup(\NormanSeibert\Ldap\Domain\Model\Typo3User\UserGroupInterface $group)
    {
        $this->group = $group;
    }

    /**
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\UserGroupInterface
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * Tries to load the TYPO3 group based on DN or group title.
     */
    public function loadGroup()
    {
        $pid = $this->usergroupRules->getPid();
        $msg = 'Search for group record with DN = '.$this->dn.' in page '.$pid;
        if ($this->ldapConfig->logLevel >= 2) {
            $this->logger->debug($msg);
        }
        // search for DN
        $group = $this->usergroupRepository->findByDn($this->dn, $pid);
        // search for group title if no record with DN found
        if (is_object($group)) {
            $msg = 'Group record already existing: '.$group->getUid();
            if (3 == $this->ldapConfig->logLevel) {
                $this->logger->debug($msg);
            }
        } else {
            $mapping = $this->usergroupRules->getMapping();
            $rawLdapGroupTitle = $mapping['title.']['data'];
            $ldapGroupTitle = str_replace('field:', '', $rawLdapGroupTitle);
            $groupTitle = $this->getAttribute($ldapGroupTitle);
            $group = $this->usergroupRepository->findByGroupTitle($groupTitle, $pid);
            if (is_object($group)) {
                $msg = 'Group record already existing: '.$group->getUid();
                if (3 == $this->ldapConfig->logLevel) {
                    $this->logger->debug($msg);
                }
            }
        }
        $this->group = $group;
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
            $this->group = $this->objectManager->get($this->groupObject);
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
                    if ($this->ldapConfig->logLevel >= 1) {
                        $this->logger->warning($msg);
                    }
                }
            }

            if ($lastRun) {
                $this->group->setLastRun($lastRun);
            }

            if ($this->usergroupRules->getRestrictToGroups()) {
                $groupOK = false;
                $groupOK = $this->checkGroupName($group->getTitle());
                if ($groupOK) {
                    $createGroup = true;
                }
            } else {
                $createGroup = true;
            }
        } else {
            // error condition. There should always be a group title
            $msg = 'No group title (Server: '.$this->ldapServer->getConfiguration()->getUid().', DN: '.$this->dn.')';
            if ($this->ldapConfig->logLevel >= 1) {
                $this->logger->notice($msg);
            }
            \NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING, $msg, $this->ldapServer->getConfiguration()->getUid());
        }

        if ($createGroup) {
            $this->usergroupRepository->add($this->group);
            $msg = 'Create group record "'.$groupTitle.'" (DN: '.$this->dn.')';
            if ($this->ldapConfig->logLevel >= 3) {
                $debugData = (array) $this->user;
                $this->logger->debug($msg, $debugData);
            } elseif (2 == $this->ldapConfig->logLevel) {
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
                    if ($this->ldapConfig->logLevel >= 1) {
                        $this->logger->warning($msg);
                    }
                }
            }

            if ($lastRun) {
                $this->group->setLastRun($lastRun);
            }

            if ($this->usergroupRules->getRestrictToGroups()) {
                $groupOK = false;
                $groupOK = $this->checkGroupName($group->getTitle());
                if ($groupOK) {
                    $updateGroup = true;
                }
            } else {
                $updateGroup = true;
            }
        } else {
            // error condition. There should always be a group title
            $msg = 'No group title (Server: '.$this->ldapServer->getConfiguration()->getUid().', DN: '.$this->dn.')';
            if ($this->ldapConfig->logLevel >= 1) {
                $this->logger->notice($msg);
            }
            \NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING, $msg, $this->ldapServer->getConfiguration()->getUid());
        }

        if ($updateGroup) {
            $this->usergroupRepository->update($this->group);
            $msg = 'Update group record "'.$groupTitle.'" (DN: '.$this->dn.')';
            if ($this->ldapConfig->logLevel >= 3) {
                $debugData = (array) $this->user;
                $this->logger->debug($msg, $debugData);
            } elseif (2 == $this->ldapConfig->logLevel) {
                $this->logger->debug($msg);
            }
        }
    }

    /**
     * adds new TYPO3 usergroups.
     *
     * @param array  $newGroups
     * @param array  $existingGroups
     * @param string $lastRun
     *
     * @return array
     */
    public function addNewGroups($newGroups, $existingGroups, $lastRun)
    {
        if (!is_array($existingGroups)) {
            $existingGroups = [];
        }
        $assignedGroups = $existingGroups;
        $addnewgroups = $this->usergroupRules->getImportGroups();

        $pid = $this->usergroupRules->getPid();

        if ((is_array($newGroups)) && ($addnewgroups)) {
            foreach ($newGroups as $group) {
                $newGroup = $this->objectManager->get($this->groupObject);
                $newGroup->setPid($pid);
                $newGroup->setTitle($group['title']);
                $newGroup->setDN($group['dn']);
                $newGroup->setServerUid($this->ldapServer->getConfiguration()->getUid());
                if ($lastRun) {
                    $newGroup->setLastRun($lastRun);
                }
                // LDAP attributes from mapping
                if ($group['groupObject']) {
                    $groupObject = $group['groupObject'];
                    $insertArray = $this->getAttributes('group', $groupObject->getAttributes());
                    unset($insertArray['field']);
                    foreach ($insertArray as $field => $value) {
                        $ret = $newGroup->_setProperty($field, $value);
                        if (!$ret) {
                            $msg = 'Property "'.$field.'" is unknown to Extbase.';
                            if ($this->ldapConfig->logLevel >= 2) {
                                $this->logger->warning($msg);
                            }
                        }
                    }
                }
                $this->usergroupRepository->add($newGroup);
                $msg = 'Insert user group "'.$group['title'].')';
                if ($this->ldapConfig->logLevel >= 3) {
                    $debugData = (array) $newGroup;
                    $this->logger->debug($msg, $debugData);
                } elseif (2 == $this->ldapConfig->logLevel) {
                    $this->logger->debug($msg);
                }
                $assignedGroups[] = $newGroup;
                $this->ldapServer->addGroup($newGroup, $this->groupObject);
            }
        }

        return $assignedGroups;
    }

    /** Assigns TYPO3 usergroups to the current TYPO3 user.
     *
     * @param string $lastRun
     * @param \NormanSeibert\Ldap\Domain\Model\LdapUser\User
     * @param mixed $user
     *
     * @return array
     */
    public function assignGroups($lastRun = null, $user)
    {
        $ret = [];
        $mapping = $this->usergroupRules->getMapping();

        if (is_array($mapping)) {
            if ($this->usergroupRules->getReverseMapping()) {
                $ret = $this->reverseAssignGroups($user);
            } else {
                switch (strtolower($mapping['field'])) {
                    case 'text':
                        $ret = $this->assignGroupsText();

                        break;

                    case 'parent':
                        $ret = $this->assignGroupsParent();

                        break;

                    case 'dn':
                        $ret = $this->assignGroupsDN();

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
            if ($this->ldapConfig->logLevel >= 2) {
                $this->logger->notice($msg);
            }
        }

        $assignedGroups = $this->addNewGroups($ret['newGroups'], $ret['existingGroups'], $lastRun);

        if ($this->usergroupRules->getAddToGroups()) {
            $addToGroups = $this->usergroupRules->getAddToGroups();
            $groupsToAdd = $this->usergroupRepository->findByUids(explode(',', $addToGroups));
            $usergroups = array_merge($assignedGroups, $groupsToAdd);
        } else {
            $usergroups = $assignedGroups;
        }

        return $usergroups;
    }

    /** Checks whether a usergroup is in the list of allowed groups.
     *
     * @param string $groupname
     *
     * @return bool
     */
    public function checkGroupName($groupname)
    {
        $ret = false;
        $onlygroup = $this->usergroupRules->getRestrictToGroups();
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
            if ((!$ret) && (3 == $this->ldapConfig->logLevel)) {
                $msg = 'Filtered out: '.$groupname;
                $this->logger->debug($msg, $logArray);
            }
        }

        return $ret;
    }

    /**
     * @param string $attribute
     * @param string $selector
     * @param string $usergroup
     * @param string $dn
     * @param object $obj
     *
     * @return array
     */
    private function resolveGroup($attribute, $selector, $usergroup, $dn = null, $obj = null)
    {
        $groupFound = false;
        $resolvedGroup = false;
        $newGroup = false;

        $allGroups = $this->ldapServer->getAllGroups();

        foreach ($allGroups as $group) {
            // @var $group \NormanSeibert\Ldap\Domain\Model\Typo3User\UserGroupInterface
            $attrValue = $group->_getProperty($attribute);
            if ($selector == $attrValue) {
                $groupFound = $group;
            }
        }

        if (is_object($groupFound)) {
            if ($this->checkGroupName($groupFound->getTitle())) {
                $resolvedGroup = $groupFound;
            }
        } elseif ($usergroup) {
            if ($this->checkGroupName($usergroup)) {
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
     *
     * @param \NormanSeibert\Ldap\Domain\Model\LdapUser\User $user
     *
     * @return array
     */
    private function reverseAssignGroups($user)
    {
        $msg = 'Use reverse mapping for usergroups';
        if (3 == $this->ldapConfig->logLevel) {
            $this->logger->debug($msg);
        }

        $ret = [];
        $mapping = $this->usergroupRules->getMapping();
        $searchAttribute = $this->usergroupRules->getSearchAttribute();

        if (!$searchAttribute) {
            $searchAttribute = 'dn';
        }

        $username = mb_strtolower($user->getAttribute($searchAttribute));

        $ldapGroups = $this->ldapServer->getGroups($username);

        if (is_array($ldapGroups)) {
            unset($ldapGroups['count']);
            if (0 == count($ldapGroups)) {
                $msg = 'No usergroups found for reverse mapping';
                if ($this->ldapConfig->logLevel >= 2) {
                    $this->logger->notice($msg);
                }
            } else {
                $msg = 'Usergroups found for reverse mapping';
                if ($this->ldapConfig->logLevel >= 2) {
                    $this->logger->debug($msg);
                }
                $msg = 'Usergroups for reverse mapping';
                if (3 == $this->ldapConfig->logLevel) {
                    $this->logger->debug($msg, $ldapGroups);
                }
                foreach ($ldapGroups as $group) {
                    // $this->cObj->alternativeData = $group;
                    // $usergroup = $this->cObj->stdWrap('', $mapping['title.']);
                    $usergroup = $this->mapAttribute($mapping, 'title', $group);

                    $msg = 'Try to add usergroup "'.$usergroup.'" to user';
                    if (3 == $this->ldapConfig->logLevel) {
                        $this->logger->debug($msg);
                    }

                    if ($usergroup) {
                        $tmp = $this->resolveGroup('title', $usergroup, $usergroup, $group['dn']);
                        if ($tmp['newGroup']) {
                            $ret['newGroups'][] = $tmp['newGroup'];
                        }
                        if ($tmp['existingGroup']) {
                            $ret['existingGroups'][] = $tmp['existingGroup'];
                        }
                    } else {
                        $msg = 'Usergroup mapping did not result in a title';
                        if ($this->ldapConfig->logLevel >= 1) {
                            $this->logger->warning($msg);
                        }
                    }
                }
            }
        } else {
            $msg = 'No usergroups found for reverse mapping';
            if ($this->ldapConfig->logLevel >= 2) {
                $this->logger->notice($msg);
            }
        }

        $msg = 'Resulting usergroups to add or update';
        if (3 == $this->ldapConfig->logLevel) {
            $this->logger->debug($msg, $ret);
        }

        return $ret;
    }

    /** Determines usergroups based on a text attribute.
     *
     * @return array
     */
    private function assignGroupsText()
    {
        $msg = 'Use text based mapping for usergroups';
        if (3 == $this->ldapConfig->logLevel) {
            $this->logger->debug($msg);
        }
        $ret = [];
        $mapping = $this->usergroupRules->getMapping();

        // $this->cObj->alternativeData = $this->attributes;
        // $result = $this->cObj->stdWrap('', $mapping['title.']);
        $result = $this->getAttributeMapping($mapping, 'title', $this->attributes);

        if (is_array($result)) {
            unset($result['count']);
            $attr = [];
            foreach ($result as $v) {
                $attr[] = $this->cObj->stdWrap($v, $stdWrap);
            }
            $result = $attr;
        } elseif ('Array' == $result) {
            $tmp = explode(':', $mapping['title.']['data']);
            $attrname = $tmp[1];
            $result = $this->attributes[$attrname];
            unset($result['count']);
            $attr = [];
            foreach ($result as $v) {
                $attr[] = $this->cObj->stdWrap($v, $stdWrap);
            }
            $result = $attr;
        } else {
            $result = $this->cObj->stdWrap($result, $stdWrap);
        }
        $usergroups = $result;

        if (is_array($usergroups)) {
            unset($usergroups['count']);
            foreach ($usergroups as $group) {
                $tmp = $this->resolveGroup('title', $group, $group);
                if ($tmp['newGroup']) {
                    $ret['newGroups'][] = $tmp['newGroup'];
                }
                if ($tmp['existingGroup']) {
                    $ret['existingGroups'][] = $tmp['existingGroup'];
                }
            }
        } elseif ($usergroups) {
            $tmp = $this->resolveGroup('title', $usergroups, $usergroups);
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
     *
     * @internal param array $mapping
     *
     * @return array
     */
    private function assignGroupsParent()
    {
        $msg = 'Use parent node for usergroup';
        if (3 == $this->ldapConfig->logLevel) {
            $this->logger->debug($msg);
        }
        $ret = [];
        $mapping = $this->usergroupRules->getMapping();

        $path = explode(',', $this->dn);
        unset($path[0]);
        $parentDN = implode(',', $path);
        $ldapGroup = $this->ldapServer->getGroup($parentDN);

        // $this->cObj->alternativeData = $ldapGroup;
        // $usergroup = $this->cObj->stdWrap('', $mapping['title.']);
        $usergroup = $this->mapAttribute($mapping, 'title', $ldapGroup);

        if ($usergroup) {
            $tmp = $this->resolveGroup('title', $usergroup, $usergroup, $ldapGroup['dn']);
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
     *
     * @return array
     */
    private function assignGroupsDN()
    {
        $msg = 'Find usergroup DNs in user attribute for mapping';
        if (3 == $this->ldapConfig->logLevel) {
            $this->logger->debug($msg);
        }
        $ret = [];
        $mapping = $this->usergroupRules->getMapping();

        // $this->cObj->alternativeData = $this->attributes;
        // $groupDNs = $this->cObj->stdWrap('', $mapping['field.']);
        $groupDNs = $this->getAttributeMapping($mapping, 'field', $this->attributes);

        if (is_array($groupDNs)) {
            unset($groupDNs['count']);
            foreach ($groupDNs as $groupDN) {
                $ldapGroup = $this->ldapServer->getGroup($groupDN);
                if (is_array($ldapGroup)) {
                    // $this->cObj->alternativeData = $ldapGroup;
                    // $usergroup = $this->cObj->stdWrap('', $mapping['title.']);
                    $usergroup = $this->getAttributeMapping($mapping, 'title', $ldapGroup);
                    $tmp = $this->resolveGroup('dn', $groupDN, $usergroup, $groupDN);
                    if ($tmp['newGroup']) {
                        $ret['newGroups'][] = $tmp['newGroup'];
                    }
                    if ($tmp['existingGroup']) {
                        $ret['existingGroups'][] = $tmp['existingGroup'];
                    }
                }
            }
        } elseif ($groupDNs) {
            $ldapGroup = $this->ldapServer->getGroup($groupDNs);
            // $this->cObj->alternativeData = $ldapGroup;
            // $usergroup = $this->cObj->stdWrap('', $mapping['title.']);
            $usergroup = $this->getAttributeMapping($mapping, 'title', $ldapGroup);
            $tmp = $this->resolveGroup('dn', $groupDNs, $usergroup, $groupDNs);
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
