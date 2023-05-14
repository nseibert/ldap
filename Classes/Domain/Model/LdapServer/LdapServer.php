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
use NormanSeibert\Ldap\Domain\Model\LdapServer\ServerConfiguration;
use NormanSeibert\Ldap\Domain\Model\Configuration\LdapConfiguration;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Model for an LDAP server.
 */
class LdapServer extends AbstractEntity
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

    public function __construct()
    {
        $this->initializeObject();
    }

    public function initializeObject()
    {
        $this->ldapConfiguration = new LdapConfiguration();
        
        $this->logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
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
}
