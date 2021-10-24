<?php

//#######################################################################
// Extension Manager/Repository config file for ext "ldap".
//
// Auto generated 15-10-2012 11:11
//
// Manual updates:
// Only the data in the array - everything else is removed by next
// writing. "version" and "dependencies" must not be touched!
//#######################################################################

$EM_CONF[$_EXTKEY] = [
    'title' => 'LDAP',
    'description' => 'LDAP Integration',
    'category' => 'module',
    'shy' => 0,
    'version' => '3.4.9',
    'dependencies' => 'extbase,fluid',
    'modify_tables' => 'fe_users,fe_groups,be_users,be_groups',
    'conflicts' => '',
    'priority' => '',
    'loadOrder' => '',
    'module' => 'mod1',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearcacheonload' => 0,
    'lockType' => '',
    'author' => 'Norman Seibert',
    'author_email' => 'seibert@entios.de',
    'author_company' => '',
    'CGLcompliance' => '',
    'CGLcompliance_note' => '',
    'constraints' => [
        'depends' => [
            'php' => '7.2.0-0.0.0',
            'typo3' => '10.4.0-10.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    '_md5_values_when_last_written' => '',
];
