<?php
namespace NormanSeibert\Ldap\Domain\Model\LdapServer;
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
 * Model for the 'fe_users'/'be_users' sections in an LDAP server's configuraion
 */
class ServerConfigurationUsers extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity {
	
	/**
	 *
	 * @var string 
	 */
	protected $baseDN;
	
	/**
	 *
	 * @var string 
	 */
	protected $filter;
	
	/**
	 *
	 * @var boolean 
	 */
	protected $autoImport;
	
	/**
	 *
	 * @var boolean 
	 */
	protected $autoEnable;
	
	/**
	 *
	 * @var array 
	 */
	protected $mapping = array();
	
	/**
	 *
	 * @var int 
	 */
	protected $pid;
	
	/**
	 *
	 * @var \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups 
	 */
	protected $groupRules;
	
	/**
	 * 
	 * @param int $pid
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationUsers
	 */
	public function setPid($pid) {
		$this->pid = $pid;
		return $this;
	}
	
	/**
	 * 
	 * @return int
	 */
	public function getPid() {
		return $this->pid;
	}
	
	/**
	 * 
	 * @param string $baseDN
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationUsers
	 */
	public function setBaseDN($baseDN) {
		$this->baseDN = $baseDN;
		return $this;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function getBaseDN() {
		return $this->baseDN;
	}
	
	/**
	 * 
	 * @param string $filter
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationUsers
	 */
	public function setFilter($filter) {
		$this->filter = $filter;
		return $this;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function getFilter() {
		return $this->filter;
	}
	
	/**
	 * 
	 * @param boolean $auto
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationUsers
	 */
	public function setAutoImport($auto) {
		$this->autoImport = $auto;
		return $this;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function getAutoImport() {
		return $this->autoImport;
	}
	
	/**
	 * 
	 * @param boolean $auto
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationUsers
	 */
	public function setAutoEnable($auto) {
		$this->autoEnable = $auto;
		return $this;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function getAutoEnable() {
		return $this->autoEnable;
	}
	
	/**
	 * 
	 * @param array $mapping
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationUsers
	 */
	public function setMapping($mapping) {
		$this->mapping = $mapping;
		return $this;
	}
	
	/**
	 * 
	 * @return array
	 */
	public function getMapping() {
		return $this->mapping;
	}
	
	/**
	 * 
	 * @param \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups $rules
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationUsers
	 */
	public function setGroupRules(\NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups $rules) {
		$this->groupRules = $rules;
		return $this;
	}
	
	/**
	 * 
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups
	 */
	public function getGroupRules() {
		return $this->groupRules;
	}
}
?>