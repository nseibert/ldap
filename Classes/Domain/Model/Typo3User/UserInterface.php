<?php

namespace NormanSeibert\Ldap\Domain\Model\Typo3User;

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
interface UserInterface
{
    /**
     * @return int
     */
    public function getUid(): ?int;

    /**
     * Checks whether this user is disabled.
     *
     * @return bool whether this user is disabled
     */
    public function getIsDisabled();

    /**
     * Sets whether this user is disabled.
     *
     * @param bool $isDisabled whether this user is disabled
     */
    public function setIsDisabled($isDisabled);

    /**
     * @return object
     */
    public function setDN(string $dn);

    /**
     * @return string
     */
    public function getDN();

    /**
     * @return object
     */
    public function setServerUid(string $uid);

    /**
     * @return string
     */
    public function getServerUid();

    /**
     * @return object
     */
    public function getLdapUser();

    /**
     * @return object
     */
    public function generatePassword();

    /**
     * @return object
     */
    public function setLastRun(string $run);

    /**
     * @return string
     */
    public function getLastRun();

    /**
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage
     */
    public function getUsergroup();

    /**
     * Sets the usergroups. Keep in mind that the property is called "usergroup"
     * although it can hold several usergroups.
     */
    public function setUsergroup(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $usergroup);

    /**
     * Sets the username value.
     *
     * @api
     */
    public function setUsername(string $username);

    /**
     * Returns the username value.
     *
     * @return string
     *
     * @api
     */
    public function getUsername();

    /**
     * Setter for the pid.
     *
     * @param null|int $pid
     */
    public function setPid(int $pid);

    /**
     * Reconstitutes a property. Only for internal use.
     *
     * @param mixed $propertyValue
     *
     * @return bool
     */
    public function _setProperty(string $propertyName, $propertyValue);
}
