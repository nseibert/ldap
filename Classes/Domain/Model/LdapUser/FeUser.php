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

use \NormanSeibert\Ldap\Domain\Repository\Typo3User\FrontendUserRepository;
use \NormanSeibert\Ldap\Domain\Repository\Typo3User\FrontendUserGroupRepository;
use \NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser;
use \NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUserGroup;

/**
 * Model for users read from LDAP server
 */
class FeUser extends \NormanSeibert\Ldap\Domain\Model\LdapUser\User {
	
	/**
	 * @var FrontendUser
	 */
	protected $user;
	
	/**
	 * @var FrontendUserRepository
	 */
	protected $userRepository;
	
	/**
	 * @var FrontendUserGroupRepository
	 */
	protected $usergroupRepository;
	
	/**
	 * @var \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationUsers
	 */
	protected $userRules;

	/**
	 * @var integer
	 */
	protected $pid;

    /**
	 * @param Configuration $ldapConfig
	 * @param ObjectManager $objectManager
	 * @param FrontendUserRepository $userRepository
	 * @param FrontendUserGroupRepository $usergroupRepository
	 * @param FrontendUser $userObject
	 * @param FrontendUserGroup $groupObject
	 */
	public function __construct(FrontendUserRepository $userRepository, FrontendUserGroupRepository $usergroupRepository, FrontendUser $userObject, FrontendUserGroup $groupObject) {
		parent::__construct();
		$this->userRepository = $userRepository;
		$this->usergroupRepository = $usergroupRepository;
		$this->userObject = $userObject;
		$this->groupObject = $groupObject;
	}
	
	/**
	 * sets the LDAP server (backreference)
	 * 
	 * @param \NormanSeibert\Ldap\Domain\Model\LdapServer\Server $server
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapUser\FeUser
	 */
	public function setLdapServer(\NormanSeibert\Ldap\Domain\Model\LdapServer\Server $server) {
		$this->ldapServer = $server;
		$this->userRules = $this->ldapServer->getConfiguration()->getFeUserRules();
		$this->pid = $this->userRules->getPid();
		return $this;
	}
}
?>