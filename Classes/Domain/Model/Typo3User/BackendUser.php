<?php

namespace NormanSeibert\Ldap\Domain\Model\Typo3User;

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

use NormanSeibert\Ldap\Domain\Model\Configuration\Configuration;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Model for TYPO3 backend users.
 */
class BackendUser extends AbstractEntity implements \NormanSeibert\Ldap\Domain\Model\Typo3User\UserInterface
{
    /**
     * @var string
     */
    protected $userName = '';

    /**
     * @var string
     */
    protected $description = '';

    /**
     * @var bool
     */
    protected $isAdministrator = false;

    /**
     * @var bool
     */
    protected $isDisabled = false;

    /**
     * @var \DateTime|null
     */
    protected $startDateAndTime;

    /**
     * @var \DateTime|null
     */
    protected $endDateAndTime;

    /**
     * @var string
     */
    protected $email = '';

    /**
     * @var string
     */
    protected $realName = '';

    /**
     * @var \DateTime|null
     */
    protected $lastLoginDateAndTime;
    
    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $dn;

    /**
     * @var \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
     */
    protected $ldapServer;

    /**
     * @var int
     */
    protected $serverUid;

    /**
     * @var string
     */
    protected $password = '';

    /**
     * @var string
     */
    protected $lastRun;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\BackendUserGroup>
     */
    protected $usergroup;

    /**
     * @var string
     */
    protected $databaseMounts;

    /**
     * @var string
     */
    protected $fileMounts;

    /**
     * @var string
     */
    protected $fileOperationPermissions;

    /**
     * @var int
     */
    protected $options;

    public function __construct()
    {
        $this->usergroup = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
    }

    /**
     * Gets the user name.
     *
     * @return string the user name, will not be empty
     */
    public function getUserName()
    {
        return $this->userName;
    }

