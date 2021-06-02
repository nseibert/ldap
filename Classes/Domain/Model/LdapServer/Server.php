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

use NormanSeibert\Ldap\Domain\Model\Configuration\Configuration;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserGroupRepository;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\FrontendUserGroupRepository;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

/**
 * Model for an LDAP server.
 */
class Server extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity implements \Psr\Log\LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var int
     */
    protected $limitLdapResults = 0;

    /**
     * @var Configuration
     */
    protected $ldapConfig;

    /**
     * @var \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfiguration
     */
    protected $configuration;

    /**
     * @var FrontendUserGroupRepository
     */
    protected $feUsergroupRepository;

    /**
     * @var BackendUserGroupRepository
     */
    protected $beUsergroupRepository;

    /**
     * @var \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUserGroup
     */
    protected $allBeGroups;

    /**
     * @var \NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUserGroup
     */
    protected $allFeGroups;

    /**
     * @var int
     */
    protected $uid;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var Dispatcher
     */
    protected $signalSlotDispatcher;

    /**
     * @param Configuration ldapConfig
     */
    public function __construct(Configuration $ldapConfig, FrontendUserGroupRepository $feUsergroupRepository, BackendUserGroupRepository $beUsergroupRepository, ObjectManager $objectManager, Dispatcher $signalSlotDispatcher)
    {
        $this->logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        $this->ldapConfig = $ldapConfig;
        $this->feUsergroupRepository = $feUsergroupRepository;
        $this->beUsergroupRepository = $beUsergroupRepository;
        $this->objectManager = $objectManager;
        $this->signalSlotDispatcher = $signalSlotDispatcher;
    }

    /**
     * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
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
     * @param \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfiguration $config
     *
     * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
     */
    public function setConfiguration(ServerConfiguration $config)
    {
        $this->configuration = $config;
        $this->uid = $config->getUid();

        return $this;
    }

    /**
     * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfiguration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * finds users based on a text attribute, typically the username.
     *
     * @param string $findname
     * @param bool   $doSanitize
     *
     * @return array
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
                $findname = \NormanSeibert\Ldap\Utility\Helpers::sanitizeQuery($findname);
            }
            $baseDN = $this->getConfiguration()->getUserRules($this->table)->getBaseDN();
            $filter = $this->getConfiguration()->getUserRules($this->table)->getFilter();
        }

        if (!empty($filter) && !empty($baseDN)) {
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
            $parsedFilter = str_replace('<search>', $findname, $filter);
            if (3 == $this->ldapConfig->logLevel) {
                $msg = 'Query server "'.$this->getConfiguration()->getUid();
                $logArray = [
                    'baseDN' => $baseDN,
                    'filter' => $parsedFilter,
                ];
                $this->logger->debug($msg, $logArray);
            }

            $info = $this->search($connect, $baseDN, $parsedFilter, 'sub');
        }

        if ($info['count'] >= 0) {
            $parameters = [
                'server' => $this,
                'find' => $findname,
                'table' => $this->table,
                'type' => 'list',
                'result' => $info,
            ];
            $this->signalSlotDispatcher->dispatch(__CLASS__, 'getUsersResults', $parameters);

            $users = [];
            for ($i = 0; $i < $info['count']; ++$i) {
                if ('be_users' == $this->table) {
                    $user = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\LdapUser\\BeUser');
                } else {
                    $user = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\LdapUser\\FeUser');
                }
                $user
                    ->setDN($info[$i]['dn'])
                    ->setAttributes($info[$i])
                    ->setLdapServer($this)
                ;
                $users[] = $user;
            }
        }

        $msg = 'Found '.$info['count'].' records';
        if (3 == $this->ldapConfig->logLevel) {
            $this->logger->debug($msg);
        }

        return $users;
    }

    /**
     * finds a single user by its DN.
     *
     * @param string $dn
     * @param bool   $doSanitize
     *
     * @return \NormanSeibert\Ldap\Domain\Model\LdapUser\User
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
                $distinguishedName = \NormanSeibert\Ldap\Utility\Helpers::sanitizeQuery($dn);
            } else {
                $distinguishedName = $dn;
            }
            $filter = $this->getConfiguration()->getUserRules($this->table)->getFilter();
            $parsedFilter = str_replace('<search>', '*', $filter);
            $info = $this->search($connect, $distinguishedName, $parsedFilter, 'base');
        }

        $parameters = [
            'server' => $this,
            'dn' => $dn,
            'table' => $this->table,
            'type' => 'single',
            'result' => $info,
        ];
        $this->signalSlotDispatcher->dispatch(__CLASS__, 'getUserResults', $parameters);

        if (1 == $info['count']) {
            if ('be_users' == $this->table) {
                $user = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\LdapUser\\BeUser');
            } else {
                $user = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\LdapUser\\FeUser');
            }
        }

        if (is_object($user)) {
            $user
                ->setDN($distinguishedName)
                ->setAttributes($info[0])
                ->setLdapServer($this)
            ;

            $msg = 'Found record: '.$distinguishedName;
            if ($this->ldapConfig->logLevel >= 2) {
                $this->logger->info($msg);
            }
        } else {
            $msg = 'Did not find a unique record for the user DN='.$dn.', but found '.$info['count'].' records instead.';
            if ($this->ldapConfig->logLevel >= 2) {
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
                $distinguishedName = \NormanSeibert\Ldap\Utility\Helpers::sanitizeQuery($dn);
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
                $findname = \NormanSeibert\Ldap\Utility\Helpers::sanitizeQuery($findname);
            }

            $baseDN = $this->getConfiguration()->getUserRules($this->table)->getGroupRules()->getBaseDN();
            $filter = $this->getConfiguration()->getUserRules($this->table)->getGroupRules()->getFilter();

            if (!empty($filter) && !empty($baseDN)) {
                $filter = str_replace('<search>', $findname, $filter);

                $msg = 'Query server: '.$this->getConfiguration()->getUid().' with filter: '.$filter;
                if (3 == $this->ldapConfig->logLevel) {
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
     * sets the filter for all queries to FE or BE.
     *
     * @param string $scope
     * @param int    $pid
     *
     * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
     */
    public function setScope($scope = 'fe', $pid = null)
    {
        if (is_int($pid)) {
            $this->pid = $pid;
        } else {
            unset($this->pid);
        }
        if ('be' == $scope) {
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
     *
     * @param string $loginname
     * @param string $password
     *
     * @return array
     */
    public function authenticateUser($loginname, $password)
    {
        $user = null;
        $username = null;
        $serverUid = $this->getConfiguration()->getUid();
        $password = \NormanSeibert\Ldap\Utility\Helpers::sanitizeCredentials($password);

        // @var $ldapUser \NormanSeibert\Ldap\Domain\Model\LdapUser\User
        $ldapUser = $this->checkUser($loginname);

        if (is_object($ldapUser)) {
            $username = $ldapUser->getDN();
        }

        if (!empty($username) && !empty($password)) {
            $connect = $this->connect();
            $bind = $this->bind($connect, $username, $password, \TYPO3\CMS\Core\Messaging\FlashMessage::INFO);

            if ($bind) {
                $user = $ldapUser;
                if ($this->ldapConfig->logLevel >= 2) {
                    $msg = 'User '.$username.' retrieved from LDAP directory (Server: '.$serverUid.')';
                    $this->logger->debug($msg);
                }
            } else {
                $msg = 'LDAP server denies authentication (Server: '.$serverUid.', User: '.$username.')';
                if ($this->ldapConfig->logLevel >= 1) {
                    $this->logger->notice($msg);
                }
                \NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING, $msg, $serverUid);
            }
        }

        return $user;
    }

    /**
     * checks user existence.
     *
     * @param string $loginname
     *
     * @return array
     */
    public function checkUser($loginname)
    {
        $ldapUser = null;
        $serverUid = $this->getConfiguration()->getUid();
        $loginname = \NormanSeibert\Ldap\Utility\Helpers::sanitizeCredentials($loginname);

        $ldapUsers = $this->getUsers($loginname);

        if (count($ldapUsers) < 1) {
            $msg = 'No user found (Server: '.$serverUid.', User: '.$loginname.')';
            if ($this->ldapConfig->logLevel >= 1) {
                $this->logger->notice($msg);
            }
            \NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::INFO, $msg, $serverUid);
        } elseif (count($ldapUsers) > 1) {
            $msg = 'Found '.count($ldapUsers).' instead of one (Server: '.$serverUid.', User: '.$loginname.')';
            if ($this->ldapConfig->logLevel >= 1) {
                $this->logger->notice($msg);
            }
            \NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::INFO, $msg, $serverUid);
        } else {
            $ldapUser = $ldapUsers[0];
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

    public function loadAllGroups()
    {
        if ('be_users' == $this->table) {
            $this->allBeGroups = $this->beUsergroupRepository->findAll();
        } else {
            $pid = null;
            $groupRules = $this->getConfiguration()->getUserRules('fe_users')->getGroupRules();
            if ($groupRules) {
                $pid = $groupRules->getPid();
            }
            if (empty($pid)) {
                $pid = $this->getConfiguration()->getUserRules('fe_users')->getPid();
            }
            if ($pid) {
                $this->allFeGroups = $this->feUsergroupRepository->findByPid($pid);
            } else {
                $this->allFeGroups = $this->feUsergroupRepository->findAll();
            }
        }
    }

    public function addFeGroup(\NormanSeibert\Ldap\Domain\Model\Typo3User\UserGroupInterface $group)
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

    public function addBeGroup(\NormanSeibert\Ldap\Domain\Model\Typo3User\UserGroupInterface $group)
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
    public function addGroup(\NormanSeibert\Ldap\Domain\Model\Typo3User\UserGroupInterface $group, $type)
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
            // @extensionScannerIgnoreLine
            $this->logger->error($msg);
            \NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR, $msg, $uid);
        }

        if ($connect) {
            if (3 == $version) {
                try {
                    ldap_set_option($connect, LDAP_OPT_PROTOCOL_VERSION, 3);
                } catch (Exception $e) {
                    $msg = 'Protocol version cannot be set to 3.';
                    // @extensionScannerIgnoreLine
                    $this->logger->error($msg);
                    \NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR, $msg);
                }
            }
            if ($forceTLS) {
                if (function_exists('ldap_start_tls')) {
                    try {
                        ldap_start_tls($connect);
                    } catch (Exception $e) {
                        $msg = 'function_exists("ldap_start_tls"): Function ldap_start_tls not available.';
                        // @extensionScannerIgnoreLine
                        $this->logger->error($msg);
                        \NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR, $msg);
                    }
                } else {
                    $msg = 'function_exists("ldap_start_tls"): Function ldap_start_tls not available.';
                    // @extensionScannerIgnoreLine
                    $this->logger->error($msg);
                    \NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR, $msg);
                }
            }

            try {
                ldap_set_option($connect, LDAP_OPT_REFERRALS, 0);
            } catch (Exception $e) {
                $msg = 'ldap_connect('.$uid.', '.$host.':'.$port.'): Could not connect to LDAP server.';
                // @extensionScannerIgnoreLine
                $this->logger->error($msg);
                \NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR, $msg, $uid);
            }
        } else {
            $msg = 'ldap_connect('.$uid.', '.$host.':'.$port.'): Could not connect to LDAP server.';
            // @extensionScannerIgnoreLine
            $this->logger->error($msg);
            \NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR, $msg, $uid);
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
    private function bind($conn, $user = '', $pass = '', $warnLevel = \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR)
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
            if ($this->ldapConfig->logLevel >= 2) {
                $msg = 'ldap_bind('.$host.':'.$port.', '.$user.', '.$pass.'): Could not bind to LDAP server.';
                // @extensionScannerIgnoreLine
                $this->logger->error($msg);
            } else {
                $msg = 'ldap_bind('.$host.':'.$port.', '.$user.', ***): Could not bind to LDAP server.';
                // @extensionScannerIgnoreLine
                $this->logger->error($msg);
            }
            $msg = 'ldap_bind('.$host.':'.$port.', '.$user.', ***): Could not bind to LDAP server.';
            \NormanSeibert\Ldap\Utility\Helpers::addError($warnLevel, $msg, $uid);
        }

        if (!$bind) {
            if ($this->ldapConfig->logLevel >= 2) {
                $msg = 'ldap_bind('.$host.':'.$port.', '.$user.', '.$pass.'): Could not bind to LDAP server.';
            } else {
                $msg = 'ldap_bind('.$host.':'.$port.', '.$user.', ***): Could not bind to LDAP server.';
            }
            // @extensionScannerIgnoreLine
            $this->logger->error($msg);
            \NormanSeibert\Ldap\Utility\Helpers::addError($warnLevel, $msg, $uid);
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
                    if ($this->ldapConfig->logLevel) {
                        // @extensionScannerIgnoreLine
                        $this->logger->error($msg);
                    }
                }

                break;

            case 'one':
                try {
                    $search = @ldap_list($ds, $baseDN, $filter);
                } catch (Exception $e) {
                    $msg = '"ldap_list" failed';
                    if ($this->ldapConfig->logLevel) {
                        // @extensionScannerIgnoreLine
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
                    if ($this->ldapConfig->logLevel) {
                        // @extensionScannerIgnoreLine
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
            if ($this->ldapConfig->logLevel) {
                // @extensionScannerIgnoreLine
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
        }

        $ret['count'] = $i;

        @ldap_free_result($search);

        return $ret;
    }
}
