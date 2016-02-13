# What does it do?

This extension allows TYPO3 to connect to LDAP directories and to fetch user records from them. Features include:
* Handling of multiple LDAP servers
* Storage of LDAP server configurations in the TYPO3 database or a configuration file
* Import/Update/Delete of frontend (FE) and backend (BE) users
* Import of user groups
* Flexible mapping of LDAP attributes to TYPO3 user properties
* Authentication of FE and BE users against the directory
* Usage of the TYPO3 scheduler to import/update/delete TYPO3 users

# Compatibility

Version 3.x is not compatible with LDAP server records created with eu_ldap 2.x, instead you have to redefine your server records in the configuration file.

_Version 3.1 supports TYPO3 6.x._
_Version 3.2 supports TYPO3 7.6 and higher._

**TYPO3 7.6 currently has a bug which prevents the extension to import LDAP attributes with multiple values. This scenario often arises in MS Active Directory environments when using the "memberOf" attribute for group membership.**

See https://forge.typo3.org/issues/73155 for more information.
