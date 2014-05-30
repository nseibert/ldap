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
 * @copyright 2013 Norman Seibert
 */

/**
 * Model for TYPO3 frontend users
 */
class FrontendUser extends \TYPO3\CMS\Extbase\Domain\Model\FrontendUser implements \NormanSeibert\Ldap\Domain\Model\Typo3User\UserInterface {
	
	/**
	 * @var boolean
	 */
	protected $isDisabled = FALSE;
	
	/**
	 * @var string 
	 */
	protected $dn;
	
	/**
	 * @var \NormanSeibert\Ldap\Domain\Model\LdapServer\Server 
	 */
	protected $ldapServer;
	
	/**
	 * @var string 
	 */
	protected $serverUid;
	
	/**
	 *
	 * @var string 
	 */
	protected $lastRun;
	
	/**
	 * @var \NormanSeibert\Ldap\Domain\Model\Configuration\Configuration
	 * @inject
	 */
	protected $ldapConfig;
	
	/**
	 * Checks whether this user is disabled.
	 *
	 * @return boolean whether this user is disabled
	 */
	public function getIsDisabled() {
		return $this->isDisabled;
	}

	/**
	 * Sets whether this user is disabled.
	 *
	 * @param boolean $isDisabled whether this user is disabled
	 * @return void
	 */
	public function setIsDisabled($isDisabled) {
		$this->isDisabled = $isDisabled;
	}
	
	/**
	 * 
	 * @param string $dn
	 * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser
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
	 * @param \NormanSeibert\Ldap\Domain\Model\LdapServer\Server $server
	 * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser
	 */
	public function setLdapServer(\NormanSeibert\Ldap\Domain\Model\LdapServer\Server $server) {
		$this->ldapServer = $server;
		$this->serverUid = $server->getConfiguration()->getUid();
		return $this;
	}
	
	/**
	 * 
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
	 */
	public function getLdapServer() {
		if (!is_object($this->ldapServer) && $this->serverUid) {
			$this->ldapServer = $this->ldapConfig->getLdapServer($this->serverUid.'.');
		}
		return $this->ldapServer;
	}
	
	/**
	 * 
	 * @param string $uid
	 * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser
	 */
	public function setServerUid($uid) {
		$this->serverUid = $uid;
		return $this;
	}
	
	/**
	 * 
	 * @return string
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
		if ($this->dn && $this->ldapServer) {
			$user = $this->getLdapServer()->getUser($this->dn);
		}
		return $user;
	}
	
	/**
	 * 
	 * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser
	 */
	public function generatePassword() {
		$password = \NormanSeibert\Ldap\Utility\Helpers::generatePassword(10, 2, 2, 2);
		if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('saltedpasswords')) {
			if (\TYPO3\CMS\Saltedpasswords\Utility\SaltedPasswordsUtility::isUsageEnabled('FE')) {
				$objSalt = \TYPO3\CMS\Saltedpasswords\Salt\SaltFactory::getSaltingInstance(NULL);
				if (is_object($objSalt)) {
					$password = $objSalt->getHashedPassword($password);
				}
			}
		}
		$this->password = $password;
		return $this;
	}
	
	/**
	 * 
	 * @param string $run
	 * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser
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
}
?>