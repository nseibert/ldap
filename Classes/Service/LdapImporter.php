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

use NormanSeibert\Ldap\Domain\Model\Configuration\LdapConfiguration;
use NormanSeibert\Ldap\Domain\Model\LdapServer\LdapServer;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserRepository;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\FrontendUserRepository;
use NormanSeibert\Ldap\Domain\Repository\LdapServer\LdapServerRepository;
use Psr\Log\LoggerInterface;

/**
 * Service to import users from LDAP directory to TYPO3 database.
 */
class LdapImporter
{
    private LoggerInterface $logger;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var LdapConfiguration
     */
    protected $ldapConfig;

    /**
     * @var LdapServer
     */
    protected $ldapServer;

    /**
     * @var LdapServerRepository
     */
    protected $serverRepository;

    /**
     * @var FrontendUserRepository
     */
    protected $feUserRepository;

    /**
     * @var BackendUserRepository
     */
    protected $beUserRepository;

    public function __construct(
        LdapConfiguration $ldapConfig,
        LdapServer $ldapServer,
        LdapServerRepository $serverRepository,
        FrontendUserRepository $feUserRepository,
        BackendUserRepository $beUserRepository,
        LoggerInterface $logger)
    {
        $this->ldapConfig = $ldapConfig;
        $this->ldapServer = $ldapServer;
        $this->serverRepository = $serverRepository;
        $this->feUserRepository = $feUserRepository;
        $this->beUserRepository = $beUserRepository;
        $this->logger = $logger;
    }

    /**
     * initializes the importer.
     *
     * @param int $uid
     * @param string $scope
     */
    public function init($uid, $scope)
    {
        $server = $this->serverRepository->findByUid($uid);
        if (is_object($server)) {
            $this->ldapServer = $server;
            $this->ldapServer->setScope($scope);
        }
        if ('be' == $scope) {
            $this->table = 'be_users';
        } else {
            $this->table = 'fe_users';
        }
    }

    /**
     * imports users from LDAP to TYPO3 DB.
     *
     * @return string
     */
    public function doImport()
    {
        $runIdentifier = uniqid();
        $this->ldapServer->loadAllGroups();
        $this->getUsers($runIdentifier, 'import');

        return $runIdentifier;
    }

    /**
     * updates users from LDAP to TYPO3 DB.
     *
     * @return string
     */
    public function doUpdate()
    {
        $runIdentifier = uniqid();
        $this->ldapServer->loadAllGroups();
        $this->getUsers($runIdentifier, 'update');

        return $runIdentifier;
    }

    /**
     * imports resp. updates users from LDAP to TYPO3 DB.
     *
     * @return string
     */
    public function doImportOrUpdate()
    {
        $runIdentifier = uniqid();
        $this->ldapServer->loadAllGroups();
        $this->getUsers($runIdentifier, 'importOrUpdate');

        return $runIdentifier;
    }

    /**
     * deletes/deactivates users from LDAP to TYPO3 DB.
     *
     * @param bool $hide
     * @param bool $deleteNonLdapUsers
     *
     * @return string
     */
    public function doDelete($hide = true, $deleteNonLdapUsers = false)
    {
        $runIdentifier = uniqid();
        if ('be_users' == $this->table) {
            $repository = $this->beUserRepository;
        } else {
            $repository = $this->feUserRepository;
        }
        if ($deleteNonLdapUsers) {
            $users = $repository->findAll();
        } else {
            $users = $repository->findLdapImported();
        }

        $tmpServer = null;
        $removeUsers = [];
        foreach ($users as $user) {
            $user->setLoglevel($this->ldapConfig->getLogLevel());
            if ($user->getServerUid()) {
				// note the . behind the uid as it comes from the DB
				$server = $this->ldapConfig->getLdapServer($user->getServerUid().".");
                if ($server != $tmpServer) {
                    $tmpServer = $server;
                }
				$ldapUser = null;
				if ($tmpServer) {
					$ldapUser = $tmpServer->getUser($user->getDN());
				}
                $ldapUser = $tmpServer->getUser($user->getDN());
                if (!is_object($ldapUser)) {
                    $user->setLastRun($runIdentifier);
                    if ($hide) {
                        $user->setIsDisabled(true);
                    } else {
                        $removeUsers[] = $user;
                    }
                    $repository->update($user);
                }
            } else {
                $user->setLastRun($runIdentifier);
                if ($hide) {
                    $user->setIsDisabled(true);
                } else {
                    $removeUsers[] = $user;
                }
                $repository->update($user);
            }
        }

        foreach ($removeUsers as $user) {
            $user->setLastRun($runIdentifier);
            $repository->update($user);
            $repository->remove($user);
        }

        return $runIdentifier;
    }

    /**
     * creates new TYPO3 users.
     *
     * @param string $runIdentifier
     * @param array  $ldapUsers
     */
    private function storeNewUsers($runIdentifier, $ldapUsers)
    {
        foreach ($ldapUsers as $user) {
            $user->setLoglevel($this->ldapConfig->getLogLevel());
            $user->loadUser();
            $typo3User = $user->getUser();
            if (!is_object($typo3User)) {
                $user->addUser($runIdentifier);
            }
        }
    }

    /**
     * updates TYPO3 users.
     *
     * @param string $runIdentifier
     * @param array  $ldapUsers
     */
    private function updateUsers($runIdentifier, $ldapUsers)
    {
        foreach ($ldapUsers as $user) {
            $user->setLoglevel($this->ldapConfig->getLogLevel());
            $user->loadUser();
            $typo3User = $user->getUser();
            if (is_object($typo3User)) {
                $user->updateUser($runIdentifier);
            }
        }
    }

    /**
     * imports or updates TYPO3 users.
     *
     * @param string $runIdentifier
     * @param array  $ldapUsers
     */
    private function storeUsers($runIdentifier, $ldapUsers)
    {
        foreach ($ldapUsers as $user) {
            // @var $user \NormanSeibert\Ldap\Domain\Model\LdapUser\User
            $user->loadUser();
            $typo3User = $user->getUser();
            if (is_object($typo3User)) {
                $user->updateUser($runIdentifier);
            } else {
                $user->addUser($runIdentifier);
            }
        }
    }

    /**
     * retrieves user records from LDAP.
     *
     * @param string $runIdentifier
     * @param string $command
     * @param string $search
     */
    private function getUsers($runIdentifier, $command, $search = '*')
    {
        $ldapUsers = $this->ldapServer->getUsers($search, false);
        if (is_object($ldapUsers)) {
            switch ($command) {
                case 'import':
                    $this->storeNewUsers($runIdentifier, $ldapUsers);
                    break;
                case 'update':
                    $this->updateUsers($runIdentifier, $ldapUsers);
                    break;
                case 'importOrUpdate':
                    $this->storeUsers($runIdentifier, $ldapUsers);
                    break;
            }
        } else {
            // recursive search
            if ($this->ldapConfig->logLevel >= 1) {
                $msg = 'LDAP query limit exceeded';
                $this->logger->notice($msg);
            }
            $searchCharacters = \NormanSeibert\Ldap\Utility\Helpers::getSearchCharacterRange();
            foreach ($searchCharacters as $thisCharacter) {
                $newSearch = substr_replace($search, $thisCharacter, 1, 0);
                $msg = 'Query server: '.$this->ldapServer->getConfiguration()->getUid().' with getUsers("'.$newSearch.'")';
                if (3 == $this->ldapConfig->logLevel) {
                    $this->logger->debug($msg);
                }
                $this->getUsers($runIdentifier, $command, $newSearch);
            }
        }
    }
}
