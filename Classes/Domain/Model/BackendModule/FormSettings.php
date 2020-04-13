<?php

namespace NormanSeibert\Ldap\Domain\Model\BackendModule;

/**
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
 * @author	  Norman Seibert <seibert@entios.de>
 * @copyright 2020 Norman Seibert
 */

/**
 * Model for the backend module form.
 */
class FormSettings extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    /**
     * @var bool
     */
    protected $authenticateFe = false;

    /**
     * @var bool
     */
    protected $authenticateBe = false;

    /**
     * @var bool
     */
    protected $hideNotDelete = false;

    /**
     * @var string
     */
    protected $loginname = '';

    /**
     * @var string
     */
    protected $password = '';

    /**
     * @var string
     */
    protected $loginType = 'fe';

    /**
     * @var array
     */
    protected $useServers;

    /**
     * @var bool
     */
    protected $deleteNonLdapUsers = false;

    /**
     * @param bool $fe
     */
    public function setAuthenticateFe($fe)
    {
        $this->authenticateFe = $fe;
    }

    /**
     * @return bool
     */
    public function getAuthenticateFe()
    {
        return $this->authenticateFe;
    }

    /**
     * @param bool $be
     */
    public function setAuthenticateBe($be)
    {
        $this->authenticateBe = $be;
    }

    /**
     * @return bool
     */
    public function getAuthenticateBe()
    {
        return $this->authenticateBe;
    }

    /**
     * @param bool $hide
     */
    public function setHideNotDelete($hide)
    {
        $this->hideNotDelete = $hide;
    }

    /**
     * @return bool
     */
    public function getHideNotDelete()
    {
        return $this->hideNotDelete;
    }

    /**
     * @param bool $nonLdap
     */
    public function setDeleteNonLdapUsers($nonLdap)
    {
        $this->deleteNonLdapUsers = $nonLdap;
    }

    /**
     * @return bool
     */
    public function getDeleteNonLdapUsers()
    {
        return $this->deleteNonLdapUsers;
    }

    /**
     * @param string
     * @param mixed $username
     */
    public function setLoginname($username)
    {
        $this->loginname = $username;
    }

    /**
     * @return string
     */
    public function getLoginname()
    {
        return $this->loginname;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $login
     */
    public function setLoginType($login)
    {
        $this->loginType = $login;
    }

    /**
     * @return string
     */
    public function getLoginType()
    {
        return $this->loginType;
    }

    /**
     * @param array $servers
     */
    public function setUseServers($servers)
    {
        $this->useServers = $servers;
    }

    /**
     * @return array
     */
    public function getUseServers()
    {
        return $this->useServers;
    }
}
