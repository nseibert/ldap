<?php

$tempColumns = [
    'tx_ldap_dn' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:ldap/Resources/Private/Language/locallang_db.xml:fe_users.tx_ldap_dn',
        'config' => [
            'type' => 'none',
        ],
    ],
    'tx_ldap_serveruid' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:ldap/Resources/Private/Language/locallang_db.xml:fe_users.tx_ldap_server',
        'config' => [
            'type' => 'none',
        ],
    ],
    'tx_ldap_lastrun' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:ldap/Resources/Private/Language/locallang_db.xml:fe_users.tx_ldap_lastrun',
        'config' => [
            'type' => 'none',
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('be_groups', $tempColumns);
