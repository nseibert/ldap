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

/**
 * Model for users read from LDAP server.
 */
class BeGroup extends \NormanSeibert\Ldap\Domain\Model\LdapUser\Group
{
    /**
     * @var \NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserGroupRepository
     */
    protected $usergroupRepository;

    public function __construct(BackendUserGroupRepository $usergroupRepository)
    {
        parent::__construct();
        $this->usergroupRepository = $usergroupRepository;
        $this->groupObject = 'NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUserGroup';
    }

    /**
     * sets the LDAP server (backreference).
     *
     * @return \NormanSeibert\Ldap\Domain\Model\LdapUser\BeGroup
     */
    public function setLdapServer(\NormanSeibert\Ldap\Domain\Model\LdapServer\Server $server)
    {
        $this->ldapServer = $server;
        $this->usergroupRules = $this->ldapServer->getConfiguration()->getBeUserRules()->getGroupRules();
        $this->pid = 0;

        return $this;
    }
}
