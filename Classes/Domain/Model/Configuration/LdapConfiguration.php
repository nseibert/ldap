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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Log\LoggerInterface;
use NormanSeibert\Ldap\Service\LdapSetup;
use NormanSeibert\Ldap\Domain\Repository\Configuration\LdapConfigurationRepository;

/**
 * Model for the extension's configuration of LDAP servsers.
 */
class LdapConfiguration extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
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
     * @var LdapSetup
     */
    private $ldapSetup;

    /**
     * @var LdapConfigurationRepository
     */
    private $configurationRepository;

    public function __construct(LoggerInterface $logger, LdapSetup $ldapSetup, LdapConfigurationRepository $configurationRepository)
    {
        $this->logger = $logger;
        $this->ldapSetup = $ldapSetup;
        $this->configurationRepository = $configurationRepository;

        $this->ldapSetup->checkLdapExtension();
        $this->config = $this->configurationRepository->getConfiguration();
        // $this->logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
    }

    public function getLoglevel() {
        return $this->config['logLevel'];
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
     * check server configurations
     *
     * @return bool
     */
    public function checkConfiguration()
    {
        $configOK = $this->configurationRepository->isConfigurationValid();

        if ($configOK) {
            $ldapServers = $this->configurationRepository->getLdapServers();

            if (count($ldapServers)) {
                $configOK = true;
            } else {
                $msg = 'No LDAP server found.';
                $this->logger->warning($msg);
                Helpers::addError(self::ERROR, $msg);
            }
        }

        return $configOK;
    }
}