<?php
defined('TYPO3_MODE') or die();

// Register the backend module
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
	'NormanSeibert.' . $_EXTKEY,
	'tools',	 // Make module a submodule of 'web'
	'm1',	 // Submodule key
	'',		 // Position
	array(
		'Module' => 'check, summary, importUsers, doImportUsers, updateUsers,
			doUpdateUsers, importAndUpdateUsers, doImportAndUpdateUsers,
			deleteUsers, doDeleteUsers, checkLogin, doCheckLogin'
	),
	array(
		'access' => 'user,group',
		'icon'   => 'EXT:' . $_EXTKEY . '/Resources/Public/Icons/ldap.svg',
		'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang.xml',
	)
);

/**
 * Register icons
 */
$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
$iconRegistry->registerIcon(
    'extension-ldap-main',
    \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
    ['source' => 'EXT:ldap/Resources/Public/Icons/ldap.svg']
);
?>