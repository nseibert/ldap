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


// use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use NormanSeibert\Ldap\Domain\Model\LdapUser\LdapBeUser;
use NormanSeibert\Ldap\Domain\Model\LdapUser\LdapFeUser;
use NormanSeibert\Ldap\Domain\Model\LdapServer\LdapServer;
use NormanSeibert\Ldap\Utility\Helpers;
use SplObjectStorage;
use Psr\Log\LoggerInterface;

/**
 * Model for an LDAP server.
 */
class LdapHandler
{
    private LoggerInterface $logger;

    const ERROR = 2;
    const WARNING = 1;
    const OK = 0;
    const INFO = -1;
    const NOTICE = -2;

    /**
     * @var int
     */
    protected $logLevel;

    public function __construct()
    {
         $this->logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * finds users based on a text attribute, typically the username.
     */
    public function getUsers(LdapServer $server, string $findname = '*', bool $doSanitize = false): SplObjectStorage
    {
        $users = null;
        $info = [];
        $bind = null;
        $connect = null;
        $filter = null;
        $baseDN = null;

        $users = new SplObjectStorage();

        if ($server->getUserType() == 'be') {
            $table = 'be_users';
        } else {
            $table = 'fe_users';
        }

        if (strlen($findname)) {
            if ($doSanitize) {
                $findname = Helpers::sanitizeQuery($findname);
            }
            $baseDN = $server->getConfiguration()->getUserRules($table)->getBaseDN();
            $filter = $server->getConfiguration()->getUserRules($table)->getFilter();
        }

        if (!empty($filter) && !empty($baseDN)) {
            $connect = $this->ldapConnect($server);
        } else {
            $msg = 'No baseDN or no filter given.';
            $this->logger->notice($msg);
        }

        if ($connect) {
            if ($server->getConfiguration()->getUser() && $server->getConfiguration()->getPassword()) {
                $bind = $this->ldapBind($server, $connect, $server->getConfiguration()->getUser(), $server->getConfiguration()->getPassword());
            } else {
                $bind = $this->ldapBind($server, $connect);
            }
        }

        if ($bind) {
            $parsedFilter = str_replace('<search>', $findname, $filter);
            if (3 == $this->logLevel) {
                $msg = 'Query server "' . $server->getConfiguration()->getUid();
                $logArray = [
                    'baseDN' => $baseDN,
                    'filter' => $parsedFilter,
                ];
                $this->logger->debug($msg, $logArray);
            }

            $info = $this->ldapSearch($server, $connect, $baseDN, $parsedFilter, 'sub');
        } else {
            $msg = 'Bind failed.';
            $this->logger->notice($msg);
        }

        if (isset($info['count']) && ($info['count'] >= 0)) {
            $parameters = [
                'server' => $server,
                'find' => $findname,
                'table' => $table,
                'type' => 'list',
                'result' => $info,
            ];
            // $this->eventDispatcher->dispatch(__CLASS__, 'getUsersResults', $parameters);
            
            for ($i = 0; $i < $info['count']; ++$i) {
                if ('be_users' == $table) {
                    $user = GeneralUtility::makeInstance(LdapBeUser::class);
                } else {
                    $user = GeneralUtility::makeInstance(LdapFeUser::class);
                }

                $user->setDN($info[$i]['dn']);
                $user->setAttributes($info[$i]);
                $user->setLdapServer($server);
                
                $users->attach($user);
            }
            

            $msg = 'Found ' . $info['count'] . ' records';
            if (3 == $this->logLevel) {
                $this->logger->debug($msg);
            }
        } else {
            $msg = 'Invalid LDAP query result';
            if (3 == $this->logLevel) {
                $this->logger->debug($msg);
            }
        }

        return $users;
    }

    /**
     * finds a single user by its DN.
     */
    public function getUser(LdapServer $server, string $dn, bool $doSanitize = false): LdapFeUser | LdapBeUser
    {
        $bind = null;
        $connect = null;
        $user = null;
        $distinguishedName = null;
        $filter = null;
        $parsedFilter = null;

        if ($server->getUserType() == 'be') {
            $table = 'be_users';
        } else {
            $table = 'fe_users';
        }

        //TODO: findet die User ggf. auch ueber andere Server als den, ueber den urspruenglich importiert wurde.
        $info = [];
        if (strlen($dn)) {
            $connect = $this->ldapConnect($server);
        }
        if ($connect) {
            if ($server->getConfiguration()->getUser() && $server->getConfiguration()->getPassword()) {
                $bind = $this->ldapBind($server, $connect, $server->getConfiguration()->getUser(), $server->getConfiguration()->getPassword());
            } else {
                $bind = $this->ldapBind($server, $connect);
            }
        }
        if ($bind) {
            if ($doSanitize) {
                $distinguishedName = Helpers::sanitizeQuery($dn);
            } else {
                $distinguishedName = $dn;
            }
            $filter = $server->getConfiguration()->getUserRules($table)->getFilter();
            $parsedFilter = str_replace('<search>', '*', $filter);
            $info = $this->ldapSearch($server, $connect, $distinguishedName, $parsedFilter, 'base');
        } else {

        }

        $parameters = [
            'server' => $this,
            'dn' => $dn,
            'table' => $table,
            'type' => 'single',
            'result' => $info,
        ];
        //$this->eventDispatcher->dispatch(__CLASS__, 'getUserResults', $parameters);

        if (1 == $info['count']) {
            if ('be_users' == $table) {
                $user = GeneralUtility::makeInstance(LdapBeUser::class);
            } else {
                $user = GeneralUtility::makeInstance(LdapFeUser::class);
            }
        }

        if (is_object($user)) {
            $user
                ->setDN($distinguishedName)
                ->setAttributes($info[0])
                ->setLdapServer($server)
            ;

            $msg = 'Found record: ' . $distinguishedName;
            if ($this->logLevel >= 2) {
                $this->logger->info($msg);
            }
        } else {
            $msg = 'Did not find a unique record for the user DN = ' . $dn . ', but found ' . $info['count'] . ' records instead.';
            if ($this->logLevel >= 2) {
                $this->logger->notice($msg);
            }
        }

        return $user;
    }

    /**
     * find a single group based on its DN.
     */
    public function getGroup(LdapServer $server, string $dn): array
    {
        $info = [];
        $bind = null;
        if (strlen($dn)) {
            $connect = $this->ldapConnect($server);
            if ($connect) {
                if ($server->getConfiguration()->getUser() && $server->getConfiguration()->getPassword()) {
                    $bind = $this->ldapBind($server, $connect, $server->getConfiguration()->getUser(), $server->getConfiguration()->getPassword());
                } else {
                    $bind = $this->ldapBbind($server, $connect);
                }
            }
            if ($bind) {
                $distinguishedName = Helpers::sanitizeQuery($dn);
                $info = $this->ldapSearch($server, $connect, $distinguishedName, '(objectClass=*)', 'base');
            }
        }

        return $info[0];
    }

    /**
     * finds usergroups -> getUsers().
     */
    public function getGroups(LdapServer $server, string $findname = '*', bool $doSanitize = false): array
    {
        $info = [];

        if ($server->getUserType() == 'be') {
            $table = 'be_users';
        } else {
            $table = 'fe_users';
        }

        if (strlen($findname)) {
            if ($doSanitize) {
                $findname = Helpers::sanitizeQuery($findname);
            }

            $baseDN = $server->getConfiguration()->getUserRules($table)->getGroupRules()->getBaseDN();
            $filter = $server->getConfiguration()->getUserRules($table)->getGroupRules()->getFilter();

            if (!empty($filter) && !empty($baseDN)) {
                $filter = str_replace('<search>', $findname, $filter);

                $msg = 'Query server: ' . $server->getConfiguration()->getUid() . ' with filter: ' . $filter;
                if (3 == $this->logLevel) {
                    $this->logger->debug($msg);
                }

                if (!empty($filter)) {
                    $connect = $this->ldapConnect($server);
                    if (!empty($connect)) {
                        if ($server->getConfiguration()->getUser() && $server->getConfiguration()->getPassword()) {
                            $bind = $this->ldapBind($server, $connect, $server->getConfiguration()->getUser(), $server->getConfiguration()->getPassword());
                        } else {
                            $bind = $this->ldapBind($server, $connect);
                        }
                    }
                    if (!empty($bind)) {
                        $info = $this->ldapSearch($server, $connect, $baseDN, $filter, 'sub');
                    }
                }
            }
        }

        return $info;
    }

    /**
     * checks the LDAP server connection.
     */
    public function checkConnection(LdapServer $server)
    {
        return $this->ldapConnect($server);
    }

    /**
     * checks the LDAP server binding.
     */
    public function checkBind(LdapServer $server, $connect = null): bool
    {
        $ret = false;
        $bind = null;
        if (empty($connect)) {
            $connect = $this->ldapConnect($server);
        }
        if ($server->getConfiguration()->getUser() && $server->getConfiguration()->getPassword()) {
            $bind = $this->ldapBind($server, $connect, $server->getConfiguration()->getUser(), $server->getConfiguration()->getPassword());
        } else {
            $bind = $this->ldapBind($server, $connect);
        }
        if ($bind) {
            $ret = true;
        }

        return $ret;
    }

    /**
     * checks user credentials by binding to the LDAP server.
     */
    public function authenticateUser(LdapServer $server, string $loginname, string $password): LdapFeUser | LdapBeUser | null
    {
        $user = null;
        $username = null;
        $serverUid = $server->getUid();
        $password = Helpers::sanitizeCredentials($password);

        $ldapUser = $this->checkUser($server, $loginname);

        if (is_object($ldapUser)) {
            $username = $ldapUser->getDN();
        }

        if (!empty($username) && !empty($password)) {
            $connect = $this->ldapConnect($server);
            $bind = $this->ldapBind($server, $connect, $username, $password, self::INFO);

            if ($bind) {
                $user = $ldapUser;
                if ($this->logLevel >= 2) {
                    $msg = 'User ' . $username . ' retrieved from LDAP directory (Server: ' . $serverUid . ')';
                    $this->logger->debug($msg);
                }
            } else {
                $msg = 'LDAP server denies authentication (Server: ' . $serverUid . ', User: ' . $username . ')';
                if ($this->logLevel >= 1) {
                    $this->logger->notice($msg);
                }
                Helpers::addError(self::WARNING, $msg, $serverUid);
            }
        }

        return $user;
    }

    /**
     * checks user existence.
     */
    public function checkUser(LDAPServer $server, string $loginname): LdapFeUser | LdapBeUser | null
    {
        $ldapUser = null;
        $serverUid = $server->getUid();
        $loginname = Helpers::sanitizeCredentials($loginname);

        $ldapUsers = $this->getUsers($server, $loginname);

        if (isset($ldapUsers) && (count($ldapUsers) < 1)) {
            $msg = 'No user found (Server: ' . $serverUid . ', User: ' . $loginname . ')';
            if ($this->logLevel >= 1) {
                $this->logger->notice($msg);
            }
            Helpers::addError(self::INFO, $msg, $serverUid);
        } elseif (isset($ldapUsers) && (count($ldapUsers) > 1)) {
            $msg = 'Found ' . count($ldapUsers) . ' instead of one (Server: ' . $serverUid . ', User: ' . $loginname . ')';
            if ($this->logLevel >= 1) {
                $this->logger->notice($msg);
            }
            Helpers::addError(self::INFO, $msg, $serverUid);
        } elseif (isset($ldapUsers)) {
            $ldapUsers->rewind();
            $ldapUser = $ldapUsers->current();
        }

        return $ldapUser;
    }

    /**
     * Retrieve the LDAP error message, if any.
     */
    protected function getLdapError($resource = null): string
    {
        $message = '';
        if (!empty($resource)) {
            $message = 'LDAP error #' . ldap_errno($resource) . ':  ' . ldap_error($resource);
        }

        return $message;
    }

    /**
     * connects to the LDAP server.
     */
    private function ldapConnect(LdapServer $server)
    {
        $connect = false;
        $uid = (int)$server->getUid();
        $host = $server->getConfiguration()->getHost() ?? '';
        $port = (int)$server->getConfiguration()->getPort();
        if (0 === strlen($port)) {
            $port = 389;
        }
        $version = (int)$server->getConfiguration()->getVersion();
        $forceTLS = (int)$server->getConfiguration()->getForceTLS();

        $ldapProtocol = $port === 636 || $forceTLS ? 'ldaps' : 'ldap';
        $ldapUri = $ldapProtocol . '://' . $host . ':' . $port;

        try {
            $connect = ldap_connect($ldapUri);
        } catch (Exception $e) {
            $msg = 'ldap_connect(' . $uid . ', ' . $host . ':' . $port . '): Could not connect to LDAP server.';
            $this->logger->error($msg);
            Helpers::addError(self::ERROR, $msg, $uid);
        }

        if ($connect) {
            if (3 == $version) {
                try {
                    ldap_set_option($connect, LDAP_OPT_PROTOCOL_VERSION, 3);
                } catch (Exception $e) {
                    $msg = 'Protocol version cannot be set to 3.';
                    $this->logger->error($msg);
                    Helpers::addError(self::ERROR, $msg);
                }
            }
            if ($forceTLS) {
                if (function_exists('ldap_start_tls')) {
                    try {
                        ldap_start_tls($connect);
                    } catch (Exception $e) {
                        $msg = 'function_exists("ldap_start_tls"): Function ldap_start_tls not available.';
                        $this->logger->error($msg);
                        Helpers::addError(self::ERROR, $msg);
                    }
                } else {
                    $msg = 'function_exists("ldap_start_tls"): Function ldap_start_tls not available.';
                    $this->logger->error($msg);
                    Helpers::addError(self::ERROR, $msg);
                }
            }

            try {
                ldap_set_option($connect, LDAP_OPT_REFERRALS, 0);
            } catch (Exception $e) {
                $msg = 'ldap_connect(' . $uid . ', ' . $host . ':' . $port . '): Could not connect to LDAP server.';
                $this->logger->error($msg);
                Helpers::addError(self::ERROR, $msg, $uid);
            }
        } else {
            $msg = 'ldap_connect(' . $uid . ', ' . $host . ':' . $port . '): Could not connect to LDAP server.';
            $this->logger->error($msg);
            Helpers::addError(self::ERROR, $msg, $uid);
        }

        return $connect;
    }

    /**
     * Binds to the LDAP server.
     */
    private function ldapBind(LdapServer $server, $conn, string $user = '', string $pass = '', int $warnLevel = self::ERROR)
    {
        $bind = null;
        $uid = $server->getUid();
        $host = $server->getConfiguration()->getHost();
        $port = $server->getConfiguration()->getPort();

        try {
            ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 2);
            if ($user && $pass) {
                $bind = @ldap_bind($conn, $user, $pass);
            } else {
                $bind = @ldap_bind($conn);
            }
        } catch (Exception $e) {
            if ($this->logLevel >= 2) {
                $msg = 'ldap_bind(' . $host . ':' . $port . ', ' . $user . ', ' . $pass . '): Could not bind to LDAP server.';
                $this->logger->error($msg);
            } else {
                $msg = 'ldap_bind(' . $host . ':' . $port . ', ' . $user . ', ***): Could not bind to LDAP server.';
                $this->logger->error($msg);
            }
            $msg = 'ldap_bind(' . $host . ':' . $port . ', ' . $user . ', ***): Could not bind to LDAP server.';
            Helpers::addError($warnLevel, $msg, $uid);
        }

        if (!$bind) {
            if ($this->logLevel >= 2) {
                $msg = 'ldap_bind(' . $host . ':' . $port . ', ' . $user . ', ' . $pass . '): Could not bind to LDAP server.';
            } else {
                $msg = 'ldap_bind(' . $host . ':' . $port . ', ' . $user . ', ***): Could not bind to LDAP server.';
            }
            $this->logger->error($msg);
            Helpers::addError($warnLevel, $msg, $uid);
        }

        return $bind;
    }

