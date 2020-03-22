<?php
declare(strict_types = 1);

return [
    NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser::class => [
        'tableName' => 'fe_users',
        'recordType' => '',
        'properties' => [
            'dn' => [
                'fieldName' => 'tx_ldap_dn'
            ],
            'serverUid' => [
                'fieldName' => 'tx_ldap_serveruid'
            ],
            'lastRun' => [
                'fieldName' => 'tx_ldap_lastrun'
            ],
            'isDisabled' => [
                'fieldName' => 'disable'
            ],
        ],
    ],
    NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUser::class => [
        'tableName' => 'be_users',
        'properties' => [
            'username' => [
                'fieldName' => 'username'
            ],
            'dn' => [
                'fieldName' => 'tx_ldap_dn'
            ],
            'serverUid' => [
                'fieldName' => 'tx_ldap_serveruid'
            ],
            'lastRun' => [
                'fieldName' => 'tx_ldap_lastrun'
            ],
            'databaseMounts' => [
                'fieldName' => 'db_mountpoints'
            ],
            'fileMounts' => [
                'fieldName' => 'file_mountpoints'
            ],
            'isDisabled' => [
                'fileOperationPermissions' => 'file_permissions'
            ],
            'options' => [
                'fieldName' => 'options'
            ],
        ],
    ],
    NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUserGroup::class => [
        'tableName' => 'fe_groups',
        'properties' => [
            'dn' => [
                'fieldName' => 'tx_ldap_dn'
            ],
            'serverUid' => [
                'fieldName' => 'tx_ldap_serveruid'
            ],
            'lastRun' => [
                'fieldName' => 'tx_ldap_lastrun'
            ],
        ],
    ],
    NormanSeibert\Ldap\Domain\Model\Typo3User\BackendUserGroup::class => [
        'tableName' => 'be_groups',
        'properties' => [
            'dn' => [
                'fieldName' => 'tx_ldap_dn'
            ],
            'serverUid' => [
                'fieldName' => 'tx_ldap_serveruid'
            ],
            'lastRun' => [
                'fieldName' => 'tx_ldap_lastrun'
            ],
        ],
    ],
];