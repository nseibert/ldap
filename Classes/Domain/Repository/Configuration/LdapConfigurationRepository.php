<?php

namespace NormanSeibert\Ldap\Domain\Repository\Configuration;

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
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Repository for the extension's configuration of LDAP servsers.
 */
class LdapConfigurationRepository extends Repository
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
    private $config;

    /**
     * @var int
     */
    public $logLevel;

    /**
     * @var array
     */
    protected $ldapServerDefinitions;

    /**
     * @var array
     */
    protected $allLdapServers;

    /**
     * @var bool
     */
    protected $configOK;

    public function __construct()
    {
        $this->logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        
        $this->configOK = false;
        $this->config = $this->loadConfiguration();
        $this->allLdapServers = $this->getLdapServersFromConfiguration();
    }

    /**
     * Gets all LDAP server definitions.
     */
    public function getLdapServers(): array
    {
        return $this->allLdapServers;
    }

    /**
     * Get LDAP server definitions.
     *
     * @param string $uid
     *
     * @return mixed
     */
    public function getLdapServer($uid)
    {
        if (isset($this->allLdapServers[$uid])) {
            $server = $this->allLdapServers[$uid];
            return $server;
        } else {
            return false;
        }
    }

    /**
     * @return array
     */
    public function getConfiguration()
    {
        return $this->config;
    }

    /**
     * @return bool
     */
    public function isConfigurationValid()
    {
        return $this->configOK;
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
                    $this->ldapServerDefinitions = $tsParser->setup['ldapServers.'];
                    unset($tsParser->setup);
                    $msg = 'Configuration file "'.$configFile.'" read successfully.';
                    $this->logger->debug($msg);
                    $this->configOK = true;
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
    private function getLdapServersFromConfiguration()
    {
        $ldapServers = [];
        $allLdapServers = $this->ldapServerDefinitions;
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

    /**
     * reads LDAP server definitions from configuration file.
     *
     * @return array
     */
    public function getLdapServerDefinitions()
    {
        return $this->allLdapServers;
    }
}