    /**
     * searches the LDAP server.
     */
    private function ldapSearch(LdapServer $server, $ds, string $baseDN, string $filter, string $scope = 'sub'): array
    {
        $search = null;
        $sizeLimit = 0;

        // sometimes the attribute filter does not seem to work => disable it
        $attributes = [];

        if ($server->getLimitLdapResults()) {
            $sizeLimit = $server->getLimitLdapResults();
        }

        switch ($scope) {
            case 'base':
                try {
                    $search = @ldap_read($ds, $baseDN, $filter);
                } catch (Exception $e) {
                    $msg = '"ldap_read" failed';
                    if ($this->logLevel) {
                        $this->logger->error($msg);
                    }
                }

                break;

            case 'one':
                try {
                    $search = @ldap_list($ds, $baseDN, $filter);
                } catch (Exception $e) {
                    $msg = '"ldap_list" failed';
                    if ($this->logLevel) {
                        $this->logger->error($msg);
                    }
                }

                break;

            case 'sub':
            default:
                try {
                    $search = @ldap_search($ds, $baseDN, $filter);
                } catch (Exception $e) {
                    $msg = '"ldap_search" failed';
                    if ($this->logLevel) {
                        $this->logger->error($msg);
                    }
                }
        }

        $ret = [];
        $i = 0;

        $sizeLimitErrorNumber = 4;
        $knownSizeLimitErrors = [
            'SIZE LIMIT EXCEEDED',
            'SIZELIMIT EXCEEDED',
        ];

        if ((ldap_errno($ds) == $sizeLimitErrorNumber) || in_array(strtoupper(ldap_error($ds)), $knownSizeLimitErrors)) {
            // throw it away, since it's incomplete
            ldap_free_result($search);
            $i = -1;
        } elseif (ldap_errno($ds)) {
            $msg = 'LDAP error: ' . ldap_errno($ds) . ', ' . ldap_error($ds);
            $logArray = [
                'DS' => $ds,
                'BaseDN' => $baseDN,
                'Filter' => $filter,
            ];
            if ($this->logLevel) {
                $this->logger->error($msg, $logArray);
            }
        } else {
            // Get the first entry identifier
            $entryID = ldap_first_entry($ds, $search);

            if ($entryID) {
                // Iterate over the entries
                while ($entryID && (($i < $sizeLimit) || (0 == $sizeLimit))) {
                    // Get the distinguished name of the entry
                    $dn = ldap_get_dn($ds, $entryID);

                    $ret[$i]['dn'] = $dn;
                    // Get the attributes of the entry
                    $ldapAttributes = ldap_get_attributes($ds, $entryID);

                    // Iterate over the attributes
                    foreach ($ldapAttributes as $attribute => $values) {
                        // Get the number of values for this attribute
                        $count = 0;
                        if (is_array($values)) {
                            $count = $values['count'];
                        }
                        if (1 == $count) {
                            $ret[$i][strtolower($attribute)] = $values[0];
                        } elseif ($count > 1) {
                            $ret[$i][strtolower($attribute)] = $values;
                        }
                    }
                    $entryID = ldap_next_entry($ds, $entryID);
                    ++$i;
                }
            }

            @ldap_free_result($search);
        }

        $ret['count'] = $i;

        return $ret;
    }
}
