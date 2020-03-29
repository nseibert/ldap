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

use Psr\Log\LoggerAwareTrait;
use \NormanSeibert\Ldap\Domain\Model\Configuration\Configuration;
use \TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DefaultRestrictionContainer;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\CMS\Core\Database\Query\Restriction\RootLevelRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;


/**
 * Service to authenticate users against LDAP directory
 */
// @extensionScannerIgnoreLine
class LdapAuthService extends \TYPO3\CMS\Core\Authentication\AuthenticationService implements \Psr\Log\LoggerAwareInterface {

	use LoggerAwareTrait;

	/**
	 *
	 * @var array
	 */
	private $conf;
	
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
     */
    protected $signalSlotDispatcher;

    /**
     *
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     */
    protected $configurationManager;

    /**
     * Enable field columns of user table
     * @var array
     */
    public $enablecolumns = [
        'rootLevel' => '',
        // Boolean: If TRUE, 'AND pid=0' will be a part of the query...
        'disabled' => '',
        'starttime' => '',
        'endtime' => '',
        'deleted' => '',
    ];

    /**
     * Table in database with user data
     * @var string
     */
    public $user_table = '';

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
		$this->persistenceManager = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\PersistenceManagerInterface');
		$this->ldapConfig = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\Configuration\\Configuration');
        $this->conf = $this->ldapConfig->getConfiguration();
        
		$this->logLevel = $this->conf['logLevel'];
		$this->loginData = $loginData;
		$this->authInfo = $authenticationInformation;

		// Plaintext or RSA authentication
		$this->username = $this->loginData['uname'];
		$this->password = $this->loginData['uident_text'];

