<?php

defined('TYPO3') or die();

(function () {
    // Register the backend module
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'Ldap',
        'tools',	 // Make module a submodule of 'tools'
        'm1',	 // Submodule key
        '',		 // Position
        [
            \NormanSeibert\Ldap\Controller\ModuleController::class => 'check, summary, importUsers, doImportUsers, updateUsers, doUpdateUsers, importAndUpdateUsers, doImportAndUpdateUsers, deleteUsers, doDeleteUsers, checkLogin, doCheckLogin',
        ],
        [
            'access' => 'user,group',
            'icon' => 'EXT:ldap/Resources/Public/Icons/ldap.svg',
            'labels' => 'LLL:EXT:ldap/Resources/Private/Language/locallang.xml',
        ]
    );

    /**
     * Register icons.
     */
    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
    $iconRegistry->registerIcon(
        'extension-ldap-main',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:ldap/Resources/Public/Icons/ldap.svg']
    );
})();