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
	'version' => '3.2.0',
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
	'constraints' => array(
		'depends' => array(
			'php' => '5.3.0-0.0.0',
			'typo3' => '7.0.0-7.6.99',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => '',
);