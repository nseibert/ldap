<?php
namespace NormanSeibert\Ldap\Domain\Model\Configuration;
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
 * Model for the extension's configuration of LDAP servsers
 */
class Configuration extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity implements \TYPO3\CMS\Core\SingletonInterface {

    /**
     *
     * @var array
     */
    protected $ldapServers;
	
	/**
	 *
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 * @inject
	 */
	protected $objectManager;
	
	/**
	 *
	 * @var array 
	 */
	public $config;
	
	/**
	 *
	 * @var int 
	 */
	public $logLevel;
	
	/**
	 * 
	 */
	public function __construct() {
		$this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
		$this->config = $this->loadConfiguration();
		$this->checkLdapExtension();
		$this->allLdapServers = $this->getLdapServersFromFile();
	}
	
	/**
	 * loads the extension configuration
	 * 
	 * @global array $TYPO3_CONF_VARS
	 * @return array
	 */
	private function loadConfiguration() {
		global $TYPO3_CONF_VARS;
		$conf = unserialize($TYPO3_CONF_VARS['EXT']['extConf']['ldap']);
		$this->logLevel = $conf['logLevel'];
		
		$ok = FALSE;
		if ($conf['configFile']) {
			$configFile = $conf['configFile'];
			if (file_exists($configFile) && is_file($configFile)) {
				$ok = TRUE;
			} else {
				$configFile = PATH_site.$conf['configFile'];
				if (file_exists($configFile) && is_file($configFile)) {
					$ok = TRUE;
				}
			}
			if ($ok) {
				$fileContent = \TYPO3\CMS\Core\Utility\GeneralUtility::getUrl($configFile);
				$tsParser = $this->objectManager->get('TYPO3\\CMS\\Core\\TypoScript\\Parser\\TypoScriptParser');
				$tsParser->parse($fileContent);
				
				if ($tsParser->error) {
					$msg = 'Mapping invalid.';
					if ($this->logLevel >= 1) {
						\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 3, $tsParser->error);
					}
					\NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR, $msg);
				} else {
					$this->ldapServers = $tsParser->setup['ldapServers.'];
					unset($tsParser->setup);
				}
			} else {
				$msg = 'Configuration file "' . $configFile . '" not found.';
				\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 3);
				\NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR, $msg);
			}
		} else {
			$msg = 'No configuration file set in extension settings (in extension manager)';
			\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 3);
			\NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR, $msg);
		}
		
		return $conf;
	}
	
	/**
	 * reads LDAP server definitions from configuration file
	 * 
	 * @return array
	 */
	private function getLdapServersFromFile() {
        $ldapServers = array();
		$allLdapServers = $this->ldapServers;
		if (count($allLdapServers) == 0) {
			$msg = 'No LDAP server found.';
			\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 2);
			\NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING, $msg);
		} else {
			foreach ($allLdapServers as $uid => $row) {
				$ldapServers[$uid] = $row;
				$ldapServers[$uid]['uid'] = rtrim($uid, '.');
			}
		}
		
		return $ldapServers;
	}
	
	/**
	 * loads LDAP server definitions
	 * 
	 * @param string $uid
	 * @param array $pid
	 * @param string $authenticate
	 * @param array $userPid
	 * @return array
	 */
	public function getLdapServers($uid = NULL, $pid = NULL, $authenticate = NULL, $userPid = NULL) {
		
		$ldapServers = array();
						
		if (is_array($this->allLdapServers)) {
			foreach ($this->allLdapServers as $serverUid => $server) {			
				$load = 1;
				if ($server['disable']) {
					$load = 0;
					$msg = 'LDAP server "'. $server['title'] .'" ignored: is disabled.';
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 1);
				}
				if ($load) {
					if ($pid && $server['pid']) {
						if (!\TYPO3\CMS\Core\Utility\GeneralUtility::inList($pid, $server['pid'])) {
							$load = 0;
							$pidList = '';
							if (is_array($pid)) {
								$pidList = implode(', ', $pid);
							} else {
								$pidList = $pid;
							}
							$msg = 'LDAP server "'. $server['title'] .'" ignored: does not match list of page uids ('. $pidList .').';
							\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 1);
						}
					}
					if ($userPid && $server['fe_users.']['pid']) {
						if (!\TYPO3\CMS\Core\Utility\GeneralUtility::inList($userPid, $server['fe_users.']['pid'])) {
							$load = 0;
							$pidList = '';
							if (is_array($userPid)) {
								$pidList = implode(', ', $userPid);
							} else {
								$pidList = $userPid;
							}
							$msg = 'LDAP server "'. $server['title'] .'" ignored: does not match list of page uids ('. $pidList .').';
							\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 1);
						}
					}
					if ($uid) {
						if ($server['uid']) {
							if ($server['uid'] != $uid) {
								$load = 0;
							}
						}
					}
					$server['authenticate'] = strtolower($server['authenticate']);
					$authenticate = strtolower($authenticate);
					if ($authenticate) {
						if ($server['authenticate'] && ($server['authenticate'] != $authenticate) && ($server['authenticate'] != 'both')) {
							$load = 0;
							$msg = 'LDAP server "'. $server['title'] .'" ignored: no matching authentication configured.';
							\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 1);
						}
					}
				}
				if ($load) {
					$server = $this->getLdapServer($serverUid);
					if (is_object($server)) {
						$ldapServers[$serverUid] = $server;
					}
				}
			}
		}
		
		if (!count($ldapServers)) {
			$msg = 'No LDAP server found.';
			\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 3);
			\NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR, $msg);
		}
		
		return $ldapServers;
	}
	
	/**
	 * reads the definition of one specific LDAP server
	 * 
	 * @param string $uid
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
	 */
	public function getLdapServer($uid) {
		$serverRecord = FALSE;
		$server = $this->allLdapServers[$uid];
		if (is_array($server)) {
			$errors = $this->checkServerConfiguration($server);
			
			if (count($errors) == 0) {
				$msg = 'Configuration for server "' . $uid . '" loaded successfully';
				if ($this->logLevel >= 2) {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', -1);
				}
				$msg = 'Configuration for server "' . $uid . '"';
				if ($this->logLevel == 3) {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 0, $server);
				}

				$groupRuleFE = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\LdapServer\\ServerConfigurationGroups');
				$groupRuleFE
					->setPid($server['fe_users.']['usergroups.']['pid'])
					->setImportGroups($server['fe_users.']['usergroups.']['importGroups'])
					->setMapping($server['fe_users.']['usergroups.']['mapping.'])
					->setReverseMapping($server['fe_users.']['usergroups.']['reverseMapping'])
					->setBaseDN($server['fe_users.']['usergroups.']['baseDN'])
					->setFilter($server['fe_users.']['usergroups.']['filter'])
					->setSearchAttribute($server['fe_users.']['usergroups.']['searchAttribute'])
					->setAddToGroups($server['fe_users.']['usergroups.']['addToGroups'])
					->setRestrictToGroups($server['fe_users.']['usergroups.']['restrictToGroups'])
					->setPreserveNonLdapGroups($server['fe_users.']['usergroups.']['preserveNonLdapGroups']);
				
				$userRuleFE = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\LdapServer\\ServerConfigurationUsers');
				$userRuleFE
					->setPid($server['fe_users.']['pid'])
					->setBaseDN($server['fe_users.']['baseDN'])
					->setFilter($server['fe_users.']['filter'])
					->setAutoImport($server['fe_users.']['autoImport'])
					->setAutoEnable($server['fe_users.']['autoEnable'])
					->setOnlyUsersWithGroup($server['fe_users.']['onlyUsersWithGroup'])
					->setMapping($server['fe_users.']['mapping.'])
					->setGroupRules($groupRuleFE);

				$groupRuleBE = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\LdapServer\\ServerConfigurationGroups');
				$groupRuleBE
					->setImportGroups($server['be_users.']['usergroups.']['importGroups'])
					->setMapping($server['be_users.']['usergroups.']['mapping.'])
					->setReverseMapping($server['be_users.']['usergroups.']['reverseMapping'])
					->setBaseDN($server['be_users.']['usergroups.']['baseDN'])
					->setFilter($server['be_users.']['usergroups.']['filter'])
					->setSearchAttribute($server['be_users.']['usergroups.']['searchAttribute'])
					->setAddToGroups($server['be_users.']['usergroups.']['addToGroups'])
					->setRestrictToGroups($server['be_users.']['usergroups.']['restrictToGroups'])
					->setPreserveNonLdapGroups($server['be_users.']['usergroups.']['preserveNonLdapGroups']);
				
				$userRuleBE = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\LdapServer\\ServerConfigurationUsers');
				$userRuleBE
					->setBaseDN($server['be_users.']['baseDN'])
					->setFilter($server['be_users.']['filter'])
					->setAutoImport($server['be_users.']['autoImport'])
					->setAutoEnable($server['be_users.']['autoEnable'])
					->setOnlyUsersWithGroup($server['be_users.']['onlyUsersWithGroup'])
					->setMapping($server['be_users.']['mapping.'])
					->setGroupRules($groupRuleBE);
				
				$serverConfiguration = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\LdapServer\\ServerConfiguration');
				$serverConfiguration
					->setUid($server['uid'])
					->setTitle($server['title'])
					->setHost($server['host'])
					->setPort($server['port'])
					->setForceTLS($server['forcetls'])
					->setAuthenticate($server['authenticate'])
					->setUser($server['user'])
					->setPassword($server['password'])
					->setFeUserRules($userRuleFE)
					->setBeUserRules($userRuleBE);
				if ($server['version']) {
					$serverConfiguration->setVersion($server['version']);
				}

				$serverRecord =  $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\LdapServer\\Server');
				$serverRecord->setConfiguration($serverConfiguration);
				
			} else {
				$msg = 'LDAP server configuration invalid for "'.$server['uid'].'":';
				\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 2, $errors);
				$msg .= '<ul><li>'.implode('</li><li>', $errors).'</li></ul>';
				\NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING, $msg, $server['uid']);
			}
		} else {
			$msg = 'LDAP server not found: uid = "'.$uid.'":';
			\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 2);
			\NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING, $msg, $server['uid']);
		}
		
		return $serverRecord;
	}
	
	/**
	 * checks an LDAP server's definition on syntactical correctness
	 * 
	 * @param array $server
	 * @return array
	 */
	private function checkServerConfiguration($server) {
		$errors = array();
		
		$res = \NormanSeibert\Ldap\Utility\Helpers::checkValue($server['pid'], 'int');
		if ($res['error']) {
			$errors[] = 'Attribute "pid": '.$res['error'];
		}
		
		$res = \NormanSeibert\Ldap\Utility\Helpers::checkValue($server['port'], 'required,int+');
		if ($res['error']) {
			$errors[] = 'Attribute "port": '.$res['error'];
		}
		
		$res = \NormanSeibert\Ldap\Utility\Helpers::checkValue($server['host'], 'required');
		if ($res['error']) {
			$errors[] = 'Attribute "host": '.$res['error'];
		}
		
		$res = \NormanSeibert\Ldap\Utility\Helpers::checkValue($server['version'], 'list', '1,2,3');
		if ($res['error']) {
			$errors[] = 'Attribute "version": '.$res['error'];
		}
		
		$res = \NormanSeibert\Ldap\Utility\Helpers::checkValue(strtolower($server['authenticate']), 'list', 'fe,be,both');
		if ($res['error']) {
			$errors[] = 'Attribute "auhenticate": '.$res['error'];
		}

		$server['authenticate'] = strtolower($server['authenticate']);
		
		if (($server['authenticate'] == 'fe') || ($server['authenticate'] == 'both')) {
			$res = \NormanSeibert\Ldap\Utility\Helpers::checkValue($server['fe_users.']['pid'], 'required,int');
			if ($res['error']) {
				$errors[] = 'Attribute "fe_users.pid": '.$res['error'];
			}
			
			$res = \NormanSeibert\Ldap\Utility\Helpers::checkValue($server['fe_users.']['filter'], 'required');
			if ($res['error']) {
				$errors[] = 'Attribute "fe_users.filter": '.$res['error'];
			}
			
			$res = \NormanSeibert\Ldap\Utility\Helpers::checkValue($server['fe_users.']['baseDN'], 'required');
			if ($res['error']) {
				$errors[] = 'Attribute "fe_users.baseDN": '.$res['error'];
			}
			
			if (is_array($server['fe_users.']['mapping.'])) {
				$newObj = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\Typo3User\\FrontendUser');
				foreach ($server['fe_users.']['mapping.'] as $fld => $mapping) {
					if (substr($fld, strlen($fld)-1, 1) == '.') {
						$fld = substr($fld, 0, strlen($fld)-1);
					}
					$ret = $newObj->_hasProperty($fld);
					if (!$ret) {
						$errors[] = 'Property "fe_users.'.$fld.'" is unknown to Extbase.';
					}
				}
				$newObj = NULL;
			}

			$res = \NormanSeibert\Ldap\Utility\Helpers::checkValue($server['fe_users.']['usergroups.']['pid'], 'int');
			if ($res['error']) {
				$errors[] = 'Attribute "fe_users.usergroups.pid": '.$res['error'];
			}

			if (is_array($server['fe_users.']['usergroups.']['mapping.'])) {				
				$newObj = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\Typo3User\\FrontendUserGroup');
				foreach ($server['fe_users.']['usergroups.']['mapping.'] as $fld => $mapping) {
					if (substr($fld, strlen($fld)-1, 1) == '.') {
						$fld = substr($fld, 0, strlen($fld)-1);
					}
					if ($fld != 'field') {
						$ret = $newObj->_hasProperty($fld);
						if (!$ret) {
							$errors[] = 'Property "fe_groups.'.$fld.'" is unknown to Extbase.';
						}
					}
				}
				$newObj = NULL;
			}

			$res = \NormanSeibert\Ldap\Utility\Helpers::checkValue($server['fe_users.']['mapping.']['username.']['data'], 'required');
			if ($res['error']) {
				$errors[] = 'Attribute "fe_users.mapping.userName.data": '.$res['error'];
			}
		}
		
		if (($server['authenticate'] == 'be') || ($server['authenticate'] == 'both')) {
			$res = \NormanSeibert\Ldap\Utility\Helpers::checkValue($server['be_users.']['filter'], 'required');
			if ($res['error']) {
				$errors[] = 'Attribute "be_users.filter": '.$res['error'];
			}
			
			$res = \NormanSeibert\Ldap\Utility\Helpers::checkValue($server['be_users.']['baseDN'], 'required');
			if ($res['error']) {
				$errors[] = 'Attribute "be_users.baseDN": '.$res['error'];
			}
			
			if (is_array($server['be_users.']['mapping.'])) {				
				$newObj = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\Typo3User\\BackendUser');
				foreach ($server['be_users.']['mapping.'] as $fld => $mapping) {
					if (substr($fld, strlen($fld)-1, 1) == '.') {
						$fld = substr($fld, 0, strlen($fld)-1);
					}
					$ret = $newObj->_hasProperty($fld);
					if (!$ret) {
						$errors[] = 'Property "be_users.'.$fld.'" is unknown to Extbase.';
					}
				}
				$newObj = NULL;
			}
			if (is_array($server['be_users.']['usergroups.']['mapping.'])) {			
				$newObj = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\Typo3User\\BackendUserGroup');
				foreach ($server['be_users.']['usergroups.']['mapping.'] as $fld => $mapping) {
					if (substr($fld, strlen($fld)-1, 1) == '.') {
						$fld = substr($fld, 0, strlen($fld)-1);
					}
					if ($fld != 'field') {
						$ret = $newObj->_hasProperty($fld);
						if (!$ret) {
							$errors[] = 'Property "be_groups.'.$fld.'" is unknown to Extbase.';
						}
					}
				}
				$newObj = NULL;
			}
			
			$res = \NormanSeibert\Ldap\Utility\Helpers::checkValue($server['be_users.']['mapping.']['username.']['data'], 'required');
			if ($res['error']) {
				$errors[] = 'Attribute "be_users.mapping.userName.data": '.$res['error'];
			}
		}
		
		return $errors;
	}
	
	/**
	 * checks whether PHP's LDAP functionality is available
	 * @return boolean
	 */
	public function checkLdapExtension() {
		$result = extension_loaded('ldap');
		if (!$result) {
			$msg = 'PHP LDAP extension not loaded.';
			\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 3);
			\NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR, $msg);
		}
		return $result;
	}
	
	/**
	 * 
	 * @return array
	 */
	public function getConfiguration() {
		return $this->config;
	}
}
?>