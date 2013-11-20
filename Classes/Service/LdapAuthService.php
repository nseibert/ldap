<?php
namespace NormanSeibert\Ldap\Service;
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
 * Service to authenticate users against LDAP directory
 */
class LdapAuthService extends \TYPO3\CMS\Sv\AuthenticationService {
	
	/**
	 * 
	 * @var string
	 */
	private $extKey = 'ldap';

	/**
	 *
	 * @var array
	 */
	private $conf;
	
	/**
	 *
	 * @var object
	 */
	public $pObj;
	
	/**
	 *
	 * @var interger
	 */
	private $logLevel;
	
	/**
	 *
	 * @var string
	 */
	private $authenticatedUser = '';
	
	/**
	 *
	 * @var array
	 */
	private $hooks;

	/**
	 * @var \NormanSeibert\Ldap\Domain\Model\Configuration\Configuration
	 * @inject
	 */
	protected $ldapConfig;
	
	/**
	 *
	 * @var TYPO3\CMS\Extbase\Object\ObjectManager
	 * @inject
	 */
	protected $objectManager;
	
	/**
	 * @var \TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface
	 * @inject
	 */
	protected $persistenceManager;
	
	/**
	 * Initialize authentication service
	 *
	 * @param string $subType Subtype of the service which is used to call the service.
	 * @param array $loginData Submitted login form data
	 * @param array $authenticationInformation Information array. Holds submitted form data etc.
	 * @param object $parentObject Parent object
	 */
	function initAuth($subType, $loginData, $authenticationInformation, $parentObject) {
		$this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
		$this->ldapConfig = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\Configuration\\Configuration');
		$this->conf = $this->ldapConfig->getConfiguration();
		$this->logLevel = $this->conf['logLevel'];
		$this->hooks = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/ldap'];
		$this->pObj = $parentObject;
		$this->loginData = $loginData;
		$this->authInfo = $authenticationInformation;
		
		$this->username = $this->loginData['uname'];
		$this->password = $this->loginData['uident_text'];
		
		$this->initTSFE($this->conf['rootPageUid']);
		$this->initExtbase();
		
		if (strlen($this->password) == 0) {
			if ($this->pObj->security_level == 'rsa') {
				if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('rsaauth')) {
					$backend = \TYPO3\CMS\Rsaauth\Backend\BackendFactory::getBackend();
					$storage = \TYPO3\CMS\Rsaauth\Storage\StorageFactory::getStorage();
					$key = $storage->get();
					
					if ($key != NULL && substr($this->loginData['uident'], 0, 4) == 'rsa:') {
						$this->password = $backend->decrypt($key, substr($this->loginData['uident'], 4));
					}
				} else {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('You have an error in your TYPO3 configuration. Your security level is set to "rsa" but the  extension "rsaauth" is not loaded.', 'ldap', 3);
				}
			} elseif ($this->pObj->security_level == 'superchallenged') {
				\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('LDAP extension does not work with security level "superchallenged". Please install und activate extension "rsaauth".', 'ldap', 3);
			}
			if ($this->loginData['status'] == 'logout') {
				// do nothing so far
			} elseif ($this->conf['enableSSO'] && $this->conf['ssoHeader'] && ($_SERVER[$this->conf['ssoHeader']])) {
				$username = $_SERVER[$this->conf['ssoHeader']];
				$users = $this->typo3DB->exec_SELECTgetRows(
					'*', $this->authInfo['db_user']['table'], "username = '" . $username . "'" . $this->authInfo['db_user']['enable_clause']
				);
				$user = $users[0];
				if (!is_array($user) || ($user['tx_ldap_nosso'] == '0')) {
					$this->username = $username;
					$this->loginData['status'] = 'login';
					$this->password = '';
					$this->authInfo['db_user']['checkPidList'] = '';
					$this->authInfo['db_user']['check_pid_clause'] = '';
				}
			}
		}
	}
	
	/**
	 * Workaround for Extbase bug #32931
	 * 
	 * @param string $page Rootpage uid
	 */
	function initTSFE($page = 1) {
		$pageUid = intval($page);
		if (is_object($GLOBALS['TSFE']) && $pageUid) {
			$GLOBALS['TSFE']->id = $pageUid;
			$GLOBALS['TSFE']->type = 0;        

			// builds rootline
			$GLOBALS['TSFE']->sys_page = $this->objectManager->get('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
			$rootLine = $GLOBALS['TSFE']->sys_page->getRootLine($pageUid);

			// init template
			$GLOBALS['TSFE']->tmpl = $this->objectManager->get('TYPO3\\CMS\\Core\\TypoScript\\ExtendedTemplateService');
			$GLOBALS['TSFE']->tmpl->tt_track = 0;// Do not log time-performance information
			$GLOBALS['TSFE']->tmpl->init();

			// this generates the constants/config + hierarchy info for the template.
			$GLOBALS['TSFE']->tmpl->runThroughTemplates($rootLine, 0);
			$GLOBALS['TSFE']->tmpl->generateConfig();
			$GLOBALS['TSFE']->tmpl->loaded = 1;

			// get config array and other init from pagegen
			$GLOBALS['TSFE']->getConfigArray();
			$GLOBALS['TSFE']->linkVars = ''.$GLOBALS['TSFE']->config['config']['linkVars'];
		}
    }
	
	/**
	 * Workaround to initialize Extbase with correct persistence settings
	 */
	function initExtbase() {
		$setupFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($this->extKey) . 'Configuration/TypoScript/setup.txt';
		$fileContent = \TYPO3\CMS\Core\Utility\GeneralUtility::getUrl($setupFile);
		$tsParser = $this->objectManager->get('TYPO3\\CMS\\Core\\TypoScript\\Parser\\TyposcriptParser');
		$tsParser->parse($fileContent);
		if (!$tsParser->error) {
			$extbaseFrameworkConfiguration = $tsParser->setup;
		}
		$configurationManager = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Configuration\\ConfigurationManager');
		$configurationManager->setConfiguration($extbaseFrameworkConfiguration['config.']['tx_extbase.']);
	}
	
	/**
	 * Find a user (eg. look up the user record in database when a login is sent)
	 *
	 * @return mixed User array or FALSE
	 */
	function getUser() {
		$user = array();
		$user['authenticated'] = FALSE;
		
		if ($this->logLevel > 0) {
			\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('getUser() called, loginType: ' . $this->authInfo['loginType'], 'ldap', 0);
		}
		if ($this->loginData['status'] == 'login') {
			if ($this->username) {
				if ($this->logLevel == 1) {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Username: ' . $this->username, 'ldap', 0);
				} elseif ($this->logLevel == 2) {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Username / Password: ' . $this->username . ' / ' . $this->password, 'ldap', 0);
				}
				if ($this->authInfo['loginType'] == 'BE') {
					$pid = 0;
				} else {
					$pid = $this->authInfo['db_user']['checkPidList'];
				}
				$ldapServers = $this->ldapConfig->getLdapServers('', '', $this->authInfo['loginType'], $this->authInfo['db_user']['checkPidList']);			
				foreach ($ldapServers as $server) {
					$server->setScope(strtolower($this->authInfo['loginType']), $pid);
					$server->loadAllGroups();
					if (!$user['authenticated']) {
						// Authenticate the user here because only users shall be imported which are authenticated.
						// Otherwise every user present in the directory would be imported regardless of the entered password.
						$ldapUser = $server->authenticateUser($this->username, $this->password);
						if (is_object($ldapUser)) {
							// Credentials are OK
							if ($server->getConfiguration()->getUserRules($this->authInfo['db_user']['table'])->getAutoImport()) {
								// Authenticated users shall be imported/updated
								if ($this->logLevel >= 1) {
									\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Import/update user ' . $this->username, 'ldap', 0);
								}
								$ldapUser->loadUser();
								$typo3User = $ldapUser->getUser();
								if (is_object($typo3User)) {
									$ldapUser->updateUser();
								} else {
									$ldapUser->addUser();
								}
								// Necessary to enable fetchUserRecord()
								$this->persistenceManager = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\PersistenceManagerInterface');
								$this->persistenceManager->persistAll();
							}
							$user = $this->fetchUserRecord($this->username);
							if (!is_array($user)) {
								// Failed login attempt (no user found)
								$this->writelog(255, 3, 3, 2, 'Login-attempt from %s (%s), username \'%s\' not found!!', array($this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->login['uname'])); // Logout written to log
								\TYPO3\CMS\Core\Utility\GeneralUtility::sysLog(sprintf('Login-attempt from %s (%s), username \'%s\' not found!', $this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->login['uname']), 'Core', 0);
							} else {
								if ($this->logLevel >= 1) {
									\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('TYPO3 user found: ' . \TYPO3\CMS\Core\Utility\GeneralUtility::arrayToLogString($user, array($this->authInfo['db_user']['userid_column'], $this->authInfo['db_user']['username_column'])), 'ldap', -1);
								}
								$user['authenticated'] = TRUE;
							}
						} else {
							$user = $this->fetchUserRecord($this->username);
							if (!is_array($user)) {
								// Failed login attempt (no user found)
								$this->writelog(255, 3, 3, 2, 'Login-attempt from %s (%s), username \'%s\' not found!!', array($this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->login['uname'])); // Logout written to log
								\TYPO3\CMS\Core\Utility\GeneralUtility::sysLog(sprintf('Login-attempt from %s (%s), username \'%s\' not found!', $this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->login['uname']), 'Core', 0);
							} else {
								if ($this->logLevel >= 1) {
									\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('TYPO3 user found: ' . \TYPO3\CMS\Core\Utility\GeneralUtility::arrayToLogString($user, array($this->authInfo['db_user']['userid_column'], $this->authInfo['db_user']['username_column'])), 'ldap', -1);
								}
								$user['authenticated'] = FALSE;
							}
							if ($this->logLevel >= 1) {
								\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Login failed', 'ldap', 2);
							}
						}
					}
				}
			}
		}
		
		return $user;
	}
	
	/**
	 * Authenticate a user (Check various conditions for the user that might invalidate its authentication, eg. password match, domain, IP, etc.)
	 *
	 * @param array $user Data of user.
	 * @return boolean
	 */
	public function authUser(array $user) {
		$ok = 100;
		
		if ($this->username) {
			$ok = 0;
			/*
			$ldapServers = $this->ldapConfig->getLdapServers('', '', $this->authInfo['loginType'], $this->authInfo['db_user']['checkPidList']);
			foreach ($ldapServers as $server) {
					$server->setScope(strtolower($this->authInfo['loginType']));
					$server->loadAllGroups();
				if (!$ok) {
					if ($this->logLevel >= 1) {
						\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Check server: ' . $server->getUid(), 'ldap', 0);
					}
					$ldapUser = $server->authenticateUser($this->username, $this->password);
					if (is_object($ldapUser)) {
						if ($this->logLevel >= 1) {
							\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Login successful', 'ldap', -1);
						}
						$ok = 100;
					}
				}
			}
			*/
			// User has already been authenticated during getUser()
			if ($user['authenticated']) {
				if ($this->logLevel >= 1) {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Login successful', 'ldap', -1);
				}
				$ok = 100;
			}
			if (!$ok) {
				// Failed login attempt (wrong password) - write that to the log!
				if ($this->writeAttemptLog) {
					$this->writelog(255, 3, 3, 1, "Login-attempt from %s (%s), username '%s', password not accepted!", array($this->info['REMOTE_ADDR'], $this->info['REMOTE_HOST'], $this->username));
				}
				if ($this->logLevel >= 1) {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Password not accepted: ' . $this->password, 'ldap', 2);
				}
			}
			$ok = $ok ? 200 : ($this->conf['onlyLDAP'] ? 0 : 100);
		}
		if ($ok && $user['lockToDomain'] && $user['lockToDomain'] != $this->authInfo['HTTP_HOST']) {
			// Lock domain didn't match, so error:
			if ($this->writeAttemptLog) {
				$this->writelog(255, 3, 3, 1, "Login-attempt from %s (%s), username '%s', locked domain '%s' did not match '%s'!", array($this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $user[$this->authInfo['db_user']['username_column']], $user['lockToDomain'], $this->authInfo['HTTP_HOST']));
				\TYPO3\CMS\Core\Utility\GeneralUtility::sysLog(sprintf("Login-attempt from %s (%s), username '%s', locked domain '%s' did not match '%s'!", $this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $user[$this->authInfo['db_user']['username_column']], $user['lockToDomain'], $this->authInfo['HTTP_HOST']), 'Core', 0);
			}
			$ok = false;
		}

		$hooks = $this->hooks['ext/ldap']['login'];
		if (is_array($hooks)) {
			$parameters = array(
				'username' => $this->username,
				'user' => $user,
				'status' => $ok
			);
			foreach ($hooks as $hook) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::callUserFunction($hook, $parameters, $this);
			}
		}
		return $ok;
	}
	
	/**
	 * Get a user from DB by username
	 * provided for usage from services
	 *
	 * @param string $username user name
	 * @param string $extraWhere Additional WHERE clause: " AND ...
	 * @param array $dbUser User db table definition: $this->db_user
	 * @return mixed User array or FALSE
	 */
	function fetchUserRecord($username, $extraWhere = '', $dbUserSetup = '') {
		$dbUser = is_array($dbUserSetup) ? $dbUserSetup : $this->authInfo['db_user'];
		$user = $this->pObj->fetchUserRecord($dbUser, $username, $extraWhere);
		return $user;
	}
}
?>