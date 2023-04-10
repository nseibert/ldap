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
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationGroups;
use NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfigurationUsers;
use NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfiguration;
use NormanSeibert\Ldap\Domain\Model\LdapServer\LdapServer;
use Psr\Log\LoggerInterface;

/*
use NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser;
use NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUserGroup;
use NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser;
use NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUserGroup;
*/

/**
 * Model for the extension's configuration of LDAP servsers.
 */
class LdapConfiguration extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity implements \TYPO3\CMS\Core\SingletonInterface
{
    private LoggerInterface $logger;

    const ERROR = 2;
    const WARNING = 1;
    const OK = 0;
    const INFO = -1;
    const NOTICE = -2;

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
     * @var array
     */
    protected $allLdapServers;

    /**
     * @var bool
     */
    private $configOK;

    public function __construct(LoggerInterface $logger)
    {
        $this->configOK = 1;
        $this->checkLdapExtension();
        $this->config = $this->loadConfiguration();
        $this->allLdapServers = $this->getLdapServersFromFile();
        $this->logger = $logger;
        // $this->logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
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
                    $ldapServers[$serverUid] = $serverUid;
                }
            }
        }

        if (!count($ldapServers)) {
            $msg = 'No LDAP server found.';
            $this->logger->warning($msg);
            Helpers::addError(self::ERROR, $msg);
            $this->configOK = false;
        }

        // echo "<p>1.: " . $ldapServers["1."]->getUid() . "</p>";
        // echo "<p>2.: " . $ldapServers["2."]->getUid() . "</p>";

        return $ldapServers;
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
            $this->logger->error($msg);
            Helpers::addError(self::ERROR, $msg);
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
                $configFile = \TYPO3\CMS\Core\Core\Environment::getConfigPath().'/'.$conf['configFile'];
                if (file_exists($configFile) && is_file($configFile)) {
                    $ok = true;
                }
            }
            if ($ok) {
                $fileContent = GeneralUtility::getUrl($configFile);
                $tsParser = GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser::class);
                $tsParser->parse($fileContent);

                if ($tsParser->errors && is_array($tsParser->errors) && count($tsParser->errors) > 0) {
                    $msg = 'Mapping invalid.';
                    if ($this->logLevel >= 1) {
                        $this->logger->error($msg, $tsParser->errors);
                    }
                    Helpers::addError(self::ERROR, $msg);
                    $this->configOK = false;
                } else {
                    $this->ldapServers = $tsParser->setup['ldapServers.'];
                    unset($tsParser->setup);
                }
            } else {
                $msg = 'Configuration file "'.$configFile.'" not found.';
                $this->logger->error($msg);
                Helpers::addError(self::ERROR, $msg);
                $this->configOK = false;
            }
        } else {
            $msg = 'No configuration file set in extension settings (in extension manager)';
            $this->logger->error($msg);
            Helpers::addError(self::ERROR, $msg);
            $this->configOK = false;
        }

        return $conf;
    }

    /**
     * reads LDAP server definitions from configuration file.
     *
     * @return array
     */
    public function getLdapServersFromFile()
    {
        $ldapServers = [];
        $allLdapServers = $this->ldapServers;
        if ($allLdapServers && is_array($allLdapServers)) {
            if (0 == count($allLdapServers)) {
                $msg = 'No LDAP server found.';
                $this->logger->warning($msg);
                Helpers::addError(self::WARNING, $msg);
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
            Helpers::addError(self::WARNING, $msg);
            $this->configOK = false;
        }

        return $ldapServers;
    }
}