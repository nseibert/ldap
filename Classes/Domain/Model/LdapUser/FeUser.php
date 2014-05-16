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
class FeUser extends \NormanSeibert\Ldap\Domain\Model\LdapUser\User {
	
	/**
	 *
	 * @var \NormanSeibert\Ldap\Domain\Model\FrontendUser
	 */
	protected $user;
	
	/**
	 * @var \NormanSeibert\Ldap\Domain\Repository\FrontendUserRepository
	 * @inject
	 */
	protected $userRepository;
	
	/**
	 * @var \NormanSeibert\Ldap\Domain\Repository\FrontendUserGroupRepository
	 * @inject
	 */
	protected $usergroupRepository;
	
	/**
	 * @var \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationUsers
	 */
	protected $userRules;
	
	/**
	 * sets the LDAP server (backreference)
	 * 
	 * @param \NormanSeibert\Ldap\Domain\Model\LdapServer\Server $server
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapUser\BeUser
	 */
	public function setLdapServer(\NormanSeibert\Ldap\Domain\Model\LdapServer\Server $server) {
		$this->ldapServer = $server;
		$this->userRules = $this->ldapServer->getConfiguration()->getFeUserRules();
		
		// $callers = debug_backtrace();
		// \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('setLdapServer: '.$callers[1]['function'].', '.$callers[1]['line'], 'ldap', 2, $this->userRules->getMapping());
		
		return $this;
	}
	
	/**
	 * 
	 * @param \NormanSeibert\Ldap\Domain\Model\FrontendUser $user
	 */
	public function setUser(\NormanSeibert\Ldap\Domain\Model\FrontendUser $user) {
		$this->user = $user;
	}
	
	/**
	 * 
	 * @return \NormanSeibert\Ldap\Domain\Model\FrontendUser
	 */
	public function getUser() {
		return $this->user;
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
			$this->user = $this->objectManager->create('NormanSeibert\\Ldap\\Domain\\Model\\FrontendUser');
			
			$this->user->setPid($this->userRules->getPid());
			$this->user->setUsername($username);
			$this->user->setLdapServer($this->ldapServer);
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
			
			$this->addUsergroupsToUserRecord($lastRun);
			
			// \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($this->user);
			
			$this->userRepository->add($this->user);
			
			$msg = 'Create frontend user record "' . $username . '" (DN: ' . $this->dn . ')';
			if ($this->ldapConfig->logLevel == 2) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 0);
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
			$this->user->setPid($this->userRules->getPid());
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
			$this->addUsergroupsToUserRecord($lastRun);
			
			$this->userRepository->update($this->user);
			
			$msg = 'Update frontend user record: ' . $username;
			if ($this->ldapConfig->logLevel == 2) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 0);
			}
			
			// \TYPO3\CMS\Core\Utility\DebugUtility::var_dump($this->user);
			
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
	 * adds TYPO3 usergroups to the user record
	 * 
	 * @param string $lastRun
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
			if (count($usergroups) == 0) {
				$msg = 'User has no usergroup';
				if ($this->ldapConfig->logLevel == 2) {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 2);
				}
				\NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING, $msg, $this->ldapServer->getConfiguration()->getUid());
			} else {
				foreach ($usergroups as $group) {
					$this->user->addUsergroup($group);
				}
			}
		} else {
			$msg = 'User has no usergroup';
			if ($this->ldapConfig->logLevel == 2) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 2);
			}
			\NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING, $msg, $this->ldapServer->getConfiguration()->getUid());
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
		$assignedGroups = $existingGroups;
		$addnewgroups = $this->userRules->getGroupRules()->getImportGroups();
		
		if ((is_array($newGroups)) && ($addnewgroups)) {
			foreach ($newGroups as $group) {
			
				$pid = $this->userRules->getPid();
				$newGroup = $this->objectManager->create('NormanSeibert\\Ldap\\Domain\\Model\\FrontendUserGroup');
				$newGroup->setPid($pid);
				$newGroup->setTitle($group['title']);
				
				$newGroup->setDN($group['dn']);
				$newGroup->setLdapServer($this->ldapServer);
				if ($lastRun) {
					$newGroup->setLastRun($lastRun);
				}
				// LDAP attributes from mapping
				if ($group['groupObject']) {
					$insertArray = $this->mapAttributes('group', $group['groupObject']->getAttributes());
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
				$this->ldapServer->addFeGroup($newGroup);
			}
		}
		
		return $assignedGroups;
	}
}
?>