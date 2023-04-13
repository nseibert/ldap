<?php

namespace NormanSeibert\Ldap\Domain\Service;

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

use NormanSeibert\Ldap\Domain\Repository\Typo3User\FrontendUserGroupRepository;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserGroupRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use NormanSeibert\Ldap\Domain\Model\LdapUser\BeUser;
use NormanSeibert\Ldap\Domain\Model\LdapUser\FeUser;
use NormanSeibert\Ldap\Domain\Model\LdapServer;
use NormanSeibert\Ldap\Utility\Helpers;
use SplObjectStorage;
use Psr\Log\LoggerInterface;


class LdapUserManager
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

    public function __construct(
        LoggerInterface $logger,
        FrontendUserGroupRepository $feUsergroupRepository,
        BackendUserGroupRepository $beUsergroupRepository
    )
    {
        $this->feUsergroupRepository = $feUsergroupRepository;
        $this->beUsergroupRepository = $beUsergroupRepository;
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
     * finds users based on a text attribute, typically the username.
     */
    public function findUsers(LdapServer $server, string $userType = 'fe_user', string $findname = '*', bool $doSanitize = false): SplObjectstorage
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
            $baseDN = $server->getConfiguration()->getUserRules($userType)->getBaseDN();
            $filter = $server->getConfiguration()->getUserRules($userType)->getFilter();
        }

        if (!empty($filter) && !empty($baseDN)) {
            $connect = $server->connect();
        } else {
            $msg = 'No baseDN or no filter given.';
            $this->logger->notice($msg);
        }

        if ($connect) {
            if ($server->getConfiguration()->getUser() && $server->getConfiguration()->getPassword()) {
                $bind = $server->bind($connect, $server->getConfiguration()->getUser(), $server->getConfiguration()->getPassword());
            } else {
                $bind = $server->bind($connect);
            }
        }

        if ($bind) {
            $parsedFilter = str_replace('<search>', $findname, $filter);
            if (3 == $this->logLevel) {
                $msg = 'Query server "'.$server->getConfiguration()->getUid();
                $logArray = [
                    'baseDN' => $baseDN,
                    'filter' => $parsedFilter,
                ];
                $this->logger->debug($msg, $logArray);
            }

            $info = $server->search($connect, $baseDN, $parsedFilter, 'sub');
        } else {
            $msg = 'Bind failed.';
            $this->logger->notice($msg);
        }

        if (isset($info['count']) && ($info['count'] >= 0)) {
            $parameters = [
                'server' => $server,
                'find' => $findname,
                'table' => $userType,
                'type' => 'list',
                'result' => $info,
            ];
            // $this->eventDispatcher->dispatch(__CLASS__, 'getUsersResults', $parameters);

            $users = new SplObjectStorage();
            for ($i = 0; $i < $info['count']; ++$i) {
                if ('be_users' == $userType) {
                    $user = GeneralUtility::makeInstance(BeUser::class);
                } else {
                    $user = GeneralUtility::makeInstance(FeUser::class);
                }
                $user
                    ->setDN($info[$i]['dn'])
                    ->setAttributes($info[$i])
                    ->setLdapServer($this)
                ;
                $users->attach($user);
            }

            $msg = 'Found '.$info['count'].' records';
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
    public function findUser(LdapServer $server, string $userType = 'fe_user', string $dn, bool $doSanitize = false) \NormanSeibert\Ldap\Domain\Model\LdapUser\User
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
            $connect = $server->connect();
        }
        if ($connect) {
            if ($server->getConfiguration()->getUser() && $server->getConfiguration()->getPassword()) {
                $bind = $server->bind($connect, $server->getConfiguration()->getUser(), $server->getConfiguration()->getPassword());
            } else {
                $bind = $server->bind($connect);
            }
        }
        if ($bind) {
            if ($doSanitize) {
                $distinguishedName = Helpers::sanitizeQuery($dn);
            } else {
                $distinguishedName = $dn;
            }
            $filter = $server->getConfiguration()->getUserRules($userType)->getFilter();
            $parsedFilter = str_replace('<search>', '*', $filter);
            $info = $server->search($connect, $distinguishedName, $parsedFilter, 'base');
        } else {

        }

        $parameters = [
            'server' => $server,
            'dn' => $dn,
            'table' => $userType,
            'type' => 'single',
            'result' => $info,
        ];
        //$this->eventDispatcher->dispatch(__CLASS__, 'getUserResults', $parameters);

        if (1 == $info['count']) {
            if ('be_users' == $userType) {
                $user = GeneralUtility::makeInstance(BeUser::class);
            } else {
                $user = GeneralUtility::makeInstance(FeUser::class);
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
            $msg = 'Did not find a unique record for the user DN='.$dn.', but found '.$info['count'].' records instead.';
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
            $connect = $server->connect();
            if ($connect) {
                if ($server->getConfiguration()->getUser() && $server->getConfiguration()->getPassword()) {
                    $bind = $server->bind($connect, $server->getConfiguration()->getUser(), $server->getConfiguration()->getPassword());
                } else {
                    $bind = $server->bind($connect);
                }
            }
            if ($bind) {
                $distinguishedName = Helpers::sanitizeQuery($dn);
                $info = $this->server($connect, $distinguishedName, '(objectClass=*)', 'base');
            }
        }

        return $info[0];
    }

    /**
     * finds usergroups -> getUsers().
     */
    public function getGroups(LdapServer $server, string $userType, string $findname = '*', bool $doSanitize = false): array
    {
        $info = [];
        if (strlen($findname)) {
            if ($doSanitize) {
                $findname = Helpers::sanitizeQuery($findname);
            }

            $baseDN = $server->getConfiguration()->getUserRules($userType)->getGroupRules()->getBaseDN();
            $filter = $server->getConfiguration()->getUserRules($userType)->getGroupRules()->getFilter();

            if (!empty($filter) && !empty($baseDN)) {
                $filter = str_replace('<search>', $findname, $filter);

                $msg = 'Query server: '.$server->getConfiguration()->getUid().' with filter: '.$filter;
                if (3 == $this->logLevel) {
                    $this->logger->debug($msg);
                }

                if (!empty($filter)) {
                    $connect = $server->connect();
                    if (!empty($connect)) {
                        if ($thserveris->getConfiguration()->getUser() && $server->getConfiguration()->getPassword()) {
                            $bind = $server->bind($connect, $server->getConfiguration()->getUser(), $server->getConfiguration()->getPassword());
                        } else {
                            $bind = $server->bind($connect);
                        }
                    }
                    if (!empty($bind)) {
                        $info = $server->search($connect, $baseDN, $filter, 'sub');
                    }
                }
            }
        }

        return $info;
    }

    /**
     * checks user credentials by binding to the LDAP server.
     */
    public function authenticateUser(LdapServer $server, string $loginname, string $password): array
    {
        $user = null;
        $username = null;
        $serverUid = $server->getConfiguration()->getUid();
        $password = sanitizeCredentials($password);

        // @var $ldapUser \NormanSeibert\Ldap\Domain\Model\LdapUser\User
        $ldapUser = $this->checkUser($server, $loginname);

        if (is_object($ldapUser)) {
            $username = $ldapUser->getDN();
        }

        if (!empty($username) && !empty($password)) {
            $connect = $server->connect();
            $bind = $server->bind($connect, $username, $password, self::INFO);

            if ($bind) {
                $user = $ldapUser;
                if ($this->logLevel >= 2) {
                    $msg = 'User '.$username.' retrieved from LDAP directory (Server: '.$serverUid.')';
                    $this->logger->debug($msg);
                }
            } else {
                $msg = 'LDAP server denies authentication (Server: '.$serverUid.', User: '.$username.')';
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
    public function checkUser(LdapServer $server, string $loginname): array
    {
        $ldapUser = null;
        $serverUid = $server->getConfiguration()->getUid();
        $loginname = Helpers::sanitizeCredentials($loginname);

        $ldapUsers = $this->findUsers($server, $loginname);

        if (isset($ldapUsers) && (count($ldapUsers) < 1)) {
            $msg = 'No user found (Server: '.$serverUid.', User: '.$loginname.')';
            if ($this->logLevel >= 1) {
                $this->logger->notice($msg);
            }
            Helpers::addError(self::INFO, $msg, $serverUid);
        } elseif (isset($ldapUsers) && (count($ldapUsers) > 1)) {
            $msg = 'Found '.count($ldapUsers).' instead of one (Server: '.$serverUid.', User: '.$loginname.')';
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
}
