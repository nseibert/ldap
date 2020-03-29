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
 * @copyright 2020 Norman Seibert
 */

use Psr\Log\LoggerAwareTrait;
use \NormanSeibert\Ldap\Domain\Model\Configuration\Configuration;
use \NormanSeibert\Ldap\Utility\ContentRendererLight;
use \TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Model for users read from LDAP server
 */
class User extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity implements \Psr\Log\LoggerAwareInterface {

	use LoggerAwareTrait;
	
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
	 * @var Configuration
	 */
	protected $ldapConfig;
	
	/**
	 *
	 * @var ObjectManager
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
     * @var ContentRendererLight
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
	    $this->ldapConfig = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\Configuration\\Configuration');
		$this->cObj = $this->objectManager->get('NormanSeibert\\Ldap\\Utility\\ContentRendererLight');
		$this->importGroups = 1;
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
		if ($this->ldapConfig->logLevel >= 2) {
			$this->logger->debug($msg);
		}
		// search for DN
        /* @var $user \NormanSeibert\Ldap\Domain\Model\Typo3User\UserInterface */
		$user = $this->userRepository->findByDn($this->dn, $pid);
		// search for Username if no record with DN found
		if (is_object($user)) {
			$msg = 'User record already existing: ' . $user->getUid();
			if ($this->ldapConfig->logLevel == 3) {
				$this->logger->debug($msg);
			}
		} else {
			$mapping = $this->userRules->getMapping();
			$rawLdapUsername = $mapping['username.']['data'];
			$ldapUsername = str_replace('field:', '', $rawLdapUsername);
			$username = $this->getAttribute($ldapUsername);
			$user = $this->userRepository->findByUsername($username, $pid);
			if (is_object($user)) {
				$msg = 'User record already existing: ' . $user->getUid();
				if ($this->ldapConfig->logLevel == 3) {
					$this->logger->debug($msg);
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

		$createUser = FALSE;

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

			if (($numberOfGroups == 0) && ($this->userRules->getOnlyUsersWithGroup())) {
				$msg = 'User "' . $username . '" (DN: ' . $this->dn . ') not imported due to missing usergroup';
				if ($this->ldapConfig->logLevel >= 1) {
					$this->logger->notice($msg);
				}
			} elseif ($this->userRules->getGroupRules()->getRestrictToGroups()) {
				$groupFound = FALSE;
				reset($usergroups);
				foreach ($usergroups as $group) {
					$groupFound = $this->checkGroupMembership($group->getTitle());
					if ($groupFound) {
						break;
					}
				}
				if ($groupFound) {
					$createUser = TRUE;
				} else {
					$msg = 'User "' . $username . '" (DN: ' . $this->dn . ') because no usergroup matches "' . $this->userRules->getGroupRules()->getRestrictToGroups() . '"';
					if ($this->ldapConfig->logLevel >= 1) {
						$this->logger->notice($msg);
					}
				}
			} else {
				$createUser = TRUE;
			}
		} else {
			// error condition. There should always be a username
			$msg = 'No username (Server: ' . $this->ldapServer->getConfiguration()->getUid() . ', DN: ' . $this->dn . ')';
			if ($this->ldapConfig->logLevel >= 1) {
				$this->logger->notice($msg);
			}
			\NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING, $msg, $this->ldapServer->getConfiguration()->getUid());
		}

		if ($createUser) {
			$this->userRepository->add($this->user);
			$msg = 'Create user record "' . $username . '" (DN: ' . $this->dn . ')';
			if ($this->ldapConfig->logLevel >= 3) {
				$debugData = (array) $this->user;
				$this->logger->debug($msg, $debugData);
			}
			elseif ($this->ldapConfig->logLevel == 2) {
				$this->logger->debug($msg);
			}
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

		$updateUser = FALSE;

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

			if (($numberOfGroups == 0) && ($this->userRules->getOnlyUsersWithGroup())) {
				$msg = 'User "' . $username . '" (DN: ' . $this->dn . ') not updated due to missing usergroup';
				if ($this->ldapConfig->logLevel >= 1) {
					$this->logger->notice($msg);
				}
			} elseif ($this->userRules->getGroupRules()->getRestrictToGroups()) {
				$groupFound = FALSE;
				reset($usergroups);
				foreach ($usergroups as $group) {
					$groupFound = $this->checkGroupMembership($group->getTitle());
					if ($groupFound) {
						break;
					}
				}
				if ($groupFound) {
					$updateUser = TRUE;
				} else {
					$msg = 'User "' . $username . '" (DN: ' . $this->dn . ') because no usergroup matches "' . $this->userRules->getGroupRules()->getRestrictToGroups() . '"';
					if ($this->ldapConfig->logLevel >= 1) {
						$this->logger->notice($msg);
					}
				}
			} else {
				$updateUser = TRUE;
			}
		} else {
			// error condition. There should always be a username
			$msg = 'No username (Server: ' . $this->ldapServer->getConfiguration()->getUid() . ', DN: ' . $this->dn . ')';
			if ($this->ldapConfig->logLevel >= 1) {
				$this->logger->warning($msg);
			}
			\NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING, $msg, $this->ldapServer->getConfiguration()->getUid());
		}

