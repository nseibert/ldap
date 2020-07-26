<?php
if (!defined("TYPO3_MODE")) {
	exit("Access denied.");
}

$config = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ExtensionConfiguration')->get('ldap');

if ($config['enableFE'] && !$config['enableBE']) {
	$subTypes = 'getUserFE,authUserFE';
	if ($config['enableSSO']) $TYPO3_CONF_VARS['SVCONF']['auth']['setup']['FE_fetchUserIfNoSession'] = 1;
}

if (!$config['enableFE'] && $config['enableBE']) {
	$subTypes = 'getUserBE,authUserBE';
	if ($config['enableSSO']) $TYPO3_CONF_VARS['SVCONF']['auth']['setup']['BE_fetchUserIfNoSession'] = 1;
}

if ($config['enableFE'] && $config['enableBE']) {
	$subTypes = 'getUserFE,authUserFE,getUserBE,authUserBE';
	if ($config['enableSSO']) $TYPO3_CONF_VARS['SVCONF']['auth']['setup']['FE_fetchUserIfNoSession'] = 1;
	if ($config['enableSSO']) $TYPO3_CONF_VARS['SVCONF']['auth']['setup']['BE_fetchUserIfNoSession'] = 1;
}

if ($config['enableFE'] || $config['enableBE']) {
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService(
		$_EXTKEY,
		'auth' /* sv type */,
		'NormanSeibert\\Ldap\\Service\\LdapAuthService' /* sv key */,
		array(
			'title' => 'LDAP-Authentication',
			'description' => 'Authentication service for LDAP (FE and BE).',
			'subtype' => $subTypes,
			'available' => 1,
			'priority' => 75,
			'quality' => 75,
			'os' => '',
			'exec' => '',
			'classFile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Classes/Service/LdapAuthService.php',
			'className' => 'NormanSeibert\\Ldap\\Service\\LdapAuthService',
		)
	);
}

if (TYPO3_MODE === 'BE') {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 'NormanSeibert\\Ldap\\Command\\LdapCommandController';
}

$GLOBALS['TYPO3_CONF_VARS']['LOG']['NormanSeibert']['Ldap']['writerConfiguration'] = array(
	\TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
		'TYPO3\\CMS\\Core\\Log\\Writer\\FileWriter' => [
			'logFileInfix' => 'ldap',
		],
	],
);