<?php

declare(strict_types=1);

return [
    NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser::class => [
        'tableName' => 'fe_users',
        'recordType' => '',
        'properties' => [
            'username' => [
                'fieldName' => 'username',
            ],
            'password' => [
                'fieldName' => 'password',
            ],
            'usergroup' => [
                'fieldName' => 'usergroup',
            ],
            'name' => [
                'fieldName' => 'name',
            ],
            'firstName' => [
                'fieldName' => 'first_name',
            ],
            'middletName' => [
                'fieldName' => 'middle_name',
            ],
            'lastName' => [
                'fieldName' => 'last_name',
            ],
            'address' => [
                'fieldName' => 'address',
            ],
            'telephone' => [
                'fieldName' => 'telephone',
            ],
            'fax' => [
                'fieldName' => 'fax',
            ],
            'email' => [
                'fieldName' => 'email',
            ],
            'zip' => [
                'fieldName' => 'zip',
            ],
            'city' => [
                'fieldName' => 'city',
            ],
            'country' => [
                'fieldName' => 'country',
            ],
            'www' => [
                'fieldName' => 'www',
            ],
            'company' => [
                'fieldName' => 'company',
            ],
            'lastlogin' => [
                'fieldName' => 'lastlogin',
            ],
            'dn' => [
                'fieldName' => 'tx_ldap_dn',
            ],
            'serverUid' => [
                'fieldName' => 'tx_ldap_serveruid',
            ],
            'lastRun' => [
                'fieldName' => 'tx_ldap_lastrun',
            ],
            'isDisabled' => [
                'fieldName' => 'disable',
            ],
        ],
    ],
    NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser::class => [
        'tableName' => 'be_users',
        'properties' => [
            'isAdministrator' => [
                'fieldName' => 'admin',
            ],
            'isDisabled' => [
                'fieldName' => 'disable',
                'fileOperationPermissions' => 'file_permissions',
            ],
            'realName' => [
                'fieldName' => 'realName',
            ],
            'startDateAndTime' => [
                'fieldName' => 'starttime',
            ],
            'endDateAndTime' => [
                'fieldName' => 'endtime',
            ],
            'lastLoginDateAndTime' => [
                'fieldName' => 'lastlogin',
            ],
            'username' => [
                'fieldName' => 'username',
            ],
            'dn' => [
                'fieldName' => 'tx_ldap_dn',
            ],
            'serverUid' => [
                'fieldName' => 'tx_ldap_serveruid',
            ],
            'lastRun' => [
                'fieldName' => 'tx_ldap_lastrun',
            ],
            'databaseMounts' => [
                'fieldName' => 'db_mountpoints',
            ],
            'fileMounts' => [
                'fieldName' => 'file_mountpoints',
            ],
            'options' => [
                'fieldName' => 'options',
            ],
        ],
    ],
    NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUserGroup::class => [
        'tableName' => 'fe_groups',
        'properties' => [
            'dn' => [
                'fieldName' => 'tx_ldap_dn',
            ],
            'serverUid' => [
                'fieldName' => 'tx_ldap_serveruid',
            ],
            'lastRun' => [
                'fieldName' => 'tx_ldap_lastrun',
            ],
        ],
    ],
    NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUserGroup::class => [
        'tableName' => 'be_groups',
        'properties' => [
            'subGroups' => [
                'fieldName' => 'subgroup',
            ],
            'modules' => [
                'fieldName' => 'groupMods',
            ],
            'tablesListening' => [
                'fieldName' => 'tables_select',
            ],
            'tablesModify' => [
                'fieldName' => 'tables_modify',
            ],
            'pageTypes' => [
                'fieldName' => 'pagetypes_select',
            ],
            'allowedExcludeFields' => [
                'fieldName' => 'non_exclude_fields',
            ],
            'explicitlyAllowAndDeny' => [
                'fieldName' => 'explicit_allowdeny',
            ],
            'allowedLanguages' => [
                'fieldName' => 'allowed_languages',
            ],
            'workspacePermission' => [
                'fieldName' => 'workspace_perms',
            ],
            'databaseMounts' => [
                'fieldName' => 'db_mountpoints',
            ],
            'fileOperationPermissions' => [
                'fieldName' => 'file_permissions',
            ],
            'tsConfig' => [
                'fieldName' => 'TSconfig',
            ],
            'dn' => [
                'fieldName' => 'tx_ldap_dn',
            ],
            'serverUid' => [
                'fieldName' => 'tx_ldap_serveruid',
            ],
            'lastRun' => [
                'fieldName' => 'tx_ldap_lastrun',
            ],
        ],
    ],
];
