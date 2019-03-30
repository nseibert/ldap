

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


Installation
^^^^^^^^^^^^

#. Install the extension through the extension manager.

#. Set the extension's basic settings in the extension manager. **For the
   frontend login it is necessary to specify a root page id due to some
   Extbase bugs in TYPO3 6.0.**

#. Configure the authentication mode, whether you want to enable FE or BE
   users to login using their LDAP credentials. Please note that enabling
   BE authentication and setting exclusive authentication against LDAP
   may prevent you from logging into the TYPO3 backend! Test first!!!

#. The extension can log errors or single execution steps to the TYPO3
   log. **If you set the logging level to “3” all activities – even user
   credentials – are logged for debugging purposes** .

#. Create LDAP server records in your configuration file.

#. Use the LDAP backend module to check your configuration.

