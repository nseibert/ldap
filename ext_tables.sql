CREATE TABLE fe_groups (
	tx_ldap_serveruid varchar(255) DEFAULT '',
	tx_ldap_dn varchar(255) DEFAULT '',
	tx_ldap_lastrun varchar(255) DEFAULT ''
);

CREATE TABLE be_groups (
	tx_ldap_serveruid varchar(255) DEFAULT '',
	tx_ldap_dn varchar(255) DEFAULT '',
	tx_ldap_lastrun varchar(255) DEFAULT ''
);

CREATE TABLE fe_users (
	tx_ldap_serveruid varchar(255) DEFAULT '',
	tx_ldap_dn varchar(255) DEFAULT '',
	tx_ldap_nosso tinyint(1) DEFAULT '0',
	tx_ldap_lastrun varchar(255) DEFAULT ''
);

CREATE TABLE be_users (
	tx_ldap_serveruid varchar(255) DEFAULT '',
	tx_ldap_dn varchar(255) DEFAULT '',
	tx_ldap_nosso tinyint(1) DEFAULT '0',
	tx_ldap_lastrun varchar(255) DEFAULT ''
);