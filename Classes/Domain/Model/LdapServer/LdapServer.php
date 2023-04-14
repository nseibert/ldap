<?php

namespace NormanSeibert\Ldap\Domain\Model\LdapServer;

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
use NormanSeibert\Ldap\Domain\Model\LdapUser\LdapUser;
use NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfiguration;
use NormanSeibert\Ldap\Domain\Model\Configuration\LdapConfiguration;
use NormanSeibert\Ldap\Utility\Helpers;
use SplObjectStorage;
use Psr\Log\LoggerInterface;

/**
 * Model for an LDAP server.
 */
class LdapServer extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    private LoggerInterface $logger;

    const ERROR = 2;
    const WARNING = 1;
    const OK = 0;
    const INFO = -1;
    const NOTICE = -2;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var int
     */
    protected $limitLdapResults = 0;

    /**
     * @var ServerConfiguration
     */
    protected $serverConfiguration;

    /**
     * @var LdapConfiguration
     */
    protected $ldapConfiguration;

    /**
     * @var array
     */
    protected $allBeGroups = [];

    /**
     * @var array
     */
    protected $allFeGroups = [];

    /**
     * @var int
     */
    protected $uid;

    /**
     * @var int
     */
    protected $logLevel;

    /**
     * @var EventDispatcherInterface
     */
    // protected $eventDispatcher;
    
