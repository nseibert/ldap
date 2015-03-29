<?php
namespace NormanSeibert\Ldap\Domain\Model\LdapUser;
/**
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
 * @copyright 2013 Norman Seibert
 */

/**
 * Model for users read from LDAP server
 */
class User extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity {
	
	/**
	 *
	 * @var string 
	 */
	protected $dn;
	
	/**
	 *
	 * @var array 
	 */
	protected $attributes;
	
	/**
	 * @var \NormanSeibert\Ldap\Domain\Repository\Typo3User\UserRepositoryInterface
	 */
	protected $userRepository;
	
	/**
	 * @var \NormanSeibert\Ldap\Domain\Repository\Typo3User\UserGroupRepositoryInterface
	 */
	protected $usergroupRepository;
	
	/**
	 *
	 * @var \NormanSeibert\Ldap\Domain\Model\LdapServer\Server 
	 */
	protected $ldapServer;
	
	/**
	 * @var \NormanSeibert\Ldap\Domain\Model\Configuration\Configuration
	 * @inject
	 */
	protected $ldapConfig;
	
	/**
	 *
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 */
	protected $objectManager;
	
	/**
	 *
	 * @var \NormanSeibert\Ldap\Domain\Model\Typo3User\UserInterface
	 */
	protected $user;

    /**
     *
     * @var \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationUsers
     */
	protected $userRules;

    /**
     * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
     */
    protected $cObj;

    /**
     * @var boolean
     */
    protected $importGroups;

    /**
     * @var string
     */
    protected $userObject;

    /**
     * @var string
     */
    protected $groupObject;

