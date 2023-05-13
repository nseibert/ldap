<?php

namespace NormanSeibert\Ldap\Domain\Model\LdapServer;

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
 * Model for an LDAP server's configuration.
 */
class ServerConfiguration extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    /**
     * @var int
     */
    protected $uid;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var bool
     */
    protected $disable;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var int
     */
    protected $port;

    /**
     * @var bool
     */
    protected $forceTLS;

    /**
     * @var int
     */
    protected $version;

    /**
     * @var string
     */
    protected $authenticate;

    /**
     * @var string
     */
    protected $user;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $longName;

    /**
     * @var ServerConfigurationUsers
     */
    protected $feUserRules;

    /**
     * @var ServerConfigurationUsers
     */
    protected $beUserRules;

    /**
     * @param string $uid
     *
     * @return ServerConfiguration
     */
    public function setUid($uid)
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
     * @param string $title
     *
     * @return ServerConfiguration
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param bool $disable
     *
     * @return ServerConfiguration
     */
    public function setDisable($disable)
    {
        $this->disable = $disable;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDisable()
    {
        return $this->disable;
    }

    /**
     * @return string
     */
    public function getLongName()
    {
        return $this->uid.' (title: '.$this->title.', host: '.$this->host.', port: '.$this->port.')';
    }

    /**
     * @param string $host
     *
     * @return ServerConfiguration
     */
    public function setHost($host)
    {
        $this->host = $host;

        return $this;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param int $port
     *
     * @return ServerConfiguration
     */
    public function setPort($port)
    {
        $this->port = $port;

        return $this;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param bool $force
     *
     * @return ServerConfiguration
     */
    public function setForceTLS($force)
    {
        $this->forceTLS = $force;

        return $this;
    }

    /**
     * @return bool
     */
    public function getForceTLS()
    {
        return $this->forceTLS;
    }

    /**
     * @param int $version
     *
     * @return ServerConfiguration
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param string $auth
     *
     * @return ServerConfiguration
     */
    public function setAuthenticate($auth)
    {
        $this->authenticate = $auth;

        return $this;
    }

    /**
     * @return string
     */
    public function getAuthenticate()
    {
        return $this->authenticate;
    }

    /**
     * @param string $user
     *
     * @return ServerConfiguration
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param string $pwd
     *
     * @return ServerConfiguration
     */
    public function setPassword($pwd)
    {
        $this->password = $pwd;

        return $this;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string                                                               $table
     * @param ServerConfigurationUsers $rules
     *
     * @return ServerConfiguration
     */
    public function setUserRules($table, ServerConfigurationUsers $rules)
    {
        if ('be_users' == $table) {
            $this->beUserRules = $rules;
        } else {
            $this->feUserRules = $rules;
        }

        return $this;
    }

    /**
     * @param string $table
     *
     * @return ServerConfigurationUsers
     */
    public function getUserRules($table)
    {
        if ('be_users' == $table) {
            $ret = $this->beUserRules;
        } else {
            $ret = $this->feUserRules;
        }

        return $ret;
    }

    /**
     * @param ServerConfigurationUsers $rules
     *
     * @return ServerConfiguration
     */
    public function setFeUserRules(ServerConfigurationUsers $rules)
    {
        $this->feUserRules = $rules;

        return $this;
    }

    /**
     * @return ServerConfigurationUsers
     */
    public function getFeUserRules()
    {
        return $this->feUserRules;
    }

    /**
     * @param ServerConfigurationUsers $rules
     *
     * @return ServerConfiguration
     */
    public function setBeUserRules(ServerConfigurationUsers $rules)
    {
        $this->beUserRules = $rules;

        return $this;
    }

    /**
     * @return ServerConfigurationUsers
     */
    public function getBeUserRules()
    {
        return $this->beUserRules;
    }
}