/*
    public function injectEventDispatcherInterface(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }
*/

    public function __construct(
        LoggerInterface $logger,
        LdapConfiguration $configuration
    )
    {
        $this->ldapConfiguration = $configuration;
        $this->logger = $logger;
    }

    /**
     * @return LdapServer
     */
    public function setlogLevel(int $logLevel)
    {
        $this->logLevel = $logLevel;

        return $this;
    }

    /**
     * @return int
     */
    public function getLogLevel(): ?int
    {
        return $this->logLevel;
    }

    /**
     * @return LdapServer
     */
    public function setUid(int $uid)
    {
        $this->uid = $uid;

        return $this;
    }

    /**
     * @return int
     */
    public function getUid(): ?int
    {
        return $this->uid;
    }

    /**
     * @param ServerConfiguration $config
     *
     * @return LdapServer
     */
    public function setConfiguration(ServerConfiguration $config)
    {
        $this->serverConfiguration = $config;
        $this->uid = $config->getUid();

        return $this;
    }

    /**
     * @return ServerConfiguration
     */
    public function getConfiguration()
    {
        return $this->serverConfiguration;
    }

    /**
     * finds users based on a text attribute, typically the username.
     *
     * @param string $findname
     * @param bool   $doSanitize
     *
     * @return SplObjectStorage
     */
    public function getUsers($findname = '*', $doSanitize = false)
    {
        $users = null;
        $info = [];
        $bind = null;
        $connect = null;
        $filter = null;
        $baseDN = null;

        if (strlen($findname)) {
            if ($doSanitize) {
                $findname = Helpers::sanitizeQuery($findname);
            }
            $baseDN = $this->getConfiguration()->getUserRules($this->table)->getBaseDN();
            $filter = $this->getConfiguration()->getUserRules($this->table)->getFilter();
        }

        if (!empty($filter) && !empty($baseDN)) {
            $connect = $this->connect();
        } else {
            $msg = 'No baseDN or no filter given.';
            $this->logger->notice($msg);
        }

        if ($connect) {
            if ($this->getConfiguration()->getUser() && $this->getConfiguration()->getPassword()) {
                $bind = $this->bind($connect, $this->getConfiguration()->getUser(), $this->getConfiguration()->getPassword());
            } else {
                $bind = $this->bind($connect);
            }
        }

        if ($bind) {
            $parsedFilter = str_replace('<search>', $findname, $filter);
            if (3 == $this->logLevel) {
                $msg = 'Query server "'.$this->getConfiguration()->getUid();
                $logArray = [
                    'baseDN' => $baseDN,
                    'filter' => $parsedFilter,
                ];
                $this->logger->debug($msg, $logArray);
            }

            $info = $this->search($connect, $baseDN, $parsedFilter, 'sub');
        } else {
            $msg = 'Bind failed.';
            $this->logger->notice($msg);
        }

        if (isset($info['count']) && ($info['count'] >= 0)) {
            $parameters = [
                'server' => $this,
                'find' => $findname,
                'table' => $this->table,
                'type' => 'list',
                'result' => $info,
            ];
            // $this->eventDispatcher->dispatch(__CLASS__, 'getUsersResults', $parameters);

            $users = new SplObjectStorage();
            
            for ($i = 0; $i < $info['count']; ++$i) {
                if ('be_users' == $this->table) {
                    $user = GeneralUtility::makeInstance(LdapBeUser::class);
                } else {
                    $user = GeneralUtility::makeInstance(LdapFeUser::class);
                }

                $user->setDN($info[$i]['dn']);
                $user->setAttributes($info[$i]);
                $user->setLdapServer($this);
                
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
     *
     * @param string $dn
     * @param bool   $doSanitize
     *
     * @return LdapUser
     */
    public function getUser($dn, $doSanitize = false)
    {
        $bind = null;
        $connect = null;
        $user = null;
        $distinguishedName = null;
        $filter = null;
        $parsedFilter = null;

        //TODO: findet die User ggf. auch ueber andere Server als den, ueber den urspruenglich importiert wurde.
        $info = [];
        if (strlen($dn)) {
            $connect = $this->connect();
        }
        if ($connect) {
            if ($this->getConfiguration()->getUser() && $this->getConfiguration()->getPassword()) {
                $bind = $this->bind($connect, $this->getConfiguration()->getUser(), $this->getConfiguration()->getPassword());
            } else {
                $bind = $this->bind($connect);
            }
        }
        if ($bind) {
            if ($doSanitize) {
                $distinguishedName = Helpers::sanitizeQuery($dn);
            } else {
                $distinguishedName = $dn;
            }
            $filter = $this->getConfiguration()->getUserRules($this->table)->getFilter();
            $parsedFilter = str_replace('<search>', '*', $filter);
            $info = $this->search($connect, $distinguishedName, $parsedFilter, 'base');
        } else {

        }

        $parameters = [
            'server' => $this,
            'dn' => $dn,
            'table' => $this->table,
            'type' => 'single',
            'result' => $info,
        ];
        //$this->eventDispatcher->dispatch(__CLASS__, 'getUserResults', $parameters);

        if (1 == $info['count']) {
            if ('be_users' == $this->table) {
                $user = GeneralUtility::makeInstance(LdapBeUser::class);
            } else {
                $user = GeneralUtility::makeInstance(LdapFeUser::class);
            }
        }

        if (is_object($user)) {
            $user
                ->setDN($distinguishedName)
                ->setAttributes($info[0])
                ->setLdapServer($this)
            ;

            $msg = 'Found record: '.$distinguishedName;
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
     *
     * @param string $dn
     *
     * @return array
     */
    public function getGroup($dn)
    {
        $info = [];
        $bind = null;
        if (strlen($dn)) {
            $connect = $this->connect();
            if ($connect) {
                if ($this->getConfiguration()->getUser() && $this->getConfiguration()->getPassword()) {
                    $bind = $this->bind($connect, $this->getConfiguration()->getUser(), $this->getConfiguration()->getPassword());
                } else {
                    $bind = $this->bind($connect);
                }
            }
            if ($bind) {
                $distinguishedName = Helpers::sanitizeQuery($dn);
                $info = $this->search($connect, $distinguishedName, '(objectClass=*)', 'base');
            }
        }

        return $info[0];
    }

    /**
     * finds usergroups -> getUsers().
     *
     * @param string $findname
     * @param bool   $doSanitize
     *
     * @return array
     */
    public function getGroups($findname = '*', $doSanitize = false)
    {
        $info = [];
        if (strlen($findname)) {
            if ($doSanitize) {
                $findname = Helpers::sanitizeQuery($findname);
            }

            $baseDN = $this->getConfiguration()->getUserRules($this->table)->getGroupRules()->getBaseDN();
            $filter = $this->getConfiguration()->getUserRules($this->table)->getGroupRules()->getFilter();

            if (!empty($filter) && !empty($baseDN)) {
                $filter = str_replace('<search>', $findname, $filter);

                $msg = 'Query server: ' . $this->getConfiguration()->getUid() . ' with filter: ' . $filter;
                if (3 == $this->logLevel) {
                    $this->logger->debug($msg);
                }

                if (!empty($filter)) {
                    $connect = $this->connect();
                    if (!empty($connect)) {
                        if ($this->getConfiguration()->getUser() && $this->getConfiguration()->getPassword()) {
                            $bind = $this->bind($connect, $this->getConfiguration()->getUser(), $this->getConfiguration()->getPassword());
                        } else {
                            $bind = $this->bind($connect);
                        }
                    }
                    if (!empty($bind)) {
                        $info = $this->search($connect, $baseDN, $filter, 'sub');
                    }
                }
            }
        }

        return $info;
    }

    /**
     * gets the filter for all queries to FE or BE.
     */
    public function getUserType(): string
    {
        if ('be_users' == $this->table) {
            $userType = 'be';
        } else {
            $userType = 'fe';
        }

        return $userType;
    }

    /**
     * sets the filter for all queries to FE or BE.
     */
    public function setUserType(string $userType = 'fe', int $pid = null): LdapServer
    {
        if (is_int($pid)) {
            $this->pid = $pid;
        } else {
            unset($this->pid);
        }
        if ('be' == $userType) {
            $this->table = 'be_users';
        } else {
            $this->table = 'fe_users';
        }

        return $this;
    }

    /**
     * checks the LDAP server connection.
     *
     * @return resource
     */
    public function checkConnection()
    {
        return $this->connect();
    }

    /**
     * checks the LDAP server binding.
     *
     * @param $connect
     *
     * @return bool
     */
    public function checkBind($connect = null)
    {
        $ret = false;
        $bind = null;
        if (empty($connect)) {
            $connect = $this->connect();
        }
        if ($this->getConfiguration()->getUser() && $this->getConfiguration()->getPassword()) {
            $bind = $this->bind($connect, $this->getConfiguration()->getUser(), $this->getConfiguration()->getPassword());
        } else {
            $bind = $this->bind($connect);
        }
        if ($bind) {
            $ret = true;
        }

        return $ret;
    }

    /**
     * checks user credentials by binding to the LDAP server.
     */
    public function authenticateUser(string $loginname, string $password): LdapFeUser | LdapBeUser | null
    {
        $user = null;
        $username = null;
        $serverUid = $this->getUid();
        $password = Helpers::sanitizeCredentials($password);

        $ldapUser = $this->checkUser($loginname);

        if (is_object($ldapUser)) {
            $username = $ldapUser->getDN();
        }

        if (!empty($username) && !empty($password)) {
            $connect = $this->connect();
            $bind = $this->bind($connect, $username, $password, self::INFO);

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
    public function checkUser(string $loginname): LdapFeUser | LdapBeUser | null
    {
        $ldapUser = null;
        $serverUid = $this->getUid();
        $loginname = Helpers::sanitizeCredentials($loginname);

        $ldapUsers = $this->getUsers($loginname);

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
     * @param int $limit
     *
     * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
     */
    public function setLimitLdapResults($limit)
    {
        $this->limitLdapResults = $limit;

        return $this;
    }

    /**
     * @return int
     */
    public function getLimitLdapResults()
    {
        return $this->limitLdapResults;
    }

    public function addFeGroup($group)
    {
        $this->allFeGroups[] = $group;
    }

    /**
     * @return array
     */
    public function getAllFeGroups()
    {
        return $this->allFeGroups;
    }

    public function addBeGroup($group)
    {
        $this->allBeGroups[] = $group;
    }

    /**
     * @return array
     */
    public function getAllBeGroups()
    {
        return $this->allBeGroups;
    }

    /**
     * @param string $type
     */
    public function addGroup($group, $type)
    {
        if ('NormanSeibert\\Ldap\\Domain\\Model\\Typo3User\\BackendUserGroup' == $type) {
            $this->addBeGroup($group);
        } else {
            $this->addFeGroup($group);
        }
    }

    /**
     * @return array
     */
    public function getAllGroups()
    {
        if ('be_users' == $this->table) {
            $groups = $this->allBeGroups;
        } else {
            $groups = $this->allFeGroups;
        }

        return $groups;
    }

    /**
     * Retrieve the LDAP error message, if any.
     *
     * @param resource $resource an LDAP connection object
     *
     * @return string the error message string (if any) that exists on the connection
     */
    protected function getLdapError($resource = null)
    {
        $message = '';
        if (!empty($resource)) {
            $message = 'LDAP error #'.ldap_errno($resource).':  '.ldap_error($resource);
        }

        return $message;
    }

    /**
     * compiles a list of mapped attributes from configuration.
     *
     * @return array
     */
    private function getUsedAttributes()
    {
        $attr = [];

        $userMapping = $this->getConfiguration()->getUserRules($this->table)->getMapping();
        if (is_array($userMapping)) {
            foreach ($userMapping as $field => $value) {
                if (isset($value['data'])) {
                    $attr[] = str_replace('field:', '', $value['data']);
                } elseif (isset($value['value'])) {
                    // Everything OK
                } else {
                    $msg = 'Mapping for attribute "'.$this->table.'.mapping.'.$field.'" incorrect.';
                    $this->logger->warning($msg);
                }
            }
        }

        $groupMapping = $this->getConfiguration()->getUserRules($this->table)->getGroupRules()->getMapping();
        if (is_array($groupMapping)) {
            foreach ($groupMapping as $field => $value) {
                if (isset($value['data'])) {
                    $attr[] = str_replace('field:', '', $value['data']);
                } elseif (isset($value['value'])) {
                    // Everything OK
                } elseif ('field' != $field) {
                    $msg = 'Mapping for attribute "'.$this->table.'.usergroups.mapping.'.$field.'" incorrect.';
                    $this->logger->warning($msg);
                }
            }
        }

        return array_unique($attr);
    }

    /**
     * connects to the LDAP server.
     *
     * @return resource
     */
    private function connect()
    {
        $connect = false;
        $uid = $this->getConfiguration()->getUid();
        $host = $this->getConfiguration()->getHost();
        $port = $this->getConfiguration()->getPort();
        if (0 == strlen($port)) {
            $port = 389;
        }
        $version = $this->getConfiguration()->getVersion();
        $forceTLS = $this->getConfiguration()->getForceTLS();

        try {
            $connect = ldap_connect($host, $port);
        } catch (Exception $e) {
            $msg = 'ldap_connect('.$uid.', '.$host.':'.$port.'): Could not connect to LDAP server.';
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
                $msg = 'ldap_connect('.$uid.', '.$host.':'.$port.'): Could not connect to LDAP server.';
                $this->logger->error($msg);
                Helpers::addError(self::ERROR, $msg, $uid);
            }
        } else {
            $msg = 'ldap_connect('.$uid.', '.$host.':'.$port.'): Could not connect to LDAP server.';
            $this->logger->error($msg);
            Helpers::addError(self::ERROR, $msg, $uid);
        }

        return $connect;
    }

    /**
     * Binds to the LDAP server.
     *
     * @param resource $conn
     * @param string   $user
     * @param string   $pass
     * @param int      $warnLevel
     *
     * @return resource
     */
    private function bind($conn, $user = '', $pass = '', $warnLevel = self::ERROR)
    {
        $bind = null;
        $uid = $this->getConfiguration()->getUid();
        $host = $this->getConfiguration()->getHost();
        $port = $this->getConfiguration()->getPort();

        try {
            ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 2);
            if ($user && $pass) {
                $bind = @ldap_bind($conn, $user, $pass);
            } else {
                $bind = @ldap_bind($conn);
            }
        } catch (Exception $e) {
            if ($this->logLevel >= 2) {
                $msg = 'ldap_bind('.$host.':'.$port.', '.$user.', '.$pass.'): Could not bind to LDAP server.';
                $this->logger->error($msg);
            } else {
                $msg = 'ldap_bind('.$host.':'.$port.', '.$user.', ***): Could not bind to LDAP server.';
                $this->logger->error($msg);
            }
            $msg = 'ldap_bind('.$host.':'.$port.', '.$user.', ***): Could not bind to LDAP server.';
            Helpers::addError($warnLevel, $msg, $uid);
        }

        if (!$bind) {
            if ($this->logLevel >= 2) {
                $msg = 'ldap_bind('.$host.':'.$port.', '.$user.', '.$pass.'): Could not bind to LDAP server.';
            } else {
                $msg = 'ldap_bind('.$host.':'.$port.', '.$user.', ***): Could not bind to LDAP server.';
            }
            $this->logger->error($msg);
            Helpers::addError($warnLevel, $msg, $uid);
        }

        return $bind;
    }

    /**
     * searches the LDAP server.
     *
     * @param resource $ds
     * @param string   $baseDN
     * @param string   $filter
     * @param string   $scope
     *
     * @return array
     */
    private function search($ds, $baseDN, $filter, $scope = 'sub')
    {
        $search = null;
        $sizeLimit = 0;

        // sometimes the attribute filter does not seem to work => disable it
        $attributes = [];

        if ($this->limitLdapResults) {
            $sizeLimit = $this->limitLdapResults;
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
            $msg = 'LDAP error: '.ldap_errno($ds).', '.ldap_error($ds);
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
