<?php

return [
    'tools_ldap' => [
        'parent' => 'tools',
        'position' => ['after' => '*'],
        'access' => 'user,group',
        'workspaces' => 'live',
        'iconIdentifier' => 'extension-ldap-main',
        'path' => '/module/tools/ldap',
        'labels' => 'LLL:EXT:ldap/Resources/Private/Language/locallang.xml',
        'extensionName' => 'ldap',
        'controllerActions' => [
            \NormanSeibert\Ldap\Controller\ModuleController::class => [
                'check',
                'summary',
                'importUsers',
                'doImportUsers',
                'updateUsers',
                'doUpdateUsers',
                'importAndUpdateUsers',
                'doImportAndUpdateUsers',
                'deleteUsers',
                'doDeleteUsers',
                'checkLogin',
                'doCheckLogin',
            ],
        ],
    ],
];