    /**
	 * 
	 */
	public function __construct() {
		$this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
		$this->cObj = $this->objectManager->get('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
		// $this->initializeRequiredTsfeParts();
		$this->importGroups = 1;
	}

	/**
	 * @return void
	 */
	protected function initializeRequiredTsfeParts() {
		/*
		if (!isset($GLOBALS['TSFE']) || empty($GLOBALS['TSFE']->sys_page)) {
			$GLOBALS['TSFE']->sys_page = $this->objectManager->get('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
		}
		if (!isset($GLOBALS['TSFE']) || empty($GLOBALS['TSFE']->tmpl)) {
			$GLOBALS['TSFE']->tmpl = $this->objectManager->get('TYPO3\\CMS\\Core\\TypoScript\\ExtendedTemplateService');
		}
		*/
		if (!isset($GLOBALS['TSFE']) || (empty($GLOBALS['TSFE']->csConvObj)))	{
			$GLOBALS['TSFE']->csConvObj = $this->objectManager->get('TYPO3\\CMS\\Core\\Charset\\CharsetConverter');
		}
	}
	
	/**
	 * 
	 * @param \NormanSeibert\Ldap\Domain\Model\Typo3User\UserInterface $user
	 */
	public function setUser(\NormanSeibert\Ldap\Domain\Model\Typo3User\UserInterface $user) {
		$this->user = $user;
	}
	
	/**
	 * 
	 * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\UserInterface
	 */
	public function getUser() {
		return $this->user;
	}
	
	/**
	 * 
	 * @param string $dn
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapUser\User
	 */
	public function setDN($dn) {
		$this->dn = $dn;
		return $this;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function getDN() {
		return $this->dn;
	}
	
	/**
	 * 
	 * @param array $attrs
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapUser\User
	 */
	public function setAttributes($attrs) {
		$this->attributes = $attrs;
		return $this;
	}
	
	/**
	 * 
	 * @return array
	 */
	public function getAttributes() {
		return $this->attributes;
	}
	
	/**
	 * 
	 * @param string $attr
	 * @param string $value
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapUser\User
	 */
	public function setAttribute($attr, $value) {
		$this->attributes[$attr] = $value;
		return $this;
	}
	
	/**
	 * 
	 * @param string $attr
	 * @return array
	 */
	public function getAttribute($attr) {
		return $this->attributes[$attr];
	}
	
	/** 
	 * Tries to load the TYPO3 user based on DN or username
	 * 
	 */
	public function loadUser() {
		$pid = $this->userRules->getPid();
		$msg = 'Search for user record with DN = ' . $this->dn . ' in page ' . $pid;
		if ($this->ldapConfig->logLevel == 2) {
			\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 0);
		}
		// search for DN
        /* @var $user \NormanSeibert\Ldap\Domain\Model\Typo3User\UserInterface */
		$user = $this->userRepository->findByDn($this->dn, $pid);
		// search for Username if no record with DN found
		if (is_object($user)) {
			$msg = 'User record already existing: ' . $user->getUid();
			if ($this->ldapConfig->logLevel == 2) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 0);
			}
		} else {
			$mapping = $this->userRules->getMapping();
			$rawLdapUsername = $mapping['username.']['data'];
			$ldapUsername = str_replace('field:', '', $rawLdapUsername);
			$username = $this->getAttribute($ldapUsername);
			$user = $this->userRepository->findByUsername($username, $pid);
			if (is_object($user)) {
				$msg = 'User record already existing: ' . $user->getUid();
				if ($this->ldapConfig->logLevel == 2) {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 0);
				}
			}
		}
		$this->user = $user;
	}
	
	/**
	 * adds a new TYPO3 user
	 * 
	 * @param string $lastRun
	 */
	public function addUser($lastRun = NULL) {
		$mapping = $this->userRules->getMapping();
		$rawLdapUsername = $mapping['username.']['data'];
		$ldapUsername = str_replace('field:', '', $rawLdapUsername);

		$username = $this->getAttribute($ldapUsername);

		if ($username) {
			$this->user = $this->objectManager->get($this->userObject);
			$this->user->setLdapServer($this->ldapServer);
			$this->user->setPid($this->pid);
			$this->user->setUsername($username);
			$this->user->setDN($this->dn);
			$this->user->generatePassword();
			
			// LDAP attributes from mapping
			$insertArray = $this->mapAttributes();
			foreach ($insertArray as $field => $value) {
				$ret = $this->user->_setProperty($field, $value);
				if (!$ret) {
					$msg = 'Property "' . $field . '" is unknown to Extbase.';
					if ($this->ldapConfig->logLevel == 2) {
						\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 0);
					}
				}
			}
			
			if ($lastRun) {
				$this->user->setLastRun($lastRun);
			}
			
			$numberOfGroups = $this->addUsergroupsToUserRecord($lastRun);

			if (($numberOfGroups == 0) && ($this->userRules->getOnlyUsersWithGroup())) {
				$msg = 'User "' . $username . '" (DN: ' . $this->dn . ') not imported due to missing usergroup';
				if ($this->ldapConfig->logLevel == 2) {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 0);
				}
			} else {
				$this->userRepository->add($this->user);
				
				$msg = 'Create user record "' . $username . '" (DN: ' . $this->dn . ')';
				if ($this->ldapConfig->logLevel == 2) {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 0);
				}
			}
		} else {
			// error condition. There should always be a username
			$msg = 'No username (Server: ' . $this->ldapServer->getConfiguration()->getUid() . ', DN: ' . $this->dn . ')';
			if ($this->ldapConfig->logLevel == 2) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 2);
			}
			\NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING, $msg, $this->ldapServer->getConfiguration()->getUid());
		}
	}
	
	/** 
	 * updates a TYPO3 user
	 * 
	 * @param string $lastRun
	 */
	public function updateUser($lastRun = NULL) {
		$mapping = $this->userRules->getMapping();
		$rawLdapUsername = $mapping['username.']['data'];
		$ldapUsername = str_replace('field:', '', $rawLdapUsername);

		$username = $this->getAttribute($ldapUsername);

		if ($username) {
			$this->user->setPid($this->pid);
			$this->user->setUsername($username);
			$this->user->setLdapServer($this->ldapServer);
			$this->user->setDN($this->dn);
			
			// LDAP attributes from mapping
			$insertArray = $this->mapAttributes();
			foreach ($insertArray as $field => $value) {
				$ret = $this->user->_setProperty($field, $value);
				if (!$ret) {
					$msg = 'Property "' . $field . '" is unknown to Extbase.';
					if ($this->ldapConfig->logLevel == 2) {
						\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 0);
					}
				}
			}
			
			if ($lastRun) {
				$this->user->setLastRun($lastRun);
			}
			
			$this->removeUsergroupsFromUserRecord();
			$numberOfGroups = $this->addUsergroupsToUserRecord($lastRun);

			if (($numberOfGroups == 0) && ($this->userRules->getOnlyUsersWithGroup())) {
				$msg = 'User "' . $username . '" (DN: ' . $this->dn . ') not updated due to missing usergroup';
				if ($this->ldapConfig->logLevel == 2) {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 0);
				}
			} else {
				$this->userRepository->update($this->user);
				
				$msg = 'Update user record "' . $username;
				if ($this->ldapConfig->logLevel == 2) {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 0);
				}
			}
		} else {
			// error condition. There should always be a username
			$msg = 'No username (Server: ' . $this->ldapServer->getConfiguration()->getUid() . ', DN: ' . $this->dn . ')';
			if ($this->ldapConfig->logLevel == 2) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 2);
			}
			\NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING, $msg, $this->ldapServer->getConfiguration()->getUid());
		}
	}
	
	/** Maps attributes from LDAP record to TYPO3 DB fields
	 * 
	 * @param string $mappingType
	 * @param array $useAttributes
	 * @return array
	 */
	protected function mapAttributes($mappingType = 'user', $useAttributes = array()) {
		$insertArray = array();
		if ($mappingType == 'group') {
			$mapping = $this->userRules->getGroupRules()->getMapping();
			$attributes = $useAttributes;
			// print_r ($mapping); die;
		} else {
			$mapping = $this->userRules->getMapping();
			$attributes = $this->attributes;
		}

		if (is_array($mapping)) {
			foreach ($mapping as $key => $value) {
				if ($key != 'username.') {
					$stdWrap = $value['stdWrap.'];
					if (is_array($value['stdWrap.'])) {
						unset($value['stdWrap.']);
					}
					$this->cObj->alternativeData = $attributes;
					$result = $this->cObj->stdWrap($value['value'], $value);
					if (substr($key, strlen($key) - 1, 1) == '.') {
						$key = substr($key, 0, strlen($key) - 1);
					}
					if (is_array($result)) {
						unset($result['count']);
						$attr = array();
						foreach ($result as $v) {
							$attr[] = $this->cObj->stdWrap($v, $stdWrap);
						}
						$result = implode(', ', $attr);
					} else {
						$result = $this->cObj->stdWrap($result, $stdWrap);
					}
					$insertArray[$key] = $result;
				}
			}
		}
		
		return $insertArray;
	}
	
	/** 
	 * enables a TYPO3 user
	 */
	public function enableUser() {
		$this->user->setIsDisabled(FALSE);
		$this->userRepository->update($this->user);
	}

	/**
	 * adds TYPO3 usergroups to the user record
	 * 
	 * @param string $lastRun
	 * @return integer
	 */
	protected function addUsergroupsToUserRecord($lastRun = NULL) {
		if (is_object($this->userRules->getGroupRules())) {
			$assignedGroups = $this->assignGroups($lastRun);
			if ($this->userRules->getGroupRules()->getAddToGroups()) {
				$addToGroups = $this->userRules->getGroupRules()->getAddToGroups();
				$groupsToAdd = $this->usergroupRepository->findByUids(explode(',', $addToGroups));
				$usergroups = array_merge($assignedGroups, $groupsToAdd);
			} else {
				$usergroups = $assignedGroups;
			}
			if (count($usergroups) > 0) {
				foreach ($usergroups as $group) {
					$this->user->addUsergroup($group);
				}
			} else {
				if ($this->ldapConfig->logLevel == 2) {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('User has no LDAP usergroups: ' . $this->user->getUsername(), 'ldap', 0);
				}
			}
		}

		return count($usergroups);
	}
	
	/** Assigns TYPO3 usergroups to the current TYPO3 user
	 * 
	 * @param string $lastRun
	 * @return array
	 */
	protected function assignGroups($lastRun = NULL) {
        $ret = array();
		$mapping = $this->userRules->getGroupRules()->getMapping();

		if (is_array($mapping)) {
			if ($this->userRules->getGroupRules()->getReverseMapping()) {
				$ret = $this->reverseAssignGroups();
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
			$ret = array(
				'newGroups' => array(),
				'existingGroups' => array()
			);
		}
		
		$assignedGroups = $this->addNewGroups($ret['newGroups'], $ret['existingGroups'], $lastRun);

		return $assignedGroups;
	}
	
	/** Assigns TYPO3 usergroups to the current TYPO3 user by additionally querying the LDAP server for groups
	 *
	 * @return array
	 */
	private function reverseAssignGroups() {
		$ret = array();
		$mapping = $this->userRules->getGroupRules()->getMapping();
		$searchAttribute = $this->userRules->getGroupRules()->getSearchAttribute();
		
		if ($searchAttribute) {
			$groupname = mb_strtolower($this->getAttribute('dn'));
		} else {
			$searchAttribute = 'dn';
		}

		$groupname = mb_strtolower($this->getAttribute($searchAttribute));
		
		$ldapGroups = $this->ldapServer->getGroups($groupname);
		
		foreach ($ldapGroups as $group) {
			$this->cObj->alternativeData = $group;
			$usergroup = $this->cObj->stdWrap('', $mapping['title.']);
			$tmp = $this->resolveGroup('title', $usergroup, $usergroup, $group['dn']);
			if ($tmp['newGroup']) {
				$ret['newGroups'][] = $tmp['newGroup'];
			}
			if ($tmp['existingGroup']) {
				$ret['existingGroups'][] = $tmp['existingGroup'];
			}
		}
		
		// $assignedGroups = $this->addNewGroups($ret['newGroups'], $ret['existingGroups'], $lastRun);

		return $ret;
	}
	
	/** Determines usergroups based on a text attribute
	 *
	 * @return array
	 */
	private function assignGroupsText() {
		$ret = array();
		$mapping = $this->userRules->getGroupRules()->getMapping();
		
		$this->cObj->alternativeData = $this->attributes;
		$result = $this->cObj->stdWrap('', $mapping['title.']);
		if (substr($key, strlen($key) - 1, 1) == '.') {
			$key = substr($key, 0, strlen($key) - 1);
		}
		if (is_array($result)) {
			unset($result['count']);
			$attr = array();
			foreach ($result as $v) {
				$attr[] = $this->cObj->stdWrap($v, $stdWrap);
			}
			$result = implode(', ', $attr);
		} else {
			$result = $this->cObj->stdWrap($result, $stdWrap);
		}
		$usergroups = $result;

		\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Usergroups', 'ldap', 3, $usergroups);

		// print_r($usergroups); die;
		
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

    /** Determines usergroups based on the user records parent record
     *
     * @internal param array $mapping
     * @return array
     */
	private function assignGroupsParent() {
		$ret = array();
		$mapping = $this->userRules->getGroupRules()->getMapping();
		
		$path = explode(',', $this->dn);
		unset($path[0]);
		$parentDN = implode(',', $path);
		$ldapGroup = $this->ldapServer->getGroup($parentDN);
		
		$this->cObj->alternativeData = $ldapGroup;
		$usergroup = $this->cObj->stdWrap('', $mapping['title.']);
		
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
	
	/** Determines usergroups based on the user record's DN
	 * 
	 * @return array
	 */
	private function assignGroupsDN() {
		$ret = array();
		$mapping = $this->userRules->getGroupRules()->getMapping();
		
		$this->cObj->alternativeData = $this->attributes;
		$groupDNs = $this->cObj->stdWrap('', $mapping['field.']);
		
		if (is_array($groupDNs)) {
			unset($groupDNs['count']);
			foreach ($groupDNs as $groupDN) {
				$ldapGroup = $this->ldapServer->getGroup($groupDN);
				if (is_array($ldapGroup)) {
					$this->cObj->alternativeData = $ldapGroup;
					$usergroup = $this->cObj->stdWrap('', $mapping['title.']);
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
			$this->cObj->alternativeData = $ldapGroup;
			$usergroup = $this->cObj->stdWrap('', $mapping['title.']);
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
	
	/**
	 * 
	 * @param string $attribute
	 * @param string $selector
	 * @param array $usergroup
	 * @param string $dn
	 * @param object $obj
	 * @return array
	 */
	private function resolveGroup($attribute, $selector, $usergroup, $dn = NULL, $obj = NULL) {
		$groupFound = FALSE;
		$resolvedGroup = FALSE;
		$newGroup = FALSE;

		$allGroups = $this->ldapServer->getAllGroups();
		foreach ($allGroups as $group) {
            /* @var $group \NormanSeibert\Ldap\Domain\Model\Typo3User\UserGroupInterface */
			$attrValue = $group->_getProperty($attribute);
			if ($selector == $attrValue) {
				$groupFound = $group;
			}
		}
		if (is_object($groupFound)) {
			if ($this->checkGroupMembership($groupFound->getTitle())) {
				$resolvedGroup = $groupFound;
			}
		} elseif ($usergroup) {
			if ($this->checkGroupMembership($usergroup)) {
				$newGroup = array(
					'title' => $usergroup,
					'dn' => $dn,
					'groupObject' => $obj
				);
			}
		}		
		$ret = array(
			'newGroup' => $newGroup,
			'existingGroup' => $resolvedGroup
		);
		
		return $ret;
	}
	
	/** Checks whether a usergroup is in the list of allowed groups
	 * 
	 * @param string $groupname
	 * @return boolean
	 */
	protected function checkGroupMembership($groupname) {
		$ret = FALSE;
		$onlygroup = $this->userRules->getGroupRules()->getRestrictToGroups();
		if (empty($onlygroup)) {
			$ret = TRUE;
		} else {
			$onlygrouparray = explode(",", $onlygroup);
			if (is_array($onlygrouparray)) {
				while ((list(, $value) = each($onlygrouparray)) && !($ret)) {
					if (preg_match(trim($value), $groupname)) $ret = TRUE;
				}
			}
			if ((!$ret) && ($this->ldapConfig->logLevel == 2)) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Filtered out: ' . $groupname, 'ldap', 0);
			}
		}

		return $ret;
	}
	
	/**
	 * removes usergroups from the user record
	 */
	protected function removeUsergroupsFromUserRecord() {
		$preserveNonLdapGroups = $this->userRules->getGroupRules()->getPreserveNonLdapGroups();
		if ($preserveNonLdapGroups) {
			$usergroups = $this->user->getUsergroup()->toArray();
			foreach ($usergroups as $group) {
				$extendedGroup = $this->usergroupRepository->findByUid($group->getUid());
				if ($extendedGroup->getServerUid()) {
					$this->user->removeUsergroup($group);
				}
			}
		} else {
            /* @var $usergroup \TYPO3\CMS\Extbase\Persistence\ObjectStorage */
			$usergroup = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\ObjectStorage');
			$this->user->setUsergroup($usergroup);
		}
	}
	
	/**
	 * adds a new TYPO3 usergroup
	 * 
	 * @param array $newGroups
	 * @param array $existingGroups
	 * @param string $lastRun
	 * @return array
	 */
	public function addNewGroups($newGroups, $existingGroups, $lastRun) {
		if (!is_array($existingGroups)) {
			$existingGroups = array();
		}
		$assignedGroups = $existingGroups;
		$addnewgroups = $this->userRules->getGroupRules()->getImportGroups();
		if ((is_array($newGroups)) && ($addnewgroups)) {
			foreach ($newGroups as $group) {
                /* @var $newGroup \NormanSeibert\Ldap\Domain\Model\Typo3User\UserGroupInterface */
				$newGroup = $this->objectManager->get($this->groupObject);
				$newGroup->setPid($this->pid);
				$newGroup->setTitle($group['title']);
				$newGroup->setDN($group['dn']);
				$newGroup->setLdapServer($this->ldapServer);
				if ($lastRun) {
					$newGroup->setLastRun($lastRun);
				}
				// LDAP attributes from mapping
				if ($group['groupObject']) {
                    /* @var $groupObject \NormanSeibert\Ldap\Domain\Model\LdapUser\User */
                    $groupObject = $group['groupObject'];
                    $insertArray = $this->mapAttributes('group', $groupObject->getAttributes());
					unset($insertArray['field']);
					foreach ($insertArray as $field => $value) {
						$ret = $newGroup->_setProperty($field, $value);
						if (!$ret) {
							$msg = 'Property "' . $field . '" is unknown to Extbase.';
							if ($this->ldapConfig->logLevel == 2) {
								\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 0);
							}
						}
					}
				}
				$this->usergroupRepository->add($newGroup);
				$assignedGroups[] = $newGroup;
				$this->ldapServer->addGroup($newGroup, $this->groupObject);
			}
		}
		
		return $assignedGroups;
	}
}
?>