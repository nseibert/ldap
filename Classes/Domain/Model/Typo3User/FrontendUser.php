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
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Model for TYPO3 frontend users.
 */
class FrontendUser extends AbstractEntity implements \NormanSeibert\Ldap\Domain\Model\Typo3User\UserInterface
{
    /**
     * @var string
     */
    protected $username = '';

    /**
     * @var string
     */
    protected $password = '';

    /**
     * @var ObjectStorage<FrontendUserGroup>
     */
    protected $usergroup;

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var string
     */
    protected $firstName = '';

    /**
     * @var string
     */
    protected $middleName = '';

    /**
     * @var string
     */
    protected $lastName = '';

    /**
     * @var string
     */
    protected $address = '';

    /**
     * @var string
     */
    protected $telephone = '';

    /**
     * @var string
     */
    protected $fax = '';

    /**
     * @var string
     */
    protected $email = '';

    /**
     * @var string
     */
    protected $title = '';

    /**
     * @var string
     */
    protected $zip = '';

    /**
     * @var string
     */
    protected $city = '';

    /**
     * @var string
     */
    protected $country = '';

    /**
     * @var string
     */
    protected $www = '';

    /**
     * @var string
     */
    protected $company = '';

    /**
     * @var \DateTime|null
     */
    protected $lastlogin;
    /**
     * @var bool
     */
    protected $isDisabled = false;

    /**
     * @var string
     */
    protected $dn;

    /**
     * @var int
     */
    protected $serverUid;

    /**
     * @var string
     */
    protected $lastRun;

    /**
     * Constructs a new Front-End User
     *
     * @param string $username
     * @param string $password
     */
    public function __construct($username = '', $password = '')
    {
        $this->username = $username;
        $this->password = $password;
        $this->usergroup = new ObjectStorage();
    }

    /**
     * Called again with initialize object, as fetching an entity from the DB does not use the constructor
     */
    public function initializeObject()
    {
        $this->usergroup = $this->usergroup ?? new ObjectStorage();
    }

    /**
     * Sets the username value
     *
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * Returns the username value
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Sets the password value
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * Returns the password value
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Sets the usergroups. Keep in mind that the property is called "usergroup"
     * although it can hold several usergroups.
     *
     * @param ObjectStorage<FrontendUserGroup> $usergroup
     */
    public function setUsergroup(ObjectStorage $usergroup)
    {
        $this->usergroup = $usergroup;
    }

    /**
     * Adds a usergroup to the frontend user
     *
     * @param FrontendUserGroup $usergroup
     */
    public function addUsergroup(FrontendUserGroup $usergroup)
    {
        $this->usergroup->attach($usergroup);
    }

    /**
     * Removes a usergroup from the frontend user
     *
     * @param FrontendUserGroup $usergroup
     */
    public function removeUsergroup(FrontendUserGroup $usergroup)
    {
        $this->usergroup->detach($usergroup);
    }

    /**
     * Returns the usergroups. Keep in mind that the property is called "usergroup"
     * although it can hold several usergroups.
     *
     * @return ObjectStorage<FrontendUserGroup> An object storage containing the usergroup
     */
    public function getUsergroup()
    {
        return $this->usergroup;
    }

    /**
     * Sets the name value
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Returns the name value
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the firstName value
     *
     * @param string $firstName
     */
    public function setFirstName($firstName)
    {
        $this->firstName = $firstName;
    }

    /**
     * Returns the firstName value
     *
     * @return string
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * Sets the middleName value
     *
     * @param string $middleName
     */
    public function setMiddleName($middleName)
    {
        $this->middleName = $middleName;
    }

    /**
     * Returns the middleName value
     *
     * @return string
     */
    public function getMiddleName()
    {
        return $this->middleName;
    }

    /**
     * Sets the lastName value
     *
     * @param string $lastName
     */
    public function setLastName($lastName)
    {
        $this->lastName = $lastName;
    }

    /**
     * Returns the lastName value
     *
     * @return string
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * Sets the address value
     *
     * @param string $address
     */
    public function setAddress($address)
    {
        $this->address = $address;
    }

    /**
     * Returns the address value
     *
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Sets the telephone value
     *
     * @param string $telephone
     */
    public function setTelephone($telephone)
    {
        $this->telephone = $telephone;
    }

    /**
     * Returns the telephone value
     *
     * @return string
     */
    public function getTelephone()
    {
        return $this->telephone;
    }

    /**
     * Sets the fax value
     *
     * @param string $fax
     */
    public function setFax($fax)
    {
        $this->fax = $fax;
    }

    /**
     * Returns the fax value
     *
     * @return string
     */
    public function getFax()
    {
        return $this->fax;
    }

    /**
     * Sets the email value
     *
     * @param string $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * Returns the email value
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Sets the title value
     *
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Returns the title value
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Sets the zip value
     *
     * @param string $zip
     */
    public function setZip($zip)
    {
        $this->zip = $zip;
    }

    /**
     * Returns the zip value
     *
     * @return string
     */
    public function getZip()
    {
        return $this->zip;
    }

    /**
     * Sets the city value
     *
     * @param string $city
     */
    public function setCity($city)
    {
        $this->city = $city;
    }

    /**
     * Returns the city value
     *
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * Sets the country value
     *
     * @param string $country
     */
    public function setCountry($country)
    {
        $this->country = $country;
    }

    /**
     * Returns the country value
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * Sets the www value
     *
     * @param string $www
     */
    public function setWww($www)
    {
        $this->www = $www;
    }

    /**
     * Returns the www value
     *
     * @return string
     */
    public function getWww()
    {
        return $this->www;
    }

    /**
     * Sets the company value
     *
     * @param string $company
     */
    public function setCompany($company)
    {
        $this->company = $company;
    }

    /**
     * Returns the company value
     *
     * @return string
     */
    public function getCompany()
    {
        return $this->company;
    }

    /**
     * Sets the lastlogin value
     *
     * @param \DateTime $lastlogin
     */
    public function setLastlogin(\DateTime $lastlogin)
    {
        $this->lastlogin = $lastlogin;
    }

    /**
     * Returns the lastlogin value
     *
     * @return \DateTime
     */
    public function getLastlogin()
    {
        return $this->lastlogin;
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
     * @param string $dn
     *
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser
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
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser
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
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser
     */
    public function generatePassword()
    {
        $password = GeneralUtility::makeInstance(Random::class)->generateRandomHexString(20);
        $hashInstance = GeneralUtility::makeInstance(PasswordHashFactory::class)->getDefaultHashInstance('FE');
        $hashedPassword = $hashInstance->getHashedPassword($password);
        $this->password = $hashedPassword;

        return $this;
    }

    /**
     * @param string $run
     *
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser
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
}
