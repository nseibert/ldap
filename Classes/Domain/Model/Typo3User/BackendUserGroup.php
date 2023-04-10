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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Model for TYPO3 backend users.
 */
class BackendUserGroup extends AbstractEntity implements \NormanSeibert\Ldap\Domain\Model\Typo3User\UserGroupInterface
{
    public const FILE_OPPERATIONS = 1;
    public const DIRECTORY_OPPERATIONS = 4;
    public const DIRECTORY_COPY = 8;
    public const DIRECTORY_REMOVE_RECURSIVELY = 16;

    /**
     * @var string
     */
    protected $title = '';

    /**
     * @var string
     */
    protected $description = '';

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage
     */
    protected $subGroups;

    /**
     * @var string
     */
    protected $modules = '';

    /**
     * @var string
     */
    protected $tablesListening = '';

    /**
     * @var string
     */
    protected $tablesModify = '';

    /**
     * @var string
     */
    protected $pageTypes = '';

    /**
     * @var string
     */
    protected $allowedExcludeFields = '';

    /**
     * @var string
     */
    protected $explicitlyAllowAndDeny = '';

    /**
     * @var string
     */
    protected $allowedLanguages = '';

    /**
     * @var bool
     */
    protected $workspacePermission = false;

    /**
     * @var string
     */
    protected $databaseMounts = '';

    /**
     * @var int
     */
    protected $fileOperationPermissions = 0;

    /**
     * @var string
     */
    protected $tsConfig = '';

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
     * Constructs this backend usergroup
     */
    public function __construct()
    {
        $this->subGroups = new ObjectStorage();
    }

    /**
     * Setter for title
     *
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Getter for title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Setter for description
     *
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Getter for description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Setter for the sub groups
     *
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage $subGroups
     */
    public function setSubGroups(ObjectStorage $subGroups)
    {
        $this->subGroups = $subGroups;
    }

    /**
     * Adds a sub group to this backend user group
     *
     * @param BackendUserGroup $beGroup
     */
    public function addSubGroup(BackendUserGroup $beGroup)
    {
        $this->subGroups->attach($beGroup);
    }

    /**
     * Removes sub group from this backend user group
     *
     * @param BackendUserGroup $groupToDelete
     */
    public function removeSubGroup(BackendUserGroup $groupToDelete)
    {
        $this->subGroups->detach($groupToDelete);
    }

    /**
     * Remove all sub groups from this backend user group
     */
    public function removeAllSubGroups()
    {
        $subGroups = clone $this->subGroups;
        $this->subGroups->removeAll($subGroups);
    }

    /**
     * Getter of sub groups
     *
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage
     */
    public function getSubGroups()
    {
        return $this->subGroups;
    }

    /**
     * Setter for modules
     *
     * @param string $modules
     */
    public function setModules($modules)
    {
        $this->modules = $modules;
    }

    /**
     * Getter for modules
     *
     * @return string
     */
    public function getModules()
    {
        return $this->modules;
    }

    /**
     * Setter for tables listening
     *
     * @param string $tablesListening
     */
    public function setTablesListening($tablesListening)
    {
        $this->tablesListening = $tablesListening;
    }

    /**
     * Getter for tables listening
     *
     * @return string
     */
    public function getTablesListening()
    {
        return $this->tablesListening;
    }

    /**
     * Setter for tables modify
     *
     * @param string $tablesModify
     */
    public function setTablesModify($tablesModify)
    {
        $this->tablesModify = $tablesModify;
    }

    /**
     * Getter for tables modify
     *
     * @return string
     */
    public function getTablesModify()
    {
        return $this->tablesModify;
    }

    /**
     * Setter for page types
     *
     * @param string $pageTypes
     */
    public function setPageTypes($pageTypes)
    {
        $this->pageTypes = $pageTypes;
    }

    /**
     * Getter for page types
     *
     * @return string
     */
    public function getPageTypes()
    {
        return $this->pageTypes;
    }

    /**
     * Setter for allowed exclude fields
     *
     * @param string $allowedExcludeFields
     */
    public function setAllowedExcludeFields($allowedExcludeFields)
    {
        $this->allowedExcludeFields = $allowedExcludeFields;
    }

    /**
     * Getter for allowed exclude fields
     *
     * @return string
     */
    public function getAllowedExcludeFields()
    {
        return $this->allowedExcludeFields;
    }

    /**
     * Setter for explicitly allow and deny
     *
     * @param string $explicitlyAllowAndDeny
     */
    public function setExplicitlyAllowAndDeny($explicitlyAllowAndDeny)
    {
        $this->explicitlyAllowAndDeny = $explicitlyAllowAndDeny;
    }

    /**
     * Getter for explicitly allow and deny
     *
     * @return string
     */
    public function getExplicitlyAllowAndDeny()
    {
        return $this->explicitlyAllowAndDeny;
    }

