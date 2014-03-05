<?php

########################################################################
# Extension Manager/Repository config file for ext "ldap".
#
# Auto generated 15-10-2012 11:11
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'LDAP',
	'description' => 'LDAP Integration',
	'category' => 'module',
	'shy' => 0,
	'version' => '3.1.15',
	'dependencies' => 'extbase,fluid',
	'modify_tables' => 'fe_users,fe_groups,be_users,be_groups',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => 'mod1',
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearcacheonload' => 0,
	'lockType' => '',
	'author' => 'Norman Seibert',
	'author_email' => 'seibert@entios.de',
	'author_company' => '',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'constraints' => array(
		'depends' => array(
			'php' => '5.3.0-0.0.0',
			'typo3' => '6.0.0-6.2.99',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:30:{s:16:"ext_autoload.php";s:4:"5471";s:21:"ext_conf_template.txt";s:4:"249e";s:12:"ext_icon.gif";s:4:"f735";s:17:"ext_localconf.php";s:4:"acb3";s:15:"ext_php_api.dat";s:4:"7d5b";s:14:"ext_tables.php";s:4:"e3c1";s:14:"ext_tables.sql";s:4:"4576";s:25:"icon_tx_euldap_server.gif";s:4:"f735";s:16:"locallang_db.xml";s:4:"f6c1";s:7:"tca.php";s:4:"05a5";s:14:"doc/manual.pdf";s:4:"88ac";s:14:"doc/manual.sxw";s:4:"bd3b";s:16:"example/conf.txt";s:4:"a603";s:37:"hooks/class.tx_euldap_auth_logoff.php";s:4:"00a2";s:31:"hooks/class.tx_euldap_login.php";s:4:"4e41";s:32:"hooks/class.tx_euldap_search.php";s:4:"217a";s:30:"lib/class.tx_euldap_config.php";s:4:"d6bc";s:27:"lib/class.tx_euldap_div.php";s:4:"644f";s:28:"lib/class.tx_euldap_ldap.php";s:4:"76ed";s:29:"lib/class.tx_euldap_tools.php";s:4:"f9d6";s:13:"mod1/conf.php";s:4:"0e6e";s:14:"mod1/index.php";s:4:"f812";s:18:"mod1/locallang.xml";s:4:"d6e6";s:22:"mod1/locallang_mod.xml";s:4:"4a3f";s:19:"mod1/moduleicon.gif";s:4:"f735";s:14:"mod1/style.css";s:4:"572e";s:29:"scheduler/class.tx_euldap.php";s:4:"1be7";s:39:"scheduler/class.tx_euldap_addfields.php";s:4:"fa53";s:27:"scheduler/locallang_csh.xml";s:4:"d7e1";s:27:"sv1/class.tx_euldap_sv1.php";s:4:"4ca1";}',
	'suggests' => array(
	),
);

?>