<?php

namespace NormanSeibert\Ldap\Service;

/*
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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DefaultRestrictionContainer;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\CMS\Core\Database\Query\Restriction\RootLevelRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use NormanSeibert\Ldap\Domain\Model\Configuration\LdapConfiguration;
use NormanSeibert\Ldap\Domain\Repository\LdapServer\LdapServerRepository;

/**
 * Service to authenticate users against LDAP directory.
 */
class LdapAuthService extends \TYPO3\CMS\Core\Authentication\AuthenticationService
{
    /**
     * Enable field columns of user table.
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
     * @var string
     */
    public $user_table = '';

    /**
     * @var Configuration
     */
    protected $ldapConfig;

    /**
     * @var Dispatcher
     */
    // protected $signalSlotDispatcher;

    /**
     * @var persistenceManager
     */
    protected $persistenceManager;

    /**
     * @var array
     */
    private $conf;

    /**
     * @var int
     */
    private $logLevel;

    /**
     * @var string
     */
    private $username = '';

    /**
     * @var string
     */
    private $password = '';

    /**
     * @var array
     */
    private $loginData = [];

    /**
     * @var array
     */
    private $ldapServers;

    private LdapServerRepository $serverRepository;

    /*
    public function __construct(PersistenceManager $persistenceManager)
    {
        $this->persistenceManager = $persistenceManager;
    }
    */

