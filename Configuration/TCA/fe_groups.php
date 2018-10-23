<?php
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


\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns("fe_groups", $tempColumns);
?>