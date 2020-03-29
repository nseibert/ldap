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
 * @copyright 2020 Norman Seibert
 */

/**
 * Model for the 'usergroups' sections in an LDAP server's configuraion
 */
class ServerConfigurationGroups extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity {
	
	/**
	 *
	 * @var boolean
	 */
	protected $importGroups;
	
	/**
	 *
	 * @var string
	 */
	protected $addToGroups;
	
	/**
	 *
	 * @var string
	 */
	protected $restrictToGroups;
	
	/**
	 *
	 * @var boolean
	 */
	protected $preserveNonLdapGroups;
	
	/**
	 *
	 * @var boolean
	 */
	protected $reverseMapping;
	
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
	 * @var string
	 */
	protected $searchAttribute;
	
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
	 * @param string $baseDN
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups
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
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups
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
	 * @param string $filter
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups
	 */
	public function setSearchAttribute($attribute) {
		$this->searchAttribute = $attribute;
		return $this;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function getSearchAttribute() {
		return $this->searchAttribute;
	}
	
	/**
	 * 
	 * @param boolean $import
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups
	 */
	public function setImportGroups($import) {
		$this->importGroups = $import;
		return $this;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function getImportGroups() {
		return $this->importGroups;
	}
	
	/**
	 * 
	 * @param boolean $preserve
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups
	 */
	public function setPreserveNonLdapGroups($preserve) {
		$this->preserveNonLdapGroups = $preserve;
		return $this;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function getPreserveNonLdapGroups() {
		return $this->preserveNonLdapGroups;
	}
	
	/**
	 * 
	 * @param array $mapping
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups
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
	 * @param boolean $reverse
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups
	 */
	public function setReverseMapping($reverse) {
		$this->reverseMapping = $reverse;
		return $this;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function getReverseMapping() {
		return $this->reverseMapping;
	}
	
	/**
	 * 
	 * @param string $add
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups
	 */
	public function setAddToGroups($add) {
		$this->addToGroups = $add;
		return $this;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function getAddToGroups() {
		return $this->addToGroups;
	}
	
	/**
	 * 
	 * @param string $restrict
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups
	 */
	public function setRestrictToGroups($restrict) {
		$this->restrictToGroups = $restrict;
		return $this;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function getRestrictToGroups() {
		return $this->restrictToGroups;
	}
	
	/**
	 * 
	 * @param int $pid
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups
	 */
	public function setPid(int $pid): void {
		$this->pid = $pid;
	}
	
	/**
	 * 
	 * @return int
	 */
	public function getPid(): ?int {
		return $this->pid;
	}
}
?>