    /**
     * Initialize authentication service.
     *
     * @param string $subType                   Subtype of the service which is used to call the service
     * @param array  $loginData                 Submitted login form data
     * @param array  $authenticationInformation Information array. Holds submitted form data etc.
     * @param object $parentObject              Parent object
     */
    public function initAuth($subType, $loginData, $authenticationInformation, $parentObject)
    {
        // $this->signalSlotDispatcher =  GeneralUtility::makeInstance(Dispatcher::class);
        $this->loginData = $loginData;
        $this->authInfo = $authenticationInformation;

        // Plaintext or RSA authentication
        $this->username = $this->loginData['uname'];
        $this->password = $this->loginData['uident_text'];

        $this->user_table = $this->authInfo['db_user']['table'];

        $this->ldapConfig = GeneralUtility::makeInstance(LdapConfiguration::class);

        if (isset($this->ldapConfig)) {
            $this->conf = $this->ldapConfig->getConfiguration();
            $this->logLevel = $this->conf['logLevel'];
            $pid = null;
            if (isset($this->authInfo['db_user']['checkPidList'])) {
                $pid = $this->authInfo['db_user']['checkPidList'];
            }
            $this->serverRepository = GeneralUtility::makeInstance(LdapServerRepository::class);
            $this->ldapServers = $this->serverRepository->findAll(null, null, $this->authInfo['loginType'], $pid);
        }

        // for testing purposes only!
        // $_SERVER[$this->conf['ssoHeader']] = 'admin@entios.local';
        // $_SERVER[$this->conf['ssoHeader']] = 'entios\\admin';

        // SSO
        if (('logout' != $this->loginData['status']) && empty($this->password) && $this->conf['enableSSO']) {
            $this->activateSSO();
        }

        $configurationManager = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::class);
        $extBaseConfiguration = $configurationManager->getConfiguration(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS, 'FeLogin', 'Login');
        // print_r($extBaseConfiguration); die;
    }

    /**
     * Find a TYPO3 user.
     *
     * @param int $pid the page id in case of an FE user
     * 
     * @return array User array
     */
    public function getTypo3User($pid = null)
    {
        $user = $this->getRawUserByName($this->username, $pid);
        if (is_array($user)) {
            if ($this->logLevel >= 1) {
                $msg = 'TYPO3 user found: ' . $this->username;
                $this->logger->debug($msg);
            }
            $user['ldap_authenticated'] = false;
        } else {
            // Failed login attempt (no user found)
            $this->writelog(255, 3, 3, 2, 'Login-attempt from %s (%s), username \'%s\' not found!!', [$this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->username]);
        }

        return $user;
    }

    /**
     * Find a user (eg. look up the user record in database when a login is sent).
     *
     * @return mixed User array or FALSE
     */
    public function getUser()
    {
        $user = false;

        // if ($this->logLevel >= 2) {
        $msg = 'getUser() called, loginType: '.$this->authInfo['loginType'];
        $this->logger->debug($msg);
        // }
        if (isset($this->loginData['status']) && ('login' == $this->loginData['status'])) {
            if ($this->username) {
                if (2 == $this->logLevel) {
                    $msg = 'Username: ' . $this->username;
                    $this->logger->debug($msg);
                } elseif (3 == $this->logLevel) {
                    $msg = 'Username / Password: ' . $this->username . ' / ' . $this->password;
                    $this->logger->debug($msg);
                }

                if ('BE' == $this->authInfo['loginType']) {
                    $pid = 0;
                } elseif (isset($this->authInfo['db_user']['checkPidList'])) {
                    $pid = $this->authInfo['db_user']['checkPidList'];
                } else {
                    $pid = null;
                }

                if (isset($this->ldapServers) && count($this->ldapServers)) {
                    foreach ($this->ldapServers as $ldapServer) {
                        $uid = $ldapServer->getUid();
                        if ($user) {
                            if ($this->logLevel >= 1) {
                                $msg = 'User already authenticated';
                                $this->logger->debug($msg);
                            }
                        } else {
                            // Authenticate the user here because only users shall be imported which are authenticated.
                            // Otherwise every user present in the directory would be imported regardless of the entered password.

                            if ($this->logLevel >= 2) {
                                $msg = 'Try to authenticate with server: ' . $uid;
                                $this->logger->debug($msg);
                            }

                            $ldapServer->setScope(strtolower($this->authInfo['loginType']), $pid);
                            $ldapServer->loadAllGroups();

                            if ($this->conf['enableSSO'] && $this->conf['ssoHeader'] && ($_SERVER[$this->conf['ssoHeader']])) {
                                $ldapUser = $ldapServer->checkUser($this->username);
                                if ($this->logLevel >= 2) {
                                    $msg = 'Check SSO user: ' . $this->username;
                                    $this->logger->debug($msg);
                                }
                            } else {
                                $ldapUser = $ldapServer->authenticateUser($this->username, $this->password);
                                if ($this->logLevel >= 2) {
                                    $msg = 'Authenticate user: ' . $this->username;
                                    $this->logger->debug($msg);
                                }
                            }

                            if (isset($ldapUser) && is_object($ldapUser)) {
                                // Credentials are OK
                                if ($ldapServer->getConfiguration()->getUserRules($this->user_table)->getAutoImport()) {
                                    // Authenticated users shall be imported/updated
                                    if ($this->logLevel >= 2) {
                                        $msg = 'Import/update user: ' . $this->username;
                                        $this->logger->debug($msg);
                                    }
                                    $ldapUser->loadUser();
                                    $typo3User = $ldapUser->getUser();
                                    if (is_object($typo3User)) {
                                        $ldapUser->updateUser();
                                    } else {
                                        $ldapUser->addUser();
                                    }
                                    // $this->persistenceManager->persistAll();
                                    $persistenceManager = GeneralUtility::makeInstance(persistenceManager::class);
                                    $persistenceManager->persistAll();
                                }
                                if ($ldapServer->getConfiguration()->getUserRules($this->user_table)->getAutoEnable()) {
                                    $ldapUser->loadUser();
                                    $typo3User = $ldapUser->getUser();
                                    if (is_object($typo3User)) {
                                        if ($typo3User->getIsDisabled()) {
                                            // Authenticated users shall be enabled
                                            if ($this->logLevel >= 2) {
                                                $msg = 'Enable user: ' . $this->username;
                                                $this->logger->debug($msg);
                                            }
                                            $ldapUser->enableUser();
                                        }
                                    }
                                    // $this->persistenceManager->persistAll();
                                    $persistenceManager = GeneralUtility::makeInstance(persistenceManager::class);
                                    $persistenceManager->persistAll();
                                }
                                $user = $this->getTypo3User($pid);
                                $user['ldap_authenticated'] = true;

                                if ($this->logLevel >= 1) {
                                    $msg = 'User authenticated successfully: ' . $this->username;
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
     * Authenticate a user.
     *
     * @param array $user data of user
     */
    public function authUser(array $user): int
    {
        $ok = 100;

        if (($this->username) && isset($this->ldapServers) && is_array($this->ldapServers) && (count($this->ldapServers) > 0)) {
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
                    $this->writelog(255, 3, 3, 1, "Login-attempt from %s (%s), username '%s', password not accepted!", [$this->info['REMOTE_ADDR'], $this->info['REMOTE_HOST'], $this->username]);
                }
                if (1 == $this->logLevel) {
                    $msg = 'Password not accepted';
                    $this->logger->notice($msg);
                }
                if ($this->logLevel > 1) {
                    $msg = 'Password not accepted: '.$this->password;
                    $this->logger->notice($msg);
                }
            }
            $ok = $ok ? 200 : ($this->conf['onlyLDAP'] ? 0 : 100);
        }
        if ($ok && isset($user['lockToDomain']) && $user['lockToDomain'] != $this->authInfo['HTTP_HOST']) {
            // Lock domain didn't match, so error:
            if ($this->writeAttemptLog) {
                $this->writelog(255, 3, 3, 1, "Login-attempt from %s (%s), username '%s', locked domain '%s' did not match '%s'!", [$this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $user[$this->authInfo['db_user']['username_column']], $user['lockToDomain'], $this->authInfo['HTTP_HOST']]);
                // GeneralUtility::sysLog(sprintf("Login-attempt from %s (%s), username '%s', locked domain '%s' did not match '%s'!", $this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $user[$this->authInfo['db_user']['username_column']], $user['lockToDomain'], $this->authInfo['HTTP_HOST']), 'Core', 0);
            }
            $ok = 0;
        }

        $parameters = [
            'username' => $this->username,
            'user' => $user,
            'status' => $ok,
        ];
        // $this->signalSlotDispatcher->dispatch(__CLASS__, 'beforeAuthentication', $parameters);

        if ($this->logLevel >= 1) {
            $msg = 'function "authUser" returns: '.$ok;
            $this->logger->debug($msg);
        }

        return $ok;
    }

    /**
     * Fetching raw user record with username=$name.
     *
     * @param string $name the username to look up
     * @param int $pid the page id in case of an FE user
     *
     * @return array user record or FALSE
     *
     * @see \TYPO3\CMS\Core\Authentication\AbstractUserAuthentication::getUserByUid()
     *
     * @internal
     */
    public function getRawUserByName($name, $pid = null)
    {
        $query = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->user_table);

        if ($pid) {
            $where = $query->expr()->eq('username', $query->createNamedParameter($name, \PDO::PARAM_STR))
                     . ' AND '
                     . $query->expr()->eq('pid', $query->createNamedParameter($pid, \PDO::PARAM_STR));
        } else {
            $where = $query->expr()->eq('username', $query->createNamedParameter($name, \PDO::PARAM_STR));
        }

        $query->setRestrictions($this->userConstraints());
        $query->select('*')
            ->from($this->user_table)
            ->where($where)
        ;

        return $query->execute()->fetch();
    }

    /**
     * This returns the restrictions needed to select the user respecting
     * enable columns and flags like deleted, hidden, starttime, endtime
     * and rootLevel.
     *
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
}
