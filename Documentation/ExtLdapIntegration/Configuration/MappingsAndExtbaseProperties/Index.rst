

.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. ==================================================
.. DEFINE SOME TEXTROLES
.. --------------------------------------------------
.. role::   underline
.. role::   typoscript(code)
.. role::   ts(typoscript)
   :class:  typoscript
.. role::   php(code)


Mappings and Extbase Properties
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Since this extension uses Extbase the mappings for users and
usergroups are based on Extbase properties and no longer on database
fields. This implies that every property you want to assign a value to
has to be known to Extbase.

You can find the Extbase standard properties in the file:

*/typo3/sysext/extbase/ext\_typoscript\_setup.txt*

The LDAP extension adds some properties defined in:

*<Extension directory>/Configuration/TypoScript/setup.txt*

