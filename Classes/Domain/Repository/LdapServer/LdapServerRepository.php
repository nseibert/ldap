<?php

namespace NormanSeibert\Ldap\Domain\Repository\LdapServer;

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

use NormanSeibert\Ldap\Utility\Helpers;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Log\LoggerInterface;
use NormanSeibert\Ldap\Domain\Repository\Configuration\LdapConfigurationRepository;
use NormanSeibert\Ldap\Domain\Model\LdapServer\LdapServer;
use NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfiguration;
use NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups;
use NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationUsers;
use SplObjectStorage;
use NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser;
use NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUserGroup;
use NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser;
use NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUserGroup;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Repository for the extension's configured LDAP servsers.
 */
class LdapServerRepository extends Repository
{
    private LoggerInterface $logger;

    const ERROR = 2;
    const WARNING = 1;
    const OK = 0;
    const INFO = -1;
    const NOTICE = -2;

    public int $logLevel;

    protected array $allLdapServers;

    protected LdapConfigurationRepository $configurationRepository;

    public function __construct()
    {
        $conf = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ExtensionConfiguration')->get('ldap');
        $this->logLevel = $conf['logLevel'];
        
        $configurationRepository = new LdapConfigurationRepository();
        $this->allLdapServers = $configurationRepository->getLdapServerDefinitions();

        $this->logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * Gets all LDAP server definitions.
     */
    public function findAll(): SplObjectStorage
    {
        $ldapServers = new SplObjectStorage();

        if (is_array($this->allLdapServers)) {
            foreach ($this->allLdapServers as $serverUid => $server) {
                $load = 1;
                if ($server['disable']) {
                    $load = 0;
                    $msg = 'LDAP server "'.$server['title'].'" ignored: is disabled.';
                    $this->logger->info($msg);
                }
                if ($load) {
                    $ldapServer = $this->initializeServer($server);
                    $ldapServers->attach($ldapServer);
                }
            }
        }

        return $ldapServers;
    }

    /**
     * Get LDAP server definitions.
     */
    public function FindByUid($uid): LdapServer|bool
    {
        if (isset($this->allLdapServers[$uid . '.'])) {
            $ldapServer = $this->initializeServer($this->allLdapServers[$uid . '.']);
            return $ldapServer;
        } else {
            return false;
        }
    }

    /**
     * Gets matching LDAP server definitions.
     */
    public function findByCritera(string $uid = null, array $pid = null, string $authenticate = null, string $userPid = null): SplObjectStorage
    {
        $ldapServers = new SplObjectStorage();

        if (is_array($this->allLdapServers)) {
            foreach ($this->allLdapServers as $serverUid => $server) {
                $load = 1;
                if ($server['disable']) {
                    $load = 0;
                    $msg = 'LDAP server "'.$server['title'].'" ignored: is disabled.';
                    $this->logger->info($msg);
                }
                if ($load) {
                    if ($pid && $server['pid']) {
                        if (!GeneralUtility::inList($pid, $server['pid'])) {
                            $load = 0;
                            $pidList = '';
                            if (is_array($pid)) {
                                $pidList = implode(', ', $pid);
                            } else {
                                $pidList = $pid;
                            }
                            $msg = 'LDAP server "'.$server['title'].'" ignored: does not match list of page uids ('.$pidList.').';
                            $this->logger->info($msg);
                        }
                    }
                    if ($userPid && isset($server['fe_users.']['pid'])) {
                        if (!GeneralUtility::inList($userPid, $server['fe_users.']['pid'])) {
                            $load = 0;
                            $pidList = '';
                            if (is_array($userPid)) {
                                $pidList = implode(', ', $userPid);
                            } else {
                                $pidList = $userPid;
                            }
                            $msg = 'LDAP server "'.$server['title'].'" ignored: does not match list of page uids ('.$pidList.').';
                            $this->logger->info($msg);
                        }
                    }
                    if ($uid) {
                        if ($server['uid']) {
                            if ($server['uid'] != $uid) {
                                $load = 0;
                            } else {
                                $msg = 'LDAP server "'.$server['uid'].'" fetched from repository successfully.';
                                $this->logger->debug($msg);
                            }
                        }
                    }
                    $server['authenticate'] = strtolower($server['authenticate']);
                    if ($authenticate) {
                        $authenticate = strtolower($authenticate);
                        if ($server['authenticate'] && ($server['authenticate'] != $authenticate) && ('both' != $server['authenticate'])) {
                            $load = 0;
                            $msg = 'LDAP server "'.$server['title'].'" ignored: no matching authentication configured.';
                            $this->logger->info($msg);
                        }
                    }
                }
                if ($load) {
                    $ldapServer = $this->initializeServer($this->allLdapServers[$serverUid]);
                    $ldapServers->attach($ldapServer);
                }
            }
        }

        return $ldapServers;
    }

    /**
     * checks an LDAP server's definition on syntactical correctness.
     */
    private function checkServerConfiguration(array $server): array
    {
        $errors = [];

        if (isset($server['pid'])) {
            $res = Helpers::checkValue($server['pid'], 'int');
            if (isset($res['error'])) {
                $errors[] = 'Attribute "pid": '.$res['error'];
            }
        }

        if (isset($server['port'])) {
            $res = Helpers::checkValue($server['port'], 'int+');
            if (isset($res['error'])) {
                $errors[] = 'Attribute "port": '.$res['error'];
            }
        } else {
            $errors[] = 'Attribute "port": This value is required';
        }

        if (!isset($server['port'])) {
            $errors[] = 'Attribute "host": This value is required';
        } 

        $res = Helpers::checkValue($server['version'], 'list', '1,2,3');
        if (isset($res['error'])) {
            $errors[] = 'Attribute "version": '.$res['error'];
        }

        $res = Helpers::checkValue(strtolower($server['authenticate']), 'list', 'fe,be,both');
        if (isset($res['error'])) {
            $errors[] = 'Attribute "auhenticate": '.$res['error'];
        }

        $server['authenticate'] = strtolower($server['authenticate']);

        if (isset($server['authenticate'])) {

            if (('fe' == $server['authenticate']) || ('both' == $server['authenticate'])) {
                $res = Helpers::checkValue($server['fe_users.']['pid'], 'required,int');
                if (isset($res['error'])) {
                    $errors[] = 'Attribute "fe_users.pid": '.$res['error'];
                }

                $res = Helpers::checkValue($server['fe_users.']['filter'], 'required');
                if (isset($res['error'])) {
                    $errors[] = 'Attribute "fe_users.filter": '.$res['error'];
                }

                $res = Helpers::checkValue($server['fe_users.']['baseDN'], 'required');
                if (isset($res['error'])) {
                    $errors[] = 'Attribute "fe_users.baseDN": '.$res['error'];
                }

                if (is_array($server['fe_users.']['mapping.'])) {
                    $newObj = GeneralUtility::makeInstance(FrontendUser::class);
                    foreach ($server['fe_users.']['mapping.'] as $fld => $mapping) {
                        if ('.' == substr($fld, strlen($fld) - 1, 1)) {
                            $fld = substr($fld, 0, strlen($fld) - 1);
                        }
                        $ret = $newObj->_hasProperty($fld);
                        if (!$ret) {
                            $errors[] = 'Property "fe_users.'.$fld.'" is unknown to Extbase.';
                        }
                    }
                    $newObj = null;
                }

                if (isset($server['fe_users.']['usergroups.']['pid'])) {
                    $res = Helpers::checkValue($server['fe_users.']['usergroups.']['pid'], 'int');
                    if (isset($res['error'])) {
                        $errors[] = 'Attribute "fe_users.usergroups.pid": '.$res['error'];
                    }
                }

                if (is_array($server['fe_users.']['usergroups.']['mapping.'])) {
                    $newObj = GeneralUtility::makeInstance(FrontendUserGroup::class);
                    foreach ($server['fe_users.']['usergroups.']['mapping.'] as $fld => $mapping) {
                        if ('.' == substr($fld, strlen($fld) - 1, 1)) {
                            $fld = substr($fld, 0, strlen($fld) - 1);
                        }
                        if ('field' != $fld) {
                            $ret = $newObj->_hasProperty($fld);
                            if (!$ret) {
                                $errors[] = 'Property "fe_groups.'.$fld.'" is unknown to Extbase.';
                            }
                        }
                    }
                    $newObj = null;
                }

                if (!isset($server['fe_users.']['mapping.']['username.']['data'])) {
                    $errors[] = 'Attribute "fe_users.mapping.userName.data": This value is required';
                }
            }

            if (('be' == $server['authenticate']) || ('both' == $server['authenticate'])) {
                $res = Helpers::checkValue($server['be_users.']['filter'], 'required');
                if (isset($res['error'])) {
                    $errors[] = 'Attribute "be_users.filter": '.$res['error'];
                }

                $res = Helpers::checkValue($server['be_users.']['baseDN'], 'required');
                if (isset($res['error'])) {
                    $errors[] = 'Attribute "be_users.baseDN": '.$res['error'];
                }

                if (is_array($server['be_users.']['mapping.'])) {
                    $newObj = GeneralUtility::makeInstance(BackendUser::class);
                    foreach ($server['be_users.']['mapping.'] as $fld => $mapping) {
                        if ('.' == substr($fld, strlen($fld) - 1, 1)) {
                            $fld = substr($fld, 0, strlen($fld) - 1);
                        }
                        $ret = $newObj->_hasProperty($fld);
                        if (!$ret) {
                            $errors[] = 'Property "be_users.'.$fld.'" is unknown to Extbase.';
                        }
                    }
                    $newObj = null;
                }

                if (is_array($server['be_users.']['usergroups.']['mapping.'])) {
                    $newObj = GeneralUtility::makeInstance(BackendUserGroup::class);
                    foreach ($server['be_users.']['usergroups.']['mapping.'] as $fld => $mapping) {
                        if ('.' == substr($fld, strlen($fld) - 1, 1)) {
                            $fld = substr($fld, 0, strlen($fld) - 1);
                        }
                        if ('field' != $fld) {
                            $ret = $newObj->_hasProperty($fld);
                            if (!$ret) {
                                $errors[] = 'Property "be_groups.'.$fld.'" is unknown to Extbase.';
                            }
                        }
                    }
                    $newObj = null;
                }

                $res = Helpers::checkValue($server['be_users.']['mapping.']['username.']['data'], 'required');
                if (isset($res['error'])) {
                    $errors[] = 'Attribute "be_users.mapping.userName.data": '.$res['error'];
                }
            }
        }

        return $errors;
    }

    public function initializeServer(array $server): LdapServer
    {   
        if (is_array($server)) {
            $errors = $this->checkServerConfiguration($server);

            if (0 == count($errors)) {
                $msg = 'Configuration for server "' . rtrim($server['uid'], '.') . '" loaded successfully';
                if ($this->logLevel >= 2) {
                    $this->logger->debug($msg);
                }
                $msg = 'Configuration for server "' . $server['uid'] . '"';
                if (3 == $this->logLevel) {
                    $this->logger->debug($msg, $server);
                }

                $groupRuleFE = GeneralUtility::makeInstance(ServerConfigurationGroups::class);
                $userRuleFE = GeneralUtility::makeInstance(ServerConfigurationUsers::class);
                if (isset($server['fe_users.']) && is_array($server['fe_users.'])) {
                    if (isset($server['fe_users.']['usergroups.']['pid'])) {
                        $groupRuleFE->setPid($server['fe_users.']['usergroups.']['pid']);
                    } elseif (isset($server['fe_users.']['pid'])) {
                        $groupRuleFE->setPid($server['fe_users.']['pid']);
                    }
                    if (isset($server['fe_users.']['usergroups.']['importGroups'])) {
                        $groupRuleFE->setImportGroups($server['fe_users.']['usergroups.']['importGroups']);
                    }
                    if (isset($server['fe_users.']['usergroups.']['mapping.'])) {
                        $groupRuleFE->setMapping($server['fe_users.']['usergroups.']['mapping.']);
                    }
                    if (isset($server['fe_users.']['usergroups.']['reverseMapping'])) {
                        $groupRuleFE->setReverseMapping($server['fe_users.']['usergroups.']['reverseMapping']);
                    }
                    if (isset($server['fe_users.']['usergroups.']['baseDN'])) {
                        $groupRuleFE->setBaseDN($server['fe_users.']['usergroups.']['baseDN']);
                    }
                    if (isset($server['fe_users.']['usergroups.']['filter'])) {
                        $groupRuleFE->setFilter($server['fe_users.']['usergroups.']['filter']);
                    }
                    if (isset($server['fe_users.']['usergroups.']['searchAttribute'])) {
                        $groupRuleFE->setSearchAttribute($server['fe_users.']['usergroups.']['searchAttribute']);
                    }
                    if (isset($server['fe_users.']['usergroups.']['addToGroups'])) {
                        $groupRuleFE->setAddToGroups($server['fe_users.']['usergroups.']['addToGroups']);
                    }
                    if (isset($server['fe_users.']['usergroups.']['restrictToGroups'])) {
                        $groupRuleFE->setRestrictToGroups($server['fe_users.']['usergroups.']['restrictToGroups']);
                    }
                    if (isset($server['fe_users.']['usergroups.']['preserveNonLdapGroups'])) {
                        $groupRuleFE->setPreserveNonLdapGroups($server['fe_users.']['usergroups.']['preserveNonLdapGroups']);
                    }

                    if (isset($server['fe_users.']['pid'])) {
                        $userRuleFE->setPid($server['fe_users.']['pid']);
                    }
                    if (isset($server['fe_users.']['baseDN'])) {
                        $userRuleFE->setBaseDN($server['fe_users.']['baseDN']);
                    }
                    if (isset($server['fe_users.']['filter'])) {
                        $userRuleFE->setFilter($server['fe_users.']['filter']);
                    }
                    if (isset($server['fe_users.']['autoImport'])) {
                        $userRuleFE->setAutoImport($server['fe_users.']['autoImport']);
                    }
                    if (isset($server['fe_users.']['autoEnable'])) {
                        $userRuleFE->setAutoEnable($server['fe_users.']['autoEnable']);
                    }
                    if (isset($server['fe_users.']['onlyUsersWithGroup'])) {
                        $userRuleFE->setOnlyUsersWithGroup($server['fe_users.']['onlyUsersWithGroup']);
                    }
                    if (isset($server['fe_users.']['mapping.'])) {
                        $userRuleFE->setMapping($server['fe_users.']['mapping.']);
                    }
                    if (isset($groupRuleFE)) {
                        $userRuleFE->setGroupRules($groupRuleFE);
                    }
                }

                $groupRuleBE = GeneralUtility::makeInstance(ServerConfigurationGroups::class);
                $userRuleBE = GeneralUtility::makeInstance(ServerConfigurationUsers::class);
                if (isset($server['be_users.']) && is_array($server['be_users.'])) {
                    if (isset($server['be_users.']['usergroups.']['importGroups'])) {
                        $groupRuleBE->setImportGroups($server['be_users.']['usergroups.']['importGroups']);
                    }
                    if (isset($server['be_users.']['usergroups.']['mapping.'])) {
                        $groupRuleBE->setMapping($server['be_users.']['usergroups.']['mapping.']);
                    }
                    if (isset($server['be_users.']['usergroups.']['reverseMapping'])) {
                        $groupRuleBE->setReverseMapping($server['be_users.']['usergroups.']['reverseMapping']);
                    }
                    if (isset($server['be_users.']['usergroups.']['baseDN'])) {
                        $groupRuleBE->setBaseDN($server['be_users.']['usergroups.']['baseDN']);
                    }
                    if (isset($server['be_users.']['usergroups.']['filter'])) {
                        $groupRuleBE->setFilter($server['be_users.']['usergroups.']['filter']);
                    }
                    if (isset($server['be_users.']['usergroups.']['searchAttribute'])) {
                        $groupRuleBE->setSearchAttribute($server['be_users.']['usergroups.']['searchAttribute']);
                    }
                    if (isset($server['be_users.']['usergroups.']['addToGroups'])) {
                        $groupRuleBE->setAddToGroups($server['be_users.']['usergroups.']['addToGroups']);
                    }
                    if (isset($server['be_users.']['usergroups.']['restrictToGroups'])) {
                        $groupRuleBE->setRestrictToGroups($server['be_users.']['usergroups.']['restrictToGroups']);
                    }
                    if (isset($server['be_users.']['usergroups.']['preserveNonLdapGroups'])) {
                        $groupRuleBE->setPreserveNonLdapGroups($server['be_users.']['usergroups.']['preserveNonLdapGroups']);
                    }
                    $groupRuleBE->setPid(0);

                    if (isset($server['be_users.']['baseDN'])) {
                        $userRuleBE->setBaseDN($server['be_users.']['baseDN']);
                    }
                    if (isset($server['be_users.']['filter'])) {
                        $userRuleBE->setFilter($server['be_users.']['filter']);
                    }
                    if (isset($server['be_users.']['autoImport'])) {
                        $userRuleBE->setAutoImport($server['be_users.']['autoImport']);
                    }
                    if (isset($server['be_users.']['autoEnable'])) {
                        $userRuleBE->setAutoEnable($server['be_users.']['autoEnable']);
                    }
                    if (isset($server['be_users.']['onlyUsersWithGroup'])) {
                        $userRuleBE->setOnlyUsersWithGroup($server['be_users.']['onlyUsersWithGroup']);
                    }
                    if (isset($server['be_users.']['mapping.'])) {
                        $userRuleBE->setMapping($server['be_users.']['mapping.']);
                    }
                    if (isset($groupRuleBE)) {
                        $userRuleBE->setGroupRules($groupRuleBE);
                        $userRuleBE->setPid(0);
                    }
                }

                $serverConfiguration = GeneralUtility::makeInstance(ServerConfiguration::class);
                
                if (isset($server['uid'])) {
                    $serverConfiguration->setUid($server['uid']);
                }
                if (isset($server['title'])) {
                    $serverConfiguration->setTitle($server['title']);
                }
                if (isset($server['host'])) {
                    $serverConfiguration->setHost($server['host']);
                }
                if (isset($server['port'])) {
                    $serverConfiguration->setPort($server['port']);
                }
                if (isset($server['forcetls'])) {
                    $serverConfiguration->setForceTLS($server['forcetls']);
                }
                if (isset($server['authenticate'])) {
                    $serverConfiguration->setAuthenticate($server['authenticate']);
                }
                if (isset($server['user'])) {
                    $serverConfiguration->setUser($server['user']);
                }
                if (isset($server['password'])) {
                    $serverConfiguration->setPassword($server['password']);
                }
                if (isset($userRuleFE)) {
                    $serverConfiguration->setFeUserRules($userRuleFE);
                }
                if (isset($userRuleBE)) {
                    $serverConfiguration->setBeUserRules($userRuleBE);
                }

                if (isset($server['version'])) {
                    $serverConfiguration->setVersion($server['version']);
                }

                $ldapServer = GeneralUtility::makeInstance(LdapServer::class);
                
                $ldapServer->setConfiguration($serverConfiguration);

                $conf = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ExtensionConfiguration')->get('ldap');
                $ldapServer->setLogLevel($conf['logLevel']);
            } else {
                $msg = 'LDAP server configuration invalid for "' . $server['uid'] . '". ';
                $this->logger->warning($msg, $errors);
                $msg .= implode(' | ', $errors).'.';
                Helpers::addError(self::WARNING, $msg, $server['uid']);
            }
        } else {
            $msg = 'LDAP server not found: uid = "' . rtrim($uid, '.') . '":';
            $this->logger->warning($msg);
            Helpers::addError(self::WARNING, $msg, $uid);
        }

        return $ldapServer;
    }
}
