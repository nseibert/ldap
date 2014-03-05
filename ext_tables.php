<?php
if (!defined("TYPO3_MODE")) {
	exit("Access denied.");
}

if (TYPO3_MODE == "BE")    {
	if (TYPO3_MODE === 'BE' && !(TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_INSTALL)) {
		Tx_Extbase_Utility_Extension::registerModule(
			'NormanSeibert.' . $_EXTKEY,
			'tools',	 // Make module a submodule of 'web'
			'm1',	 // Submodule key
			'',		 // Position
			array(
				'Module' => 'summary, importUsers, doImportUsers, updateUsers,
							doUpdateUsers, importAndUpdateUsers, doImportAndUpdateUsers,
							deleteUsers, doDeleteUsers, checkLogin, doCheckLogin'
			),
			array(
				'access' => 'user,group',
				'icon'   => 'EXT:' . $_EXTKEY . '/Resources/Public/Icons/mod_icon.gif',
				'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang.xml',
			)
		);
	}
}

$tempColumns = array(
	"tx_ldap_dn" => array(
		"exclude" => 1,        
		"label" => "LLL:EXT:ldap/Resources/Private/Language/locallang_db.xml:fe_users.tx_ldap_dn",
		"config" => Array (
			"type" => "none",
		)
	),
	"tx_ldap_serveruid" => array(
		"exclude" => 1,        
		"label" => "LLL:EXT:ldap/Resources/Private/Language/locallang_db.xml:fe_users.tx_ldap_server",
		"config" => Array (
			"type" => "none",
		)
	),
	"tx_ldap_lastrun" => array(
		"exclude" => 1,        
		"label" => "LLL:EXT:ldap/Resources/Private/Language/locallang_db.xml:fe_users.tx_ldap_lastrun",
		"config" => Array (
			"type" => "none",
		)
	),
);


if (version_compare(TYPO3_branch, '6.1', '<')) {
	\TYPO3\CMS\Core\Utility\GeneralUtility::loadTCA("fe_users");
}
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns("fe_users", $tempColumns, 1);

if (version_compare(TYPO3_branch, '6.1', '<')) {
	\TYPO3\CMS\Core\Utility\GeneralUtility::loadTCA("be_users");
}
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns("be_users", $tempColumns, 1);

if (version_compare(TYPO3_branch, '6.1', '<')) {
	\TYPO3\CMS\Core\Utility\GeneralUtility::loadTCA("fe_groups");
}
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns("fe_groups", $tempColumns, 1);

if (version_compare(TYPO3_branch, '6.1', '<')) {
	\TYPO3\CMS\Core\Utility\GeneralUtility::loadTCA("be_groups");
}
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns("be_groups", $tempColumns, 1);

t3lib_extMgm::addTCAcolumns('fe_users',$tmp_feu_ext_columns);

/*
t3lib_extMgm::addToAllTCAtypes('fe_users', '--div--;LDAP, tx_ldap_dn, tx_ldap_serveruid, tx_ldap_lastrun');
t3lib_extMgm::addToAllTCAtypes('fe_groups', '--div--;LDAP, tx_ldap_dn, tx_ldap_serveruid, tx_ldap_lastrun');
t3lib_extMgm::addToAllTCAtypes('bfe_users', '--div--;LDAP, tx_ldap_dn, tx_ldap_serveruid, tx_ldap_lastrun');
t3lib_extMgm::addToAllTCAtypes('be_groups', '--div--;LDAP, tx_ldap_dn, tx_ldap_serveruid, tx_ldap_lastrun');
*/
?>