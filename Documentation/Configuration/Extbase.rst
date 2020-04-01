.. include:: ../Includes.txt

Extbase Field Mappings
======================

Since this extension uses Extbase the mappings for users and
usergroups are based on Extbase properties and no longer on database
fields. This implies that every property you want to assign a value to
has to be known to Extbase.

You can find the Extbase standard properties in the file:

*/typo3/sysext/extbase/Configuration/Extbase/Persistence/Classes.php*

The LDAP extension adds some properties defined in:

*<Extension directory>/Configuration/Extbase/Persistence/Classes.php*
