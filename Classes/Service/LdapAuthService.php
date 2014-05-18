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
	 * @var integer
	 */
	private $logLevel;

    /**
     *
     * @var string
     */
    private $username = '';

    /**
     *
     * @var string
     */
    private $password = '';

    /**
     *
     * @var array
     */
    private $loginData = array();
	
	/**
	 *
	 * @var array
	 */
	private $ldapServers;

	/**
	 * @var \NormanSeibert\Ldap\Domain\Model\Configuration\Configuration
	 */
	protected $ldapConfig;
	
	/**
	 *
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 * @inject
	 */
	protected $objectManager;

    /**
     *
     * @var \TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     *
     * @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
     * @inject
     */
    protected $signalSlotDispatcher;

    /**
     *
     * @var \NormanSeibert\Ldap\Service\TypoScriptService
    */
    protected $typoScriptService;

    /**
     *
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     */
    protected $configurationManager;

    /**
     *
     * @var \TYPO3\CMS\Extbase\Reflection\ReflectionService
     */
    protected $reflectionService;

    /**
     *
     * @var \TYPO3\CMS\Core\Cache\CacheManager
     */
    protected $cacheManager;

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
		$this->signalSlotDispatcher = $this->objectManager->get('TYPO3\\CMS\\Extbase\\SignalSlot\\Dispatcher');
		$this->typoScriptService = $this->objectManager->get('NormanSeibert\\Ldap\\Service\\TypoScriptService');
		$this->ldapConfig = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\Configuration\\Configuration');
		$this->conf = $this->ldapConfig->getConfiguration();
		$this->logLevel = $this->conf['logLevel'];
		$this->pObj = $parentObject;
		$this->loginData = $loginData;
		$this->authInfo = $authenticationInformation;
		
		// Initialize TSFE and Extbase
		if (version_compare(TYPO3_branch, '6.0', '>')) {
			\TYPO3\CMS\Core\Core\Bootstrap::getInstance()->loadCachedTca();
		}
		$this->initializeRequiredTsfeParts();
		$this->initializeExtbaseFramework();

		// Plaintext or RSA authentication
		$this->username = $this->loginData['uname'];
		$this->password = $this->loginData['uident_text'];
		
		// for testing purposes only!
		// $_SERVER[$this->conf['ssoHeader']] = 'admin@entios.local';
		// $_SERVER[$this->conf['ssoHeader']] = 'entios\\admin';

		// SSO
		if (($this->loginData['status'] != 'logout') && empty($this->password) && $this->conf['enableSSO']) {
			$this->activateSSO();
		}
	}

	/**
	 * Activates Single Sign On (SSO)
	 *
	 * @return void
	 */
	function activateSSO() {
		if ($this->conf['ssoHeader'] && ($_SERVER[$this->conf['ssoHeader']])) {
			$this->username = $_SERVER[$this->conf['ssoHeader']];
			$this->loginData['status'] = 'login';
			$this->password = '';
			$this->authInfo['db_user']['checkPidList'] = '';
			$this->authInfo['db_user']['check_pid_clause'] = '';

			$slotReturn = $this->signalSlotDispatcher->dispatch(
				__CLASS__,
				'beforeSSO',
				array(
					'username' => $this->username
				)
			);
			// Before TYPO3 6.2 there is no return value!
			if ($slotReturn) {
				$this->username = $slotReturn['username'];
			}
			
			if ($this->logLevel > 0) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('SSO active for user: ' . $this->username, 'ldap', 0);
			}
		}
	}
	
	/**
	 * Find a TYPO3 user
	 *
	 * @return mixed User array or FALSE
	 */
	function getTypo3User() {
		$user = $this->fetchUserRecord($this->username);
		if (!is_array($user)) {
			// Failed login attempt (no user found)
			$this->writelog(255, 3, 3, 2, 'Login-attempt from %s (%s), username \'%s\' not found!!', array($this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->login['uname'])); // Logout written to log
			\TYPO3\CMS\Core\Utility\GeneralUtility::sysLog(sprintf('Login-attempt from %s (%s), username \'%s\' not found!', $this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->username), 'Core', 0);
		} else {
			if ($this->logLevel >= 1) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('TYPO3 user found: ' . $this->username, 'ldap', -1);
			}
			$user['authenticated'] = FALSE;
		}
		return $user;
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
				$this->ldapServers = $this->ldapConfig->getLdapServers('', '', $this->authInfo['loginType'], $this->authInfo['db_user']['checkPidList']);
				if (count($this->ldapServers)) {
					foreach ($this->ldapServers as $server) {
                        /* @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server */
						$server->setScope(strtolower($this->authInfo['loginType']), $pid);
						$server->loadAllGroups();

						if ($user['authenticated']) {
							if ($this->logLevel >= 1) {
								\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('User already authenticated', 'ldap', 0);
							}
						} else {
							// Authenticate the user here because only users shall be imported which are authenticated.
							// Otherwise every user present in the directory would be imported regardless of the entered password.
							
							if ($this->conf['enableSSO'] && $this->conf['ssoHeader'] && ($_SERVER[$this->conf['ssoHeader']])) {
                                /* @var $ldapUser \NormanSeibert\Ldap\Domain\Model\LdapUser\User */
								$ldapUser = $server->checkUser($this->username);
							} else {
                                /* @var $ldapUser \NormanSeibert\Ldap\Domain\Model\LdapUser\User */
								$ldapUser = $server->authenticateUser($this->username, $this->password);
							}
							
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
								$user = $this->getTypo3User();
								$user['authenticated'] = TRUE;
							} else {
								$user = $this->getTypo3User();
								if ($this->logLevel >= 1) {
									\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Login failed', 'ldap', 2);
								}
							}
						}
					}
				} else {
					$user = $this->getTypo3User();
					if ($this->logLevel >= 1) {
						\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('No LDAP servers configured', 'ldap', 2);
					}
				}
			}
		}
		
		$this->typoScriptService->restoreTypoScriptBackup();
		
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
		
		if (($this->username) && (count($this->ldapServers) > 0)) {
			$ok = 0;
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

		$parameters = array(
			'username' => $this->username,
			'user' => $user,
			'status' => $ok
		);
		$this->signalSlotDispatcher->dispatch(__CLASS__, 'beforeAuthentication', $parameters);
		
		if ($this->logLevel >= 1) {
			\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('function "authUser" returns: ' . $ok, 'ldap', 0);
		}
		
		$this->typoScriptService->restoreTypoScriptBackup();
			
		return $ok;
	}

    /**
     * Get a user from DB by username
     * provided for usage from services
     *
     * @param string $username user name
     * @param string $extraWhere Additional WHERE clause: " AND ...
     * @param string $dbUserSetup
     * @internal param array $dbUser User db table definition: $this->db_user
     * @return mixed User array or FALSE
     */
	function fetchUserRecord($username, $extraWhere = '', $dbUserSetup = '') {
		$dbUser = is_array($dbUserSetup) ? $dbUserSetup : $this->authInfo['db_user'];
		$user = $this->pObj->fetchUserRecord($dbUser, $username, $extraWhere);
		return $user;
	}
	
	//
	// Helper functions
	//
	
	/**
	 * @return void
	 */
	protected function initializeRequiredTsfeParts() {
		if (!isset($GLOBALS['TSFE']) || empty($GLOBALS['TSFE']->sys_page)) {
			$GLOBALS['TSFE']->sys_page = $this->objectManager->get('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
		}
		if (!isset($GLOBALS['TSFE']) || empty($GLOBALS['TSFE']->tmpl)) {
			$GLOBALS['TSFE']->tmpl = $this->objectManager->get('TYPO3\\CMS\\Core\\TypoScript\\ExtendedTemplateService');
		}
		if (!isset($GLOBALS['TSFE']) || (empty($GLOBALS['TSFE']->csConvObj)))	{
			$GLOBALS['TSFE']->csConvObj = $this->objectManager->get('TYPO3\\CMS\\Core\\Charset\\CharsetConverter');
		}
	}
	
	/**
	 * @return void
	 */
	protected function initializeExtbaseFramework() {
		// initialize cache manager
		$this->cacheManager = $GLOBALS['typo3CacheManager'];

		// inject content object into the configuration manager
		$this->configurationManager = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Configuration\\ConfigurationManagerInterface');
		$contentObject = $this->objectManager->get('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
		$this->configurationManager->setContentObject($contentObject);

		$this->typoScriptService->makeTypoScriptBackup();
		// load extbase typoscript
		\NormanSeibert\Ldap\Service\TypoScriptService::loadTypoScriptFromFile('EXT:extbase/ext_typoscript_setup.txt');
		// load this extensions typoscript (database column => model property map etc)
		\NormanSeibert\Ldap\Service\TypoScriptService::loadTypoScriptFromFile('EXT:ldap/Configuration/TypoScript/setup.txt');
		$this->configurationManager->setConfiguration($GLOBALS['TSFE']->tmpl->setup);
		$this->configureObjectManager();

		// initialize reflection
		$this->reflectionService = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Reflection\\ReflectionService');
		$this->reflectionService->setDataCache($this->cacheManager->getCache('extbase_reflection'));
		if (!$this->reflectionService->isInitialized()) {
			$this->reflectionService->initialize();
		}

		// initialize persistence
		$this->persistenceManager = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\PersistenceManagerInterface');
	}

	/**
	 * @return void
	 */
	protected function configureObjectManager() {
		$typoScriptSetup = $this->configurationManager->getConfiguration(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
		if (!is_array($typoScriptSetup['config.']['tx_extbase.']['objects.'])) {
			return;
		}
		$objectContainer = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Object\\Container\\Container');
		foreach ($typoScriptSetup['config.']['tx_extbase.']['objects.'] as $classNameWithDot => $classConfiguration) {
			if (isset($classConfiguration['className'])) {
				$originalClassName = rtrim($classNameWithDot, '.');
				$objectContainer->registerImplementation($originalClassName, $classConfiguration['className']);
			}
		}
	}
}
?>