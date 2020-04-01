.. include:: ../Includes.txt


Reference
=========

The following table lists the properties of an LDAP server record. If
you manage your server records in a configuration file you will
recognize the property names immediately, in the backend the
properties may have different (and localized) labels.

The configuration file uses a Typoscript like syntax, the root element
to be used is “ldapServers”.

**Each server needs to have an integer as a unique id (UID) to maintain
compatibility with database records.**

.. code-block:: php

   ldapServers {
      1 {
         title = My test server
      }
   }

Mandatory properties are printed bold.

+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| Parameter                        | Data type     | Description                                                                                        | Default        |
+==================================+===============+====================================================================================================+================+
| **title**                        | string        | Server name                                                                                        |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| **disable**                      | boolean       | Disable the server record                                                                          | 0              |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| **host**                         | string        | The server's ip address or DNS name                                                                |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| **port**                         | int+          | The server's port, mostly 389 for LDAP and 636 for LDAPS                                           |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| forcsTLS                         | boolean       | Encrypt the connection even if using port 389 which is used for unencrypted connections by default | 0              |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| version                          | int+          | The server's LDAP version, currently “3” should work for most servers                              |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| authenticate                     | string        | FE: Authenticate FE users                                                                          |                |
|                                  |               | BE: Authenticate BE users                                                                          |                |
|                                  |               | both: Authenticate FE and BE users                                                                 |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| **user**                         | string        | User (DN) with read access to the directory                                                        |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| **password**                     | string        | The aformentioned user's password                                                                  |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| **fe\_users.**                   | array / COA   | You have to set either “fe\_users” or “be\_users”, otherwise nothing will happen ...               |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| --> **.pid**                     | int           | Page ID for user storage                                                                           |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| --> **.baseDN**                  | string        | The BaseDN for all LDAP searches                                                                   |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| --> **.filter**                  | string        | The LDAP query for user retrieval, “<search>” will be replaced by the user's username              |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| --> .autoImport                  | boolean       | If set users will be imported/updated automatically after successful DAP authentication            | 0              |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| --> .autoEnable                  | boolean       | If set users will be enabled automatically after login if they have been disabled in TYPO3         | 0              |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| --> **.mapping.**                | array / COA   | Configures the TYPO3 user table fields, the basic syntax is::                                      |                |
|                                  |               |                                                                                                    |                |
|                                  |               |    <Extbase Property>.data = field:<LDAP attribute>                                                |                |
|                                  |               |    **The LDAP attributes have to be written in lowercase!**                                        |                |
|                                  |               |                                                                                                    |                |
|                                  |               | Static values like “1” are assigned similarly::                                                    |                |
|                                  |               |                                                                                                    |                |
|                                  |               |    <Extbase Property>.value = <Static value>                                                       |                |
|                                  |               |                                                                                                    |                |
|                                  |               | **Example**                                                                                        |                |
|                                  |               |                                                                                                    |                |
|                                  |               | The following example updates the table field “name” with the value                                |                |
|                                  |               | “displayname” of the user's LDAP record and wraps it with stars::                                  |                |
|                                  |               |                                                                                                    |                |
|                                  |               |    mapping {                                                                                       |                |
|                                  |               |       name {                                                                                       |                |
|                                  |               |          data = field:displayname                                                                  |                |
|                                  |               |             wrap = * | *                                                                           |                |
|                                  |               |          }                                                                                         |                |
|                                  |               |       }                                                                                            |                |
|                                  |               |    }                                                                                               |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| --> **.usergroups.**             | array / COA   | Without a usergroup FE users are unable to login to TYPO3                                          |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| --> . --> .importGroups          | boolean       | Import usergroups from the LDAP directory                                                          | 0              |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| --> . --> .restrictToGroups      | string list   | Only import groups if the name satisfies the given pattern(s)                                      |                |
|                                  |               | Regular expression.                                                                                |                |
|                                  |               |                                                                                                    |                |
|                                  |               | **Example**                                                                                        |                |
|                                  |               |                                                                                                    |                |
|                                  |               | The following example imports only users which belong to a group                                   |                |
|                                  |               | beginning with “typo3” (case insensitive)::                                                        |                |
|                                  |               |                                                                                                    |                |
|                                  |               |    restrictToGroups = /^typo3.*/i                                                                  |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| --> . --> .addToGroups           | int+ list     | Add each user to this TYPO3 user group(s)                                                          |                |
|                                  |               | Comma-separated list of usergroup UIDs                                                             |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| --> . --> .reverseMapping        | boolean       | If your LDAP directory stores users as group attributes (OpenLDAP) set this value to 1             | 0              |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| --> . --> .preserveNonLdapGroups | boolean       | Preserve relations to usergroups which have not been imported from an LDAP server                  |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
| be\_users.                       | array / COA   | Same as “fe\_users”                                                                                |                |
|                                  |               | Property “pid” does not exist because BE users are stored on the root page (zero)                  |                |
+----------------------------------+---------------+----------------------------------------------------------------------------------------------------+----------------+
