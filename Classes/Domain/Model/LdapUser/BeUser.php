<?php

namespace NormanSeibert\Ldap\Domain\Model\LdapUser;

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

use NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserGroupRepository;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserRepository;
use NormanSeibert\Ldap\Domain\Model\LdapServer\LdapServer;
use NormanSeibert\Ldap\Domain\Model\LdapUser\BeGroup;
use NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Log\LoggerInterface;


/**
 * Model for users read from LDAP server.
 */
class BeUser extends \NormanSeibert\Ldap\Domain\Model\LdapUser\User
{
    private LoggerInterface $logger;

    /**
     * @var BackendUserRepository
     */
    protected $userRepository;

    /**
     * @var BackendUserGroupRepository
     */
    protected $usergroupRepository;

    /**
     * @var BeGroup
     */
    protected $groupObject;

    /**
     * @var BackendUser
     */
    protected $user;

    /**
     * @var int
     */
    protected $logLevel;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->userObject = 'NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser';
        $this->groupObject = GeneralUtility::makeInstance(BeGroup::class);
        $this->userRepository = GeneralUtility::makeInstance(BackendUserRepository::class);
        $this->usergroupRepository = GeneralUtility::makeInstance(BackendUserGroupRepository::class);
        // $this->logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        $this->logger = $logger;
    }

    /**
     * sets the LDAP server (backreference).
     *
     * @return \NormanSeibert\Ldap\Domain\Model\LdapUser\BeUser
     */
    public function setLdapServer(LdapServer $server)
    {
        $this->ldapServer = $server;
        $this->groupObject->setLdapServer($server);
        $this->userRules = $this->ldapServer->getConfiguration()->getBeUserRules();
        $this->pid = 0;

        return $this;
    }
}
