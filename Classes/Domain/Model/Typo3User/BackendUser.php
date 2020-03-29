<?php
namespace NormanSeibert\Ldap\Domain\Model\Typo3User;
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

use \NormanSeibert\Ldap\Domain\Model\Configuration\Configuration;
use \TYPO3\CMS\Core\Utility\GeneralUtility;
use \TYPO3\CMS\Core\Crypto\Random;
use \TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;


/**
 * Model for TYPO3 backend users
 */
class BackendUser extends \TYPO3\CMS\Extbase\Domain\Model\BackendUser implements \NormanSeibert\Ldap\Domain\Model\Typo3User\UserInterface {
	
	/**
	 *
	 * @var string 
	 */
	protected $username;

	/**
	 *
	 * @var string 
	 */
	protected $dn;
	
	/**
	 *
	 * @var \NormanSeibert\Ldap\Domain\Model\LdapServer\Server 
	 */
	protected $ldapServer;
	
	/**
	 *
	 * @var int 
	 */
	protected $serverUid;

	/**
	 * @var string
	 */
	protected $password = '';
	
	/**
	 *
	 * @var string 
	 */
	protected $lastRun;
	
	/**
	 * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\BackendUserGroup>
	 */
	protected $usergroup;

	/**
	 *
	 * @var string 
	 */
	protected $databaseMounts;

	/**
	 *
	 * @var string 
	 */
	protected $fileMounts;

	/**
	 *
	 * @var string 
	 */
	protected $fileOperationPermissions;

	/**
	 *
	 * @var int 
	 */
	protected $options;

	/**
	 * 
	 */
	public function __construct() {
	    $this->usergroup = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
	}
	
	/**
	 * 
	 * @param string $dn
	 * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser
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
	 * @param int $uid
	 * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser
	 */
	public function setServerUid($uid) {
		$this->serverUid = $uid;
		return $this;
	}
	
	/**
	 * 
	 * @return int
	 */
	public function getServerUid() {
		return $this->serverUid;
	}
	
	/**
	 * 
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapUser\User
	 */
	public function getLdapUser() {
		$user = false;
		if ($this->dn && $this->serverUid) {
			$ldapConfig = GeneralUtility::makeInstance(Configuration::class);
			$server = $ldapConfig->getLdapServer($this->serverUid);
			$user = $server->getUser($this->dn);
		}
		return $user;
	}
	
	/**
	 * 
	 * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser
	 */
	public function generatePassword() {
		$password = GeneralUtility::makeInstance(Random::class)->generateRandomHexString(20);
		$hashInstance = GeneralUtility::makeInstance(PasswordHashFactory::class)->getDefaultHashInstance('BE');
		$hashedPassword = $hashInstance->getHashedPassword($password);
		$this->password = $hashedPassword;
		return $this;
	}
	
	/**
	 * 
	 * @param string $run
	 * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser
	 */
	public function setLastRun($run) {
		$this->lastRun = $run;
		return $this;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function getLastRun() {
		return $this->lastRun;
	}

	/**
	 * Gets the user name.
	 *
	 * @return string the user name, will not be empty
	 */
	public function getUsername() {
		return $this->userName;
	}

	/**
	 * Sets the user name.
	 *
	 * @param string $username the user name to set, must not be empty
	 * @return void
	 */
	public function setUsername($username) {
		$this->userName = $username;
	}
	
	/**
	 * Adds a usergroup to the backend user
	 *
	 * @param \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUserGroup $usergroup
	 */
	public function addUsergroup(\NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUserGroup $usergroup) {
		$this->usergroup->attach($usergroup);
	}

	/**
	 * Removes a usergroup from the backend user
	 *
	 * @param \TYPO3\CMS\Extbase\Domain\Model\BackendUserGroup $usergroup
	 */
	public function removeUsergroup(\TYPO3\CMS\Extbase\Domain\Model\BackendUserGroup $usergroup) {
		$this->usergroup->detach($usergroup);
	}
	
	/**
	 * Returns the usergroups. Keep in mind that the property is called "usergroup"
	 * although it can hold several usergroups.
	 *
	 * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage An object storage containing the usergroup
	 */
	public function getUsergroup() {
		return $this->usergroup;
	}
	
	/**
	 * Sets the usergroups. Keep in mind that the property is called "usergroup"
	 * although it can hold several usergroups.
	 *
	 * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage $usergroup
	 * @return void
	 * @api
	 */
	public function setUsergroup(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $usergroup) {
		$this->usergroup = $usergroup;
	}
	
	/**
	 * 
	 * @param string $mounts
	 * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser
	 */
	public function setDatabaseMounts($mounts) {
		$this->databaseMounts = $mounts;
		return $this;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function getDatabaseMounts() {
		return $this->databaseMounts;
	}
	
	/**
	 * 
	 * @param string $mounts
	 * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser
	 */
	public function setFileMounts($mounts) {
		$this->fileMounts = $mounts;
		return $this;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function getFileMounts() {
		return $this->fileMounts;
	}
	
	/**
	 * 
	 * @param string $permissions
	 * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser
	 */
	public function setFileOperationPermissions($permissions) {
		$this->fileOperationPermissions = $permissions;
		return $this;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function getFileOperationPermissions() {
		return $this->fileOperationPermissions;
	}
	
	/**
	 * 
	 * @param string $mounts
	 * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser
	 */
	public function setOptions($options) {
		$this->options = $options;
		return $this;
	}
	
	/**
	 * 
	 * @return int
	 */
	public function getOptions() {
		return $this->options;
	}
}
?>