    /**
     * Setter for allowed languages
     *
     * @param string $allowedLanguages
     */
    public function setAllowedLanguages($allowedLanguages)
    {
        $this->allowedLanguages = $allowedLanguages;
    }

    /**
     * Getter for allowed languages
     *
     * @return string
     */
    public function getAllowedLanguages()
    {
        return $this->allowedLanguages;
    }

    /**
     * Setter for workspace permission
     *
     * @param bool $workspacePermission
     */
    public function setWorkspacePermissions($workspacePermission)
    {
        $this->workspacePermission = $workspacePermission;
    }

    /**
     * Getter for workspace permission
     *
     * @return bool
     */
    public function getWorkspacePermission()
    {
        return $this->workspacePermission;
    }

    /**
     * Setter for database mounts
     *
     * @param string $databaseMounts
     */
    public function setDatabaseMounts($databaseMounts)
    {
        $this->databaseMounts = $databaseMounts;
    }

    /**
     * Getter for database mounts
     *
     * @return string
     */
    public function getDatabaseMounts()
    {
        return $this->databaseMounts;
    }

    /**
     * Getter for file operation permissions
     *
     * @param int $fileOperationPermissions
     */
    public function setFileOperationPermissions($fileOperationPermissions)
    {
        $this->fileOperationPermissions = $fileOperationPermissions;
    }

    /**
     * Getter for file operation permissions
     *
     * @return int
     */
    public function getFileOperationPermissions()
    {
        return $this->fileOperationPermissions;
    }

    /**
     * Check if file operations like upload, copy, move, delete, rename, new and
     * edit files is allowed.
     *
     * @return bool
     */
    public function isFileOperationAllowed()
    {
        return $this->isPermissionSet(self::FILE_OPPERATIONS);
    }

    /**
     * Set the the bit for file operations are allowed.
     *
     * @param bool $value
     */
    public function setFileOperationAllowed($value)
    {
        $this->setPermission(self::FILE_OPPERATIONS, $value);
    }

    /**
     * Check if folder operations like move, delete, rename, and new are allowed.
     *
     * @return bool
     */
    public function isDirectoryOperationAllowed()
    {
        return $this->isPermissionSet(self::DIRECTORY_OPPERATIONS);
    }

    /**
     * Set the the bit for directory operations are allowed.
     *
     * @param bool $value
     */
    public function setDirectoryOperationAllowed($value)
    {
        $this->setPermission(self::DIRECTORY_OPPERATIONS, $value);
    }

    /**
     * Check if it is allowed to copy folders.
     *
     * @return bool
     */
    public function isDirectoryCopyAllowed()
    {
        return $this->isPermissionSet(self::DIRECTORY_COPY);
    }

    /**
     * Set the the bit for copy directories.
     *
     * @param bool $value
     */
    public function setDirectoryCopyAllowed($value)
    {
        $this->setPermission(self::DIRECTORY_COPY, $value);
    }

    /**
     * Check if it is allowed to remove folders recursively.
     *
     * @return bool
     */
    public function isDirectoryRemoveRecursivelyAllowed()
    {
        return $this->isPermissionSet(self::DIRECTORY_REMOVE_RECURSIVELY);
    }

    /**
     * Set the the bit for remove directories recursively.
     *
     * @param bool $value
     */
    public function setDirectoryRemoveRecursivelyAllowed($value)
    {
        $this->setPermission(self::DIRECTORY_REMOVE_RECURSIVELY, $value);
    }

    /**
     * Setter for ts config
     *
     * @param string $tsConfig
     */
    public function setTsConfig($tsConfig)
    {
        $this->tsConfig = $tsConfig;
    }

    /**
     * Getter for ts config
     *
     * @return string
     */
    public function getTsConfig()
    {
        return $this->tsConfig;
    }

    /**
     * Helper method for checking the permissions bitwise.
     *
     * @param int $permission
     * @return bool
     */
    protected function isPermissionSet($permission)
    {
        return ($this->fileOperationPermissions & $permission) == $permission;
    }

    /**
     * Helper method for setting permissions bitwise.
     *
     * @param int $permission
     * @param bool $value
     */
    protected function setPermission($permission, $value)
    {
        if ($value) {
            $this->fileOperationPermissions |= $permission;
        } else {
            $this->fileOperationPermissions &= ~$permission;
        }
    }

    /**
     * @param string $dn
     *
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUserGroup
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
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUserGroup
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
    public function getLdapUsergroup()
    {
        $group = false;
        if ($this->dn && $this->serverUid) {
            $ldapConfig = GeneralUtility::makeInstance(Configuration::class);
            $server = $ldapConfig->getLdapServer($this->serverUid);
            $user = $server->getUser($this->dn);
        }

        return $group;
    }

    /**
     * @param string $run
     *
     * @return \NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUserGroup
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
