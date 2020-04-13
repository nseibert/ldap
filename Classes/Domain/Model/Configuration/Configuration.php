<?php

namespace NormanSeibert\Ldap\Domain\Model\Configuration;

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
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Model for the extension's configuration of LDAP servsers.
 */
class Configuration extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity implements \TYPO3\CMS\Core\SingletonInterface, \Psr\Log\LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var array
     */
    public $config;

    /**
     * @var int
     */
    public $logLevel;

    /**
     * @var array
     */
    protected $ldapServers;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var bool
     */
    private $configOK;

    public function __construct(ObjectManager $objectManager)
    {
        $this->configOK = 1;
        $this->objectManager = $objectManager;
        $this->config = $this->loadConfiguration();
        $this->checkLdapExtension();
        $this->allLdapServers = $this->getLdapServersFromFile();
    }

    /**
     * returns whether the configuration is ok or not.
     *
     * @return bool
     */
    public function isConfigOK()
    {
        return $this->configOK;
    }

    /**
     * loads LDAP server definitions.
     *
     * @param string $uid
     * @param array  $pid
     * @param string $authenticate
     * @param array  $userPid
     *
     * @return array
     */
    public function getLdapServers($uid = null, $pid = null, $authenticate = null, $userPid = null)
    {
        $ldapServers = [];

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
                    if ($userPid && $server['fe_users.']['pid']) {
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
                            }
                        }
                    }
                    $server['authenticate'] = strtolower($server['authenticate']);
                    $authenticate = strtolower($authenticate);
                    if ($authenticate) {
                        if ($server['authenticate'] && ($server['authenticate'] != $authenticate) && ('both' != $server['authenticate'])) {
                            $load = 0;
                            $msg = 'LDAP server "'.$server['title'].'" ignored: no matching authentication configured.';
                            $this->logger->info($msg);
                        }
                    }
                }
                if ($load) {
                    $server = $this->getLdapServer($serverUid);
                    if (is_object($server)) {
                        $ldapServers[$serverUid] = $server;
                    }
                }
            }
        }

        if (!count($ldapServers)) {
            $msg = 'No LDAP server found.';
            // @extensionScannerIgnoreLine
            $this->logger->warning($msg);
            Helpers::addError(FlashMessage::ERROR, $msg);
            $this->configOK = false;
        }

        return $ldapServers;
    }

    /**
     * reads the definition of one specific LDAP server.
     *
     * @param int $uid
     *
     * @return \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
     */
    public function getLdapServer($uid)
    {
        $serverRecord = false;
        $server = $this->allLdapServers[$uid];
        if (!is_array($server)) {
            $server = $this->allLdapServers[$uid.'.'];
        }
        if (is_array($server)) {
            $errors = $this->checkServerConfiguration($server);

            if (0 == count($errors)) {
                $msg = 'Configuration for server "'.$uid.'" loaded successfully';
                if ($this->logLevel >= 2) {
                    $this->logger->debug($msg);
                }
                $msg = 'Configuration for server "'.$uid.'"';
                if (3 == $this->logLevel) {
                    $this->logger->debug($msg, $server);
                }

                $groupRuleFE = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\LdapServer\\ServerConfigurationGroups');
                $userRuleFE = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\LdapServer\\ServerConfigurationUsers');
                if (is_array($server['fe_users.'])) {
                    if ($server['fe_users.']['usergroups.']['pid']) {
                        $groupRuleFE->setPid($server['fe_users.']['usergroups.']['pid']);
                    } elseif ($server['fe_users.']['pid']) {
                        $groupRuleFE->setPid($server['fe_users.']['pid']);
                    }
                    $groupRuleFE
                        ->setImportGroups($server['fe_users.']['usergroups.']['importGroups'])
                        ->setMapping($server['fe_users.']['usergroups.']['mapping.'])
                        ->setReverseMapping($server['fe_users.']['usergroups.']['reverseMapping'])
                        ->setBaseDN($server['fe_users.']['usergroups.']['baseDN'])
                        ->setFilter($server['fe_users.']['usergroups.']['filter'])
                        ->setSearchAttribute($server['fe_users.']['usergroups.']['searchAttribute'])
                        ->setAddToGroups($server['fe_users.']['usergroups.']['addToGroups'])
                        ->setRestrictToGroups($server['fe_users.']['usergroups.']['restrictToGroups'])
                        ->setPreserveNonLdapGroups($server['fe_users.']['usergroups.']['preserveNonLdapGroups'])
                    ;

                    $userRuleFE->setPid($server['fe_users.']['pid']);
                    $userRuleFE
                        ->setBaseDN($server['fe_users.']['baseDN'])
                        ->setFilter($server['fe_users.']['filter'])
                        ->setAutoImport($server['fe_users.']['autoImport'])
                        ->setAutoEnable($server['fe_users.']['autoEnable'])
                        ->setOnlyUsersWithGroup($server['fe_users.']['onlyUsersWithGroup'])
                        ->setMapping($server['fe_users.']['mapping.'])
                        ->setGroupRules($groupRuleFE)
                    ;
                }

                $groupRuleBE = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\LdapServer\\ServerConfigurationGroups');
                $userRuleBE = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\LdapServer\\ServerConfigurationUsers');
                if (is_array($server['be_users.'])) {
                    $groupRuleBE
                        ->setImportGroups($server['be_users.']['usergroups.']['importGroups'])
                        ->setMapping($server['be_users.']['usergroups.']['mapping.'])
                        ->setReverseMapping($server['be_users.']['usergroups.']['reverseMapping'])
                        ->setBaseDN($server['be_users.']['usergroups.']['baseDN'])
                        ->setFilter($server['be_users.']['usergroups.']['filter'])
                        ->setSearchAttribute($server['be_users.']['usergroups.']['searchAttribute'])
                        ->setAddToGroups($server['be_users.']['usergroups.']['addToGroups'])
                        ->setRestrictToGroups($server['be_users.']['usergroups.']['restrictToGroups'])
                        ->setPreserveNonLdapGroups($server['be_users.']['usergroups.']['preserveNonLdapGroups'])
                    ;

                    $userRuleBE
                        ->setBaseDN($server['be_users.']['baseDN'])
                        ->setFilter($server['be_users.']['filter'])
                        ->setAutoImport($server['be_users.']['autoImport'])
                        ->setAutoEnable($server['be_users.']['autoEnable'])
                        ->setOnlyUsersWithGroup($server['be_users.']['onlyUsersWithGroup'])
                        ->setMapping($server['be_users.']['mapping.'])
                        ->setGroupRules($groupRuleBE)
                    ;
                }

                $serverConfiguration = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\LdapServer\\ServerConfiguration');
                $serverConfiguration
                    ->setUid($server['uid'])
                    ->setTitle($server['title'])
                    ->setHost($server['host'])
                    ->setPort($server['port'])
                    ->setForceTLS($server['forcetls'])
                    ->setAuthenticate($server['authenticate'])
                    ->setUser($server['user'])
                    ->setPassword($server['password'])
                    ->setFeUserRules($userRuleFE)
                    ->setBeUserRules($userRuleBE)
                ;
                if ($server['version']) {
                    $serverConfiguration->setVersion($server['version']);
                }

                $serverRecord = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\LdapServer\\Server');
                $serverRecord->setConfiguration($serverConfiguration);
            } else {
                $msg = 'LDAP server configuration invalid for "'.$server['uid'].'":';
                $this->logger->warning($msg, $errors);
                $msg .= '<ul><li>'.implode('</li><li>', $errors).'</li></ul>';
                Helpers::addError(FlashMessage::WARNING, $msg, $server['uid']);
                $this->configOK = false;
            }
        } else {
            $msg = 'LDAP server not found: uid = "'.$uid.'":';
            $this->logger->warning($msg);
            Helpers::addError(FlashMessage::WARNING, $msg, $server['uid']);
            $this->configOK = false;
        }

        return $serverRecord;
    }

    /**
     * checks whether PHP's LDAP functionality is available.
     *
     * @return bool
     */
    public function checkLdapExtension()
    {
        $result = extension_loaded('ldap');
        if (!$result) {
            $msg = 'PHP LDAP extension not loaded.';
            // @extensionScannerIgnoreLine
            $this->logger->error($msg);
            Helpers::addError(FlashMessage::ERROR, $msg);
            $this->configOK = false;
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getConfiguration()
    {
        return $this->config;
    }

    /**
     * loads the extension configuration.
     *
     * @global array $TYPO3_CONF_VARS
     *
     * @return array
     */
    private function loadConfiguration()
    {
        $conf = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ExtensionConfiguration')->get('ldap');
        $this->logLevel = $conf['logLevel'];

        $ok = false;
        if ($conf['configFile']) {
            $configFile = $conf['configFile'];
            if (file_exists($configFile) && is_file($configFile)) {
                $ok = true;
            } else {
                $configFile = \TYPO3\CMS\Core\Core\Environment::getPublicPath().'/'.$conf['configFile'];
                if (file_exists($configFile) && is_file($configFile)) {
                    $ok = true;
                }
            }
            if ($ok) {
                $fileContent = GeneralUtility::getUrl($configFile);
                $tsParser = $this->objectManager->get('TYPO3\\CMS\\Core\\TypoScript\\Parser\\TypoScriptParser');
                $tsParser->parse($fileContent);

                if ($tsParser->errors && is_array($tsParser->errors) && count($tsParser->errors) > 0) {
                    $msg = 'Mapping invalid.';
                    if ($this->logLevel >= 1) {
                        // @extensionScannerIgnoreLine
                        $this->logger->error($msg, $tsParser->errors);
                    }
                    Helpers::addError(FlashMessage::ERROR, $msg);
                    $this->configOK = false;
                } else {
                    $this->ldapServers = $tsParser->setup['ldapServers.'];
                    unset($tsParser->setup);
                }
            } else {
                $msg = 'Configuration file "'.$configFile.'" not found.';
                // @extensionScannerIgnoreLine
                $this->logger->error($msg);
                Helpers::addError(FlashMessage::ERROR, $msg);
                $this->configOK = false;
            }
        } else {
            $msg = 'No configuration file set in extension settings (in extension manager)';
            // @extensionScannerIgnoreLine
            $this->logger->error($msg);
            Helpers::addError(FlashMessage::ERROR, $msg);
            $this->configOK = false;
        }

        return $conf;
    }

    /**
     * reads LDAP server definitions from configuration file.
     *
     * @return array
     */
    private function getLdapServersFromFile()
    {
        $ldapServers = [];
        $allLdapServers = $this->ldapServers;
        if ($allLdapServers && is_array($allLdapServers)) {
            if (0 == count($allLdapServers)) {
                $msg = 'No LDAP server found.';
                $this->logger->warning($msg);
                Helpers::addError(FlashMessage::WARNING, $msg);
                $this->configOK = false;
            } else {
                foreach ($allLdapServers as $uid => $row) {
                    $ldapServers[$uid] = $row;
                    $ldapServers[$uid]['uid'] = rtrim($uid, '.');
                }
            }
        } else {
            $msg = 'No LDAP server found.';
            $this->logger->warning($msg);
            Helpers::addError(FlashMessage::WARNING, $msg);
            $this->configOK = false;
        }

        return $ldapServers;
    }

    /**
     * checks an LDAP server's definition on syntactical correctness.
     *
     * @param array $server
     *
     * @return array
     */
    private function checkServerConfiguration($server)
    {
        $errors = [];

        $res = Helpers::checkValue($server['pid'], 'int');
        if ($res['error']) {
            $errors[] = 'Attribute "pid": '.$res['error'];
        }

        $res = Helpers::checkValue($server['port'], 'required,int+');
        if ($res['error']) {
            $errors[] = 'Attribute "port": '.$res['error'];
        }

        $res = Helpers::checkValue($server['host'], 'required');
        if ($res['error']) {
            $errors[] = 'Attribute "host": '.$res['error'];
        }

        $res = Helpers::checkValue($server['version'], 'list', '1,2,3');
        if ($res['error']) {
            $errors[] = 'Attribute "version": '.$res['error'];
        }

        $res = Helpers::checkValue(strtolower($server['authenticate']), 'list', 'fe,be,both');
        if ($res['error']) {
            $errors[] = 'Attribute "auhenticate": '.$res['error'];
        }

        $server['authenticate'] = strtolower($server['authenticate']);

        if (('fe' == $server['authenticate']) || ('both' == $server['authenticate'])) {
            $res = Helpers::checkValue($server['fe_users.']['pid'], 'required,int');
            if ($res['error']) {
                $errors[] = 'Attribute "fe_users.pid": '.$res['error'];
            }

            $res = Helpers::checkValue($server['fe_users.']['usergroups.']['pid'], 'int');
            if ($res['error']) {
                $errors[] = 'Attribute "fe_users.usergroups.pid": '.$res['error'];
            }

            $res = Helpers::checkValue($server['fe_users.']['filter'], 'required');
            if ($res['error']) {
                $errors[] = 'Attribute "fe_users.filter": '.$res['error'];
            }

            $res = Helpers::checkValue($server['fe_users.']['baseDN'], 'required');
            if ($res['error']) {
                $errors[] = 'Attribute "fe_users.baseDN": '.$res['error'];
            }

            if (is_array($server['fe_users.']['mapping.'])) {
                $newObj = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\Typo3User\\FrontendUser');
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

            $res = Helpers::checkValue($server['fe_users.']['usergroups.']['pid'], 'int');
            if ($res['error']) {
                $errors[] = 'Attribute "fe_users.usergroups.pid": '.$res['error'];
            }

            if (is_array($server['fe_users.']['usergroups.']['mapping.'])) {
                $newObj = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\Typo3User\\FrontendUserGroup');
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

            $res = Helpers::checkValue($server['fe_users.']['mapping.']['username.']['data'], 'required');
            if ($res['error']) {
                $errors[] = 'Attribute "fe_users.mapping.userName.data": '.$res['error'];
            }
        }

        if (('be' == $server['authenticate']) || ('both' == $server['authenticate'])) {
            $res = Helpers::checkValue($server['be_users.']['filter'], 'required');
            if ($res['error']) {
                $errors[] = 'Attribute "be_users.filter": '.$res['error'];
            }

            $res = Helpers::checkValue($server['be_users.']['baseDN'], 'required');
            if ($res['error']) {
                $errors[] = 'Attribute "be_users.baseDN": '.$res['error'];
            }

            if (is_array($server['be_users.']['mapping.'])) {
                $newObj = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\Typo3User\\BackendUser');
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
                $newObj = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\Typo3User\\BackendUserGroup');
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
            if ($res['error']) {
                $errors[] = 'Attribute "be_users.mapping.userName.data": '.$res['error'];
            }
        }

        return $errors;
    }
}
