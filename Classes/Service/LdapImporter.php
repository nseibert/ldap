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

use NormanSeibert\Ldap\Domain\Model\LdapServer\LdapServer;
use SplObjectStorage;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\FrontendUserRepository;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserRepository;
use NormanSeibert\Ldap\Domain\Repository\LdapServer\LdapServerRepository;
use NormanSeibert\Ldap\Service\Mapping\LdapTypo3UserMapper;
use NormanSeibert\Ldap\Service\LdapHandler;

/**
 * Service to import users from LDAP directory to TYPO3 database.
 */
class LdapImporter
{

    const ERROR = 2;
    const WARNING = 1;
    const OK = 0;
    const INFO = -1;
    const NOTICE = -2;

    /**
     * imports users from LDAP to TYPO3 DB.
     */
    public static function doImport(LdapServer $server): string
    {
        $runIdentifier = uniqid();
        self::getUsers($server, $runIdentifier, 'import');

        return $runIdentifier;
    }

    /**
     * updates users from LDAP to TYPO3 DB.
     *
     * @return string
     */
    public function doUpdate(LdapServer $server): string
    {
        $runIdentifier = uniqid();
        self::getUsers($server, $runIdentifier, 'update');

        return $runIdentifier;
    }

    /**
     * imports resp. updates users from LDAP to TYPO3 DB.
     *
     * @return string
     */
    public function doImportOrUpdate(LdapServer $server): string
    {
        $runIdentifier = uniqid();
        self::getUsers($server, $runIdentifier, 'importOrUpdate');

        return $runIdentifier;
    }

    /**
     * deletes/deactivates users from LDAP to TYPO3 DB.
     */
    public function doDelete(string $userType, bool $hide = true, bool $deleteNonLdapUsers = false): string
    {
        $conf = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ExtensionConfiguration')->get('ldap');
        $logLevel = $conf['logLevel'];
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        $runIdentifier = uniqid();
        if ('be' == $userType) {
            $userRepository = GeneralUtility::makeInstance(BackendUserRepository::class);
        } else {
            $userRepository = GeneralUtility::makeInstance(FrontendUserRepository::class);
        }
        if ($deleteNonLdapUsers) {
            $users = $userRepository->findAll();
        } else {
            $users = $userRepository->findLdapImported();
        }

        $tmpServer = null;
        $removeUsers = [];
        foreach ($users as $user) {
            if ($user->getServerUid()) {
				// note the . behind the uid as it comes from the DB
                $serverRepository = GeneralUtility::makeInstance(LdapServerRepository::class);
				$server = $serverRepository->findByUid($user->getServerUid() . ".");
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
                    $userRepository->update($user);
                }
            } else {
                $user->setLastRun($runIdentifier);
                if ($hide) {
                    $user->setIsDisabled(true);
                } else {
                    $removeUsers[] = $user;
                }
                $userRepository->update($user);
            }
        }

        foreach ($removeUsers as $user) {
            $user->setLastRun($runIdentifier);
            $userRepository->update($user);
            $userRepository->remove($user);
        }

        return $runIdentifier;
    }

    /**
     * creates new TYPO3 users.
     */
    private static function storeNewUsers(LdapServer $server, string $runIdentifier, SplObjectStorage $ldapUsers)
    {
        $conf = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ExtensionConfiguration')->get('ldap');
        $logLevel = $conf['logLevel'];

        $userMapper = GeneralUtility::makeInstance(LdapTypo3UserMapper::class);

        foreach ($ldapUsers as $user) {
            $typo3User = $userMapper->loadUser($user);
            if (!is_object($typo3User)) {
                $userMapper->addUser($runIdentifier);
            }
        }
    }

    /**
     * updates TYPO3 users.
     */
    private static function updateUsers(LdapServer $server, string $runIdentifier, SplObjectStorage $ldapUsers)
    {
        $conf = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ExtensionConfiguration')->get('ldap');
        $logLevel = $conf['logLevel'];

        $userMapper = GeneralUtility::makeInstance(LdapTypo3UserMapper::class);

        foreach ($ldapUsers as $user) {
            $typo3User = $userMapper->loadUser($user);
            if (is_object($typo3User)) {
                $user->updateUser($runIdentifier);
            }
        }
    }

    /**
     * imports or updates TYPO3 users.
     */
    private static function storeUsers(LdapServer $server, string $runIdentifier, SplObjectStorage $ldapUsers)
    {
        $conf = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ExtensionConfiguration')->get('ldap');
        $logLevel = $conf['logLevel'];

        $userMapper = GeneralUtility::makeInstance(LdapTypo3UserMapper::class);

        foreach ($ldapUsers as $user) {
            $typo3User = $userMapper->loadUser($user);
            if (is_object($typo3User)) {
                $userMapper->updateUser($user, $typo3User, $runIdentifier);
            } else {
                $userMapper->addUser($runIdentifier);
            }
        }
    }

    /**
     * retrieves user records from LDAP.
     */
    private static function getUsers(LdapServer $server, string $runIdentifier, string $command, string $search = '*')
    {
        $conf = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ExtensionConfiguration')->get('ldap');
        $logLevel = $conf['logLevel'];
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        $ldapHandler = new LdapHandler();
        $ldapUsers = $ldapHandler->getUsers($server, $search, false);

        if (is_object($ldapUsers)) {
            switch ($command) {
                case 'import':
                    self::storeNewUsers($server, $runIdentifier, $ldapUsers);
                    break;
                case 'update':
                    self::updateUsers($server, $runIdentifier, $ldapUsers);
                    break;
                case 'importOrUpdate':
                    self::storeUsers($server, $runIdentifier, $ldapUsers);
                    break;
            }
        } else {
            // recursive search
            if ($logLevel >= 1) {
                $msg = 'LDAP query limit exceeded';
                $this->logger->notice($msg);
            }
            $searchCharacters = \NormanSeibert\Ldap\Utility\Helpers::getSearchCharacterRange();
            foreach ($searchCharacters as $thisCharacter) {
                $newSearch = substr_replace($search, $thisCharacter, 1, 0);
                $msg = 'Query server: ' . $server->getUid() . ' with getUsers("' . $newSearch . '")';
                if (3 == $logLevel) {
                    $logger->debug($msg);
                }
                self::getUsers($server, $runIdentifier, $command, $newSearch);
            }
        }
    }
}