		$this->user_table = $this->authInfo['db_user']['table'];
		$this->ldapServers = $this->ldapConfig->getLdapServers('', '', $this->authInfo['loginType'], $this->authInfo['db_user']['checkPidList']);
		
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
				$msg = 'SSO active for user: ' . $this->username;
				$this->logger->info($msg);
			}
		}
	}
	
	/**
	 * Find a TYPO3 user
	 *
	 * @return mixed User array or FALSE
	 */
	function getTypo3User() {
		$user = $this->getRawUserByName($this->username);
		if (!is_array($user)) {
			// Failed login attempt (no user found)
			$this->writelog(255, 3, 3, 2, 'Login-attempt from %s (%s), username \'%s\' not found!!', array($this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->login['uname'])); // Logout written to log
			\TYPO3\CMS\Core\Utility\GeneralUtility::sysLog(sprintf('Login-attempt from %s (%s), username \'%s\' not found!', $this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->username), 'Core', 0);
		} else {
			if ($this->logLevel >= 1) {
				$msg = 'TYPO3 user found: ' . $this->username;
				$this->logger->debug($msg);
			}
			unset($user['ldap_authenticated']);
		}
		return $user;
	}
	
	/**
	 * Find a user (eg. look up the user record in database when a login is sent)
	 *
	 * @return mixed User array or FALSE
	 */
	function getUser() {
		$user = FALSE;
		
		// if ($this->logLevel >= 2) {
			$msg = 'getUser() called, loginType: ' . $this->authInfo['loginType'];
			$this->logger->debug($msg);
		// }
		if ($this->loginData['status'] == 'login') {
			if ($this->username) {
				if ($this->logLevel == 2) {
					$msg = 'Username: ' . $this->username;
					$this->logger->debug($msg);
				} elseif ($this->logLevel == 3) {
					$msg = 'Username / Password: ' . $this->username . ' / ' . $this->password;
					$this->logger->debug($msg);
				}
				if ($this->authInfo['loginType'] == 'BE') {
					$pid = 0;
				} else {
					$pid = $this->authInfo['db_user']['checkPidList'];
				}
				if (count($this->ldapServers)) {
					foreach ($this->ldapServers as $server) {
						if ($user) {
							if ($this->logLevel >= 1) {
								$msg = 'User already authenticated';
								$this->logger->debug($msg);
							}
						} else {
							// Authenticate the user here because only users shall be imported which are authenticated.
							// Otherwise every user present in the directory would be imported regardless of the entered password.
							
							if ($this->logLevel >= 2) {
								$msg = 'Try to authenticate with server: ' . $server->getUid();
								$this->logger->debug($msg);
							}

							/* @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server */
							$server->setScope(strtolower($this->authInfo['loginType']), $pid);
							$server->loadAllGroups();

							if ($this->conf['enableSSO'] && $this->conf['ssoHeader'] && ($_SERVER[$this->conf['ssoHeader']])) {
                                /* @var $ldapUser \NormanSeibert\Ldap\Domain\Model\LdapUser\User */
								$ldapUser = $server->checkUser($this->username);
								if ($this->logLevel >= 2) {
									$msg = 'Check SSO user: ' . $this->username;
									$this->logger->debug($msg);
								}
							} else {
                                /* @var $ldapUser \NormanSeibert\Ldap\Domain\Model\LdapUser\User */
								$ldapUser = $server->authenticateUser($this->username, $this->password);
								if ($this->logLevel >= 2) {
									$msg = 'Authenticate user: ' . $this->username;
									$this->logger->debug($msg);
								}
							}

							if (is_object($ldapUser)) {
								// Credentials are OK
								if ($server->getConfiguration()->getUserRules($this->user_table)->getAutoImport()) {
									// Authenticated users shall be imported/updated
									if ($this->logLevel >= 2) {
										$msg = 'Import/update user ' . $this->username;
										$this->logger->debug($msg);
									}
									$ldapUser->loadUser();
									$typo3User = $ldapUser->getUser();
									if (is_object($typo3User)) {
										$ldapUser->updateUser();
									} else {
										$ldapUser->addUser();
									}
									$this->persistenceManager->persistAll();
								}
								if ($server->getConfiguration()->getUserRules($this->user_table)->getAutoEnable()) {
									$ldapUser->loadUser();
									$typo3User = $ldapUser->getUser();
									if (is_object($typo3User)) {
										if ($typo3User->getIsDisabled()) {
											// Authenticated users shall be enabled
											if ($this->logLevel >= 2) {
												$msg = 'Enable user ' . $this->username;
												$this->logger->debug($msg);
											}
											$ldapUser->enableUser();
										}
									}
									$this->persistenceManager->persistAll();
								}
								$user = $this->getTypo3User();
								$user['ldap_authenticated'] = TRUE;

								if ($this->logLevel >= 1) {
									$msg = 'User authenticated successfully ' . $this->username;
									$this->logger->debug($msg);
								}
							} else {
								// $user = $this->getTypo3User();
								if ($this->logLevel >= 1) {
									$msg = 'Login failed';
									$this->logger->notice($msg);
								}
							}
						}
					}
				} else {
					$user = $this->getTypo3User();
					if ($this->logLevel >= 1) {
						$msg = 'No LDAP servers configured';
						$this->logger->warning($msg);
					}
				}
			}
		}
		
		return $user;
	}
	
	/**
	 * Authenticate a user
	 *
	 * @param array $user Data of user.
	 * @return int
	 */
	public function authUser(array $user):int
	{
		$ok = 100;
		
		if (($this->username) && ($this->ldapServers) && is_array($this->ldapServers) && (count($this->ldapServers) > 0)) {
			$ok = 0;
			// User has already been authenticated during getUser()
			if (isset($user['ldap_authenticated'])) {
				if ($this->logLevel >= 1) {
					$msg = 'Login successful';
					$this->logger->debug($msg);
				}
				$ok = 100;
			}
			if (!$ok) {
				// Failed login attempt (wrong password) - write that to the log!
				if ($this->writeAttemptLog) {
					$this->writelog(255, 3, 3, 1, "Login-attempt from %s (%s), username '%s', password not accepted!", array($this->info['REMOTE_ADDR'], $this->info['REMOTE_HOST'], $this->username));
				}
				if ($this->logLevel == 1) {
					$msg = 'Password not accepted';
					$this->logger->notice($msg);
				}
				if ($this->logLevel > 1) {
					$msg = 'Password not accepted: ' . $this->password;
					$this->logger->notice($msg);
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
			$ok = 0;
		}

		$parameters = array(
			'username' => $this->username,
			'user' => $user,
			'status' => $ok
		);
		$this->signalSlotDispatcher->dispatch(__CLASS__, 'beforeAuthentication', $parameters);
		
		if ($this->logLevel >= 1) {
			$msg = 'function "authUser" returns: ' . $ok;
			$this->logger->debug($msg);
		}
			
		return $ok;
	}

	/**
     * This returns the restrictions needed to select the user respecting
     * enable columns and flags like deleted, hidden, starttime, endtime
     * and rootLevel
     *
     * @return QueryRestrictionContainerInterface
     * @internal
     */
    protected function userConstraints(): QueryRestrictionContainerInterface
    {
        $restrictionContainer = GeneralUtility::makeInstance(DefaultRestrictionContainer::class);

        if (empty($this->enablecolumns['disabled'])) {
            $restrictionContainer->removeByType(HiddenRestriction::class);
        }

        if (empty($this->enablecolumns['deleted'])) {
            $restrictionContainer->removeByType(DeletedRestriction::class);
        }

        if (empty($this->enablecolumns['starttime'])) {
            $restrictionContainer->removeByType(StartTimeRestriction::class);
        }

        if (empty($this->enablecolumns['endtime'])) {
            $restrictionContainer->removeByType(EndTimeRestriction::class);
        }

        if (!empty($this->enablecolumns['rootLevel'])) {
            $restrictionContainer->add(GeneralUtility::makeInstance(RootLevelRestriction::class, [$this->user_table]));
        }

        return $restrictionContainer;
    }

	/**
     * Fetching raw user record with username=$name
     *
     * @param string $name The username to look up.
     * @return array user record or FALSE
     * @see \TYPO3\CMS\Core\Authentication\AbstractUserAuthentication::getUserByUid()
     * @internal
     */
    public function getRawUserByName($name)
    {
        $query = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->user_table);
        $query->setRestrictions($this->userConstraints());
        $query->select('*')
            ->from($this->user_table)
            ->where($query->expr()->eq('username', $query->createNamedParameter($name, \PDO::PARAM_STR)));

        return $query->execute()->fetch();
    }
}
?>
