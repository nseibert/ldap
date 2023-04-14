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

use NormanSeibert\Ldap\Domain\Model\LdapServer\LdapServer;
use NormanSeibert\Ldap\Service\Mapping\GenericMapper;


/**
 * Model for objects read from LDAP server.
 */
abstract class LdapEntity
{
    protected string $dn;

    protected array $attributes;

    protected LdapServer $ldapServer;

    protected GenericMapper $mapper;

    protected string $userType;

    public function __construct(
        GenericMapper $mapper)
    {
        $this->mapper = $mapper;
    }

    public function setDN(string $dn): object
    {
        $this->dn = $dn;

        return $this;
    }

    public function getDN(): string
    {
        return $this->dn;
    }

    public function setAttributes(array $attrs): object
    {
        $this->attributes = $attrs;

        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttribute(string $attr, string $value): object
    {
        $this->attributes[$attr] = $value;

        return $this;
    }

    public function getAttribute(string $attr): string
    {
        return $this->attributes[$attr];
    }

    public function getLdapServer(): LdapServer
    {
        return $this->ldapServer;
    }

    public function getUserType(): string
    {
        return $this->userType;
    }
}
