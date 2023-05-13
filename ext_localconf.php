<?php

defined('TYPO3') or die();

(static function (string $_EXTKEY) {
    // Configuration of authentication service
    $config = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][$_EXTKEY] ?? [];

    $subTypesArr = [];
    $subTypes = '';
    if (isset($config['enableFE']) && $config['enableFE']) {
        $subTypesArr[] = 'getUserFE';
        $subTypesArr[] = 'authUserFE';
    }
    if (isset($config['enableBE']) && $config['enableBE']) {
        $subTypesArr[] = 'getUserBE';
        $subTypesArr[] = 'authUserBE';
    }
    if (is_array($subTypesArr)) {
        $subTypesArr = array_unique($subTypesArr);
        $subTypes = implode(',', $subTypesArr);
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService(
            $_EXTKEY,
            'auth',
            \NormanSeibert\Ldap\Service\LdapAuthService::class,
            [
                'title' => 'LDAP-Authentication',
                'description' => 'Authentication service for LDAP (FE and BE).',
                'subtype' => $subTypes,
                'available' => 1,
                'priority' => 75,
                'quality' => 75,
                'os' => '',
                'exec' => '',
                'className' => \NormanSeibert\Ldap\Service\LdapAuthService::class,
            ]
        );
    }

    $GLOBALS['TYPO3_CONF_VARS']['LOG']['NormanSeibert']['Ldap']['writerConfiguration'] = [
        \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
            \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                'logFileInfix' => 'ldap',
            ],
        ],
    ];
})('ldap');