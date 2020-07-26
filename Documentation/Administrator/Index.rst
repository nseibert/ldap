.. include:: ../Includes.txt


.. _administrator:

=============
Administrator
=============


Installation
============

.. rst-class:: bignums

1. Install the extension through the extension manager.

2. Set the extension's basic settings in the extension manager. **For the
   frontend login it is necessary to specify a root page id due to some
   Extbase bugs in TYPO3 6.0.**

3. Configure the authentication mode, whether you want to enable FE or BE
   users to login using their LDAP credentials. Please note that enabling
   BE authentication and setting exclusive authentication against LDAP
   may prevent you from logging into the TYPO3 backend! Test first!!!

4. The extension can log errors or single execution steps to the TYPO3
   log. **If you set the logging level to “3” all activities – even user
   credentials – are logged for debugging purposes** .

5. Create LDAP server records in your configuration file.

6. Use the LDAP backend module to check your configuration.


Backend Module
==============

The backend module provides functions to:

* Get an overview of your LDAP server records

* Import users

* Update users

* Delete users who are not in the directory

* Check login against LDAP


Command Line (cli)
==================

The extension provides Symfony Console Commands (cli) which can be
invoked via the command line::

   typo3/sysext/core/bin/typo3 ldap:<function> <parameters>

The following functions are supported:

+-------------------------------+-------------------------------+---------------------------------------------------------------------------------+
| Function                      | Description                   | Parameters                                                                      |
+===============================+===============================+=================================================================================+
| importUsers                   | Imports new users             | **servers [string]**                                                            |
|                               |                               | comma separated list (no spaces) of server uids from the configuration file     |
|                               |                               |                                                                                 |
|                               |                               | **processFe [boolean, 0/1]**                                                    |
|                               |                               | Import frontend users                                                           |
|                               |                               |                                                                                 |
|                               |                               | **ProcessBe [boolean, 0/1]**                                                    |
|                               |                               | Import backend users                                                            |
+-------------------------------+-------------------------------+---------------------------------------------------------------------------------+
| updateUsers                   | Updates existing users        | **servers [string]**                                                            |
|                               |                               | comma separated list (no spaces) of server uids from the configuration file     |
|                               |                               |                                                                                 |
|                               |                               | **processFe [boolean, 0/1]**                                                    |
|                               |                               | Update frontend users                                                           |
|                               |                               |                                                                                 |
|                               |                               | **ProcessBe [boolean, 0/1]**                                                    |
|                               |                               | Update backend users                                                            |
+-------------------------------+-------------------------------+---------------------------------------------------------------------------------+
| importOrUpdateUsers           | Imports new users and         | **servers [string]**                                                            |
|                               | updates existing ones         | comma separated list (no spaces) of server uids from the configuration file     |
|                               |                               |                                                                                 |
|                               |                               | **processFe [boolean, 0/1]**                                                    |
|                               |                               | Import/pdate frontend users                                                     |
|                               |                               |                                                                                 |
|                               |                               | **ProcessBe [boolean, 0/1]**                                                    |
|                               |                               | Import/pdate backend users                                                      |
+-------------------------------+-------------------------------+---------------------------------------------------------------------------------+
| deleteUsers                   | Deletes or disables users     | **processFe [boolean, 0/1]**                                                    |
|                               | not found in any              | Delete frontend users                                                           |
|                               | LDAP directory                |                                                                                 |
|                               |                               | **processBe [boolean, 0/1]**                                                    |
|                               |                               | Delete backend users                                                            |
|                               |                               |                                                                                 |
|                               |                               | **hideNotDelete [boolean, 0/1]**                                                |
|                               |                               | Disable users instead of deleting them                                          |
|                               |                               |                                                                                 |
|                               |                               | **deleteNonLdapUsers [boolean, 0/1]**                                           |
|                               |                               | Delete/deactivate also users which have not been imported from a directory      |
+-------------------------------+-------------------------------+---------------------------------------------------------------------------------+


Scheduled Tasks
===============

Using the Symfony Command Console (cli) as a Scheduler Task allows
scheduled execution of an action. To create a scheduled execution
simply add a new Scheduler task with class “Execute console commands”.
Select the appropriate task under "Schedulable Command", save the task
and reopen it to set parameters. These are the same as described in
the section above.


FAQs
====

**Is there a limit on the number of user records which can be imported
from a directory?**

No, there isn't – at least not in the extension. Many LDAP servers are
configured to retrieve only 1000 records per search, so please check
your LDAP server if you get only 1000 entries.

**Can I import nested user groups from an LDAP directory?**

No, this is (currently) not supported.