    /**
     * Sets the user name.
     *
     * @param string $userName the user name to set, must not be empty
     */
    public function setUserName($userName)
    {
        $this->userName = $userName;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Checks whether this user is an administrator.
     *
     * @return bool whether this user is an administrator
     */
    public function getIsAdministrator()
    {
        return $this->isAdministrator;
    }

    /**
     * Sets whether this user should be an administrator.
     *
     * @param bool $isAdministrator whether this user should be an administrator
     */
    public function setIsAdministrator($isAdministrator)
    {
        $this->isAdministrator = $isAdministrator;
    }

    /**
     * Checks whether this user is disabled.
     *
     * @return bool whether this user is disabled
     */
    public function getIsDisabled()
    {
        return $this->isDisabled;
    }

    /**
     * Sets whether this user is disabled.
     *
     * @param bool $isDisabled whether this user is disabled
     */
    public function setIsDisabled($isDisabled)
    {
        $this->isDisabled = $isDisabled;
    }

    /**
     * Returns the point in time from which this user is enabled.
     *
     * @return \DateTime|null the start date and time
     */
    public function getStartDateAndTime()
    {
        return $this->startDateAndTime;
    }

    /**
     * Sets the point in time from which this user is enabled.
     *
     * @param \DateTime|null $dateAndTime the start date and time
     */
    public function setStartDateAndTime(\DateTime $dateAndTime = null)
    {
        $this->startDateAndTime = $dateAndTime;
    }

    /**
     * Returns the point in time before which this user is enabled.
     *
     * @return \DateTime|null the end date and time
     */
    public function getEndDateAndTime()
    {
        return $this->endDateAndTime;
    }

    /**
     * Sets the point in time before which this user is enabled.
     *
     * @param \DateTime|null $dateAndTime the end date and time
     */
    public function setEndDateAndTime(\DateTime $dateAndTime = null)
    {
        $this->endDateAndTime = $dateAndTime;
    }

    /**
     * Gets the e-mail address of this user.
     *
     * @return string the e-mail address, might be empty
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Sets the e-mail address of this user.
     *
     * @param string $email the e-mail address, may be empty
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * Returns this user's real name.
     *
     * @return string the real name. might be empty
     */
    public function getRealName()
    {
        return $this->realName;
    }

    /**
     * Sets this user's real name.
     *
     * @param string $name the user's real name, may be empty.
     */
    public function setRealName($name)
    {
        $this->realName = $name;
    }

    /**
     * Checks whether this user is currently activated.
     *
     * This function takes the "disabled" flag, the start date/time and the end date/time into account.
     *
     * @return bool whether this user is currently activated
     */
    public function isActivated()
    {
        return !$this->getIsDisabled() && $this->isActivatedViaStartDateAndTime() && $this->isActivatedViaEndDateAndTime();
    }

    /**
     * Checks whether this user is activated as far as the start date and time is concerned.
     *
     * @return bool whether this user is activated as far as the start date and time is concerned
     */
    protected function isActivatedViaStartDateAndTime()
    {
        if ($this->getStartDateAndTime() === null) {
            return true;
        }
        $now = new \DateTime('now');
        return $this->getStartDateAndTime() <= $now;
    }

    /**
     * Checks whether this user is activated as far as the end date and time is concerned.
     *
     * @return bool whether this user is activated as far as the end date and time is concerned
     */
    protected function isActivatedViaEndDateAndTime()
    {
        if ($this->getEndDateAndTime() === null) {
            return true;
        }
        $now = new \DateTime('now');
        return $now <= $this->getEndDateAndTime();
    }

    /**
     * Gets this user's last login date and time.
     *
     * @return \DateTime|null this user's last login date and time, will be NULL if this user has never logged in before
     */
    public function getLastLoginDateAndTime()
    {
        return $this->lastLoginDateAndTime;
    }

    /**
     * Sets this user's last login date and time.
     *
     * @param \DateTime|null $dateAndTime this user's last login date and time
     */
    public function setLastLoginDateAndTime(\DateTime $dateAndTime = null)
    {
        $this->lastLoginDateAndTime = $dateAndTime;
    }

    /**
     * @param string $dn
     *
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser
     */
    public function setDN($dn)
    {
        $this->dn = $dn;

        return $this;
    }

    /**
     * @return string
     */
    public function getDN()
    {
        return $this->dn;
    }

    /**
     * @param int $uid
     *
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser
     */
    public function setServerUid($uid)
    {
        $this->serverUid = $uid;

        return $this;
    }

    /**
     * @return int
     */
    public function getServerUid()
    {
        return $this->serverUid;
    }

    /**
     * @return \NormanSeibert\Ldap\Domain\Model\LdapUser\User
     */
    public function getLdapUser()
    {
        $user = false;
        if ($this->dn && $this->serverUid) {
            $ldapConfig = GeneralUtility::makeInstance(Configuration::class);
            $server = $ldapConfig->getLdapServer($this->serverUid);
            $user = $server->getUser($this->dn);
        }

        return $user;
    }

    /**
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser
     */
    public function generatePassword()
    {
        $password = GeneralUtility::makeInstance(Random::class)->generateRandomHexString(20);
        $hashInstance = GeneralUtility::makeInstance(PasswordHashFactory::class)->getDefaultHashInstance('BE');
        $hashedPassword = $hashInstance->getHashedPassword($password);
        $this->password = $hashedPassword;

        return $this;
    }

    /**
     * @param string $run
     *
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser
     */
    public function setLastRun($run)
    {
        $this->lastRun = $run;

        return $this;
    }

    /**
     * @return string
     */
    public function getLastRun()
    {
        return $this->lastRun;
    }

    /**
     * Adds a usergroup to the backend user.
     */
    public function addUsergroup(BackendUserGroup $usergroup)
    {
        $this->usergroup->attach($usergroup);
    }

    /**
     * Removes a usergroup from the backend user.
     */
    public function removeUsergroup(BackendUserGroup $usergroup)
    {
        $this->usergroup->detach($usergroup);
    }

    /**
     * Returns the usergroups. Keep in mind that the property is called "usergroup"
     * although it can hold several usergroups.
     *
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage An object storage containing the usergroup
     */
    public function getUsergroup()
    {
        return $this->usergroup;
    }

    /**
     * Sets the usergroups. Keep in mind that the property is called "usergroup"
     * although it can hold several usergroups.
     *
     * @api
     */
    public function setUsergroup(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $usergroup)
    {
        $this->usergroup = $usergroup;
    }

    /**
     * @param string $mounts
     *
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser
     */
    public function setDatabaseMounts($mounts)
    {
        $this->databaseMounts = $mounts;

        return $this;
    }

    /**
     * @return string
     */
    public function getDatabaseMounts()
    {
        return $this->databaseMounts;
    }

    /**
     * @param string $mounts
     *
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser
     */
    public function setFileMounts($mounts)
    {
        $this->fileMounts = $mounts;

        return $this;
    }

    /**
     * @return string
     */
    public function getFileMounts()
    {
        return $this->fileMounts;
    }

    /**
     * @param string $permissions
     *
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser
     */
    public function setFileOperationPermissions($permissions)
    {
        $this->fileOperationPermissions = $permissions;

        return $this;
    }

    /**
     * @return string
     */
    public function getFileOperationPermissions()
    {
        return $this->fileOperationPermissions;
    }

    /**
     * @param string $mounts
     * @param mixed  $options
     *
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser
     */
    public function setOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return int
     */
    public function getOptions()
    {
        return $this->options;
    }
}