		if ($updateUser) {
			$this->userRepository->update($this->user);
			$msg = 'Update user record "' . $username . '" (DN: ' . $this->dn . ')';
			if ($this->ldapConfig->logLevel >= 3) {
				$debugData = (array) $this->user;
				$this->logger->debug($msg, $debugData);
			}
			elseif ($this->ldapConfig->logLevel == 2) {
				$this->logger->debug($msg);
			}
		}
	}


	/** Retrieves a single attribute from LDAP record
	 * 
	 * @param array $mapping
	 * @param string $key
	 * @param array $data
	 * @return array
	 */
	protected function getAttributeMapping($mapping, $key, $data) {
		// stdWrap does no longer handle arrays, therefore we have to check and map manually
		// values derived from LDAP attribues
		$tmp = explode(':', $mapping[$key . '.']['data']);
		if (is_array($tmp)) {
			$attrName = $tmp[1];
			$ldapData = $data[$attrName];

			$msg = 'Mapping attributes';
			$logArray = array(
				'Key' => $key,
				'Rules' => $mapping,
				'Data' => $data
			);
			if ($this->ldapConfig->logLevel == 3) {
				$this->logger->debug($msg, $logArray);
			}
		}

		return $ldapData;
	}

	
	/** Maps a single attribute from LDAP record to TYPO3 DB fields
	 * 
	 * @param array $mapping
	 * @param string $key
	 * @param array $data
	 * @return string
	 */
	protected function mapAttribute($mapping, $key, $data) {
		$ldapData = $this->getAttributeMapping($mapping, $key, $data);

		$stdWrap = $mapping[$key . '.']['stdWrap.'];
		if (is_array($value['stdWrap.'])) {
			unset($value['stdWrap.']);
		}

		if (is_array($ldapData)) {
			unset($ldapData['count']);
			$ldapDataList = implode(',', $ldapData);
			$result = $this->cObj->stdWrap($ldapDataList, $stdWrap);
		} else {
			$result = $this->cObj->stdWrap($ldapData, $stdWrap);
		}

		$msg = 'Mapping for attribute "' . $key . '"';
		$logArray = array(
			'LDAP attribute value' => $ldapData,
			'Mapping result' => $result
		);
		if ($this->ldapConfig->logLevel == 3) {
			$this->logger->debug($msg, $logArray);
		}
		// static values, overwrite those from LDAP if set
		$tmp = $mapping[$key . '.']['value'];
		if ($tmp) {
			$result = $tmp;
			$msg = 'Setting attribute "' . $key . '" to: ' . $result;
			if ($this->ldapConfig->logLevel == 3) {
				$this->logger->debug($msg);
			}
		}

		return $result;
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
		} else {
			$mapping = $this->userRules->getMapping();
			$attributes = $this->attributes;
		}
		if (is_array($mapping)) {
			$msg = 'Mapping attributes';
			$logArray = array(
				'Type' => $mappingType,
				'Rules' => $mapping,
				'Data' => $attributes
			);
			if ($this->ldapConfig->logLevel == 3) {
				$this->logger->debug($msg, $logArray);
			}

			foreach ($mapping as $key => $value) {
				if ($key != 'username.') {
					if (substr($key, strlen($key) - 1, 1) == '.') {
						$key = substr($key, 0, strlen($key) - 1);
					}
					$result = $this->mapAttribute($mapping, $key, $attributes);
					$insertArray[$key] = $result;
				}
			}
		} else {
			$msg = 'No mapping rules found for type "' . $mappingType . '"';
			if ($this->ldapConfig->logLevel >= 2) {
				$this->logger->notice($msg);
			}
		}

		$msg = 'Mapped values to insert into or update to DB';
		if ($this->ldapConfig->logLevel == 3) {
			$this->logger->debug($msg, $insertArray);
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
	 * @return array
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
					if ($this->ldapConfig->logLevel == 3) {
						$msg = 'Add usergroup to user record "' . $this->user->getUsername() . '": ' . $group->getTitle();
						$this->logger->debug($msg);
					}
				}
			} else {
				if ($this->ldapConfig->logLevel == 3) {
					$msg = 'User has no LDAP usergroups: ' . $this->user->getUsername();
					$this->logger->notice($msg);
				}
			}
		}

		return $usergroups;
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

			$msg = 'No mapping for usergroups found';
			if ($this->ldapConfig->logLevel >= 2) {
				$this->logger->notice($msg);
			}
		}
		
		$assignedGroups = $this->addNewGroups($ret['newGroups'], $ret['existingGroups'], $lastRun);

		return $assignedGroups;
	}
	
	/** Assigns TYPO3 usergroups to the current TYPO3 user by additionally querying the LDAP server for groups
	 *
	 * @return array
	 */
	private function reverseAssignGroups() {
		$msg = 'Use reverse mapping for usergroups';
		if ($this->ldapConfig->logLevel == 3) {
			$this->logger->debug($msg);
		}

		$ret = array();
		$mapping = $this->userRules->getGroupRules()->getMapping();
		$searchAttribute = $this->userRules->getGroupRules()->getSearchAttribute();
		
		if (!$searchAttribute) {
			$searchAttribute = 'dn';
		}

		$username = mb_strtolower($this->getAttribute($searchAttribute));

		$ldapGroups = $this->ldapServer->getGroups($username);

		if (is_array($ldapGroups)) {
			unset($ldapGroups['count']);
			if (count($ldapGroups) == 0) {
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
				if ($this->ldapConfig->logLevel == 3) {
					$this->logger->debug($msg, $ldapGroups);
				}
				foreach ($ldapGroups as $group) {
					// $this->cObj->alternativeData = $group;
					// $usergroup = $this->cObj->stdWrap('', $mapping['title.']);
					$usergroup = $this->mapAttribute($mapping, 'title', $group);

					$msg = 'Try to add usergroup "' . $usergroup . '" to user';
					if ($this->ldapConfig->logLevel == 3) {
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
						$msg = 'Usergroup mapping did not deliver a title';
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
		if ($this->ldapConfig->logLevel == 3) {
			$this->logger->debug($msg, $ret);
		}

		return $ret;
	}
	
	/** Determines usergroups based on a text attribute
	 *
	 * @return array
	 */
	private function assignGroupsText() {
		$msg = 'Use text based mapping for usergroups';
		if ($this->ldapConfig->logLevel == 3) {
			$this->logger->debug($msg);
		}
		$ret = array();
		$mapping = $this->userRules->getGroupRules()->getMapping();
		
		// $this->cObj->alternativeData = $this->attributes;
		// $result = $this->cObj->stdWrap('', $mapping['title.']);
		$result = $this->getAttributeMapping($mapping, 'title', $this->attributes);
		
		if (is_array($result)) {
			unset($result['count']);
			$attr = array();
			foreach ($result as $v) {
				$attr[] = $this->cObj->stdWrap($v, $stdWrap);
			}
			$result = $attr;
		} elseif ($result == 'Array') {
			$tmp = explode(':', $mapping['title.']['data']);
			$attrname = $tmp[1];
			$result = $this->attributes[$attrname];
			unset($result['count']);
			$attr = array();
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

    /** Determines usergroups based on the user records parent record
     *
     * @internal param array $mapping
     * @return array
     */
	private function assignGroupsParent() {
		$msg = 'Use parent node for usergroup';
		if ($this->ldapConfig->logLevel == 3) {
			$this->logger->debug($msg);
		}
		$ret = array();
		$mapping = $this->userRules->getGroupRules()->getMapping();
		
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
	
	/** Determines usergroups based on DNs in an attribute of the user's record
	 * 
	 * @return array
	 */
	private function assignGroupsDN() {
		$msg = 'Find usergroup DNs in user attribute for mapping';
		if ($this->ldapConfig->logLevel == 3) {
			$this->logger->debug($msg);
		}
		$ret = array();
		$mapping = $this->userRules->getGroupRules()->getMapping();
		
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
	
	/**
	 * 
	 * @param string $attribute
	 * @param string $selector
	 * @param string $usergroup
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
				$logArray = array();
				foreach ($onlygrouparray as $value) {
					$regExResult = preg_match(trim($value), $groupname);
					if ($regExResult) $ret = TRUE;
					$logArray[$groupname] = $regExResult;
					if ($ret) {
						break;
					}
				}
			}
			if ((!$ret) && ($this->ldapConfig->logLevel == 3)) {
				$msg = 'Filtered out: ' . $groupname;
				$this->logger->debug($msg, $logArray);
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
					/* @var $usergroup \TYPO3\CMS\Extbase\Persistence\ObjectStorage */
					$usergroup = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\ObjectStorage');
					$this->user->setUsergroup($usergroup);
				}
			} else {
				/* @var $usergroup \TYPO3\CMS\Extbase\Persistence\ObjectStorage */
				$usergroup = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\ObjectStorage');
				$this->user->setUsergroup($usergroup);
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

		$pid = $this->userRules->getGroupRules()->getPid();
		if (empty($pid)) {
			$pid = $this->userRules->getPid();
		}
		if (empty($pid)) {
			$pid = 0;
		}

		if ((is_array($newGroups)) && ($addnewgroups)) {
			foreach ($newGroups as $group) {
                /* @var $newGroup \NormanSeibert\Ldap\Domain\Model\Typo3User\UserGroupInterface */
				$newGroup = $this->objectManager->get($this->groupObject);
				$newGroup->setPid($pid);
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
                    $insertArray = $this->getAttributes('group', $groupObject->getAttributes());
					unset($insertArray['field']);
					foreach ($insertArray as $field => $value) {
						$ret = $newGroup->_setProperty($field, $value);
						if (!$ret) {
							$msg = 'Property "' . $field . '" is unknown to Extbase.';
							if ($this->ldapConfig->logLevel >= 2) {
								$this->logger->warning($msg);
							}
						}
					}
				}
				$this->usergroupRepository->add($newGroup);
				$msg = 'Insert user group "' . $group['title'] . ')';
				if ($this->ldapConfig->logLevel >= 3) {
					$debugData = (array) $newGroup;
					$this->logger->debug($msg, $debugData);
				}
				elseif ($this->ldapConfig->logLevel == 2) {
					$this->logger->debug($msg);
				}
				$assignedGroups[] = $newGroup;
				$this->ldapServer->addGroup($newGroup, $this->groupObject);
			}
		}
		
		return $assignedGroups;
	}
}
?>
