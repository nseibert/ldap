

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


Reference
^^^^^^^^^

The following table lists the properties of an LDAP server record. If
you manage your server records in a configuration file you will
recognize the property names immediately, in the backend the
properties may have different (and localized) labels.

The configuration file uses a Typoscript like syntax, the root element
to be used is “ldapServers”.

Mandatory properties are printed bold.

.. ### BEGIN~OF~TABLE ###

.. container:: table-row

   Property
         Property
   
   Data type
         Data type
   
   Description
         Description
   
   Default
         Default


.. container:: table-row

   Property
         **title**
   
   Data type
         string
   
   Description
         Server name
   
   Default


.. container:: table-row

   Property
         **disable**
   
   Data type
         boolean
   
   Description
         Disable the server record
   
   Default
         0


.. container:: table-row

   Property
         **host**
   
   Data type
         string
   
   Description
         The server's ip address or DNS name
   
   Default


.. container:: table-row

   Property
         **port**
   
   Data type
         int+
   
   Description
         The server's port, mostly 389
   
   Default


.. container:: table-row

   Property
         forceTLS
   
   Data type
         boolean
   
   Description
         Encrypt the connection even if using port 389 which is used for
         unencrypted connections by default
   
   Default
         0


.. container:: table-row

   Property
         version
   
   Data type
         int+
   
   Description
         The server's LDAP version, currently “3” should work for most servers
   
   Default


.. container:: table-row

   Property
         authenticate
   
   Data type
         string
   
   Description
         FE: Authenticate FE users
         
         BE: Authenticate BE users
         
         both: Authenticate FE and BE users
   
   Default


.. container:: table-row

   Property
         **user**
   
   Data type
         string
   
   Description
         User (DN) with read access to the directory
   
   Default


.. container:: table-row

   Property
         **password**
   
   Data type
         string
   
   Description
         The user's password
   
   Default


.. container:: table-row

   Property
         **fe\_users.**
   
   Data type
         COA
   
   Description
         You have to set either “fe\_users” or “be\_users”, otherwise nothing
         will happen ...
   
   Default


.. container:: table-row

   Property
         **fe\_users.pid**
   
   Data type
         int
   
   Description
         Page ID for user storage
   
   Default


.. container:: table-row

   Property
         **fe\_users.baseDN**
   
   Data type
         string
   
   Description
         The BaseDN for all LDAP searches
   
   Default


.. container:: table-row

   Property
         **fe\_users.filter**
   
   Data type
         string
   
   Description
         The LDAP query for user retrieval, “<search>” will be replaced by the
         user's username.
   
   Default


.. container:: table-row

   Property
         fe\_users.autoImport
   
   Data type
         boolean
   
   Description
         If set users will be imported/updated automatically after successful
         LDAP authentication.
   
   Default
         0


.. container:: table-row

   Property
         fe\_users.autoEnable
   
   Data type
         boolean
   
   Description
         If set users will be enabled automatically after login. Otherwise
         users disabled in TYPO3 will remain disabled and will not be able to
         login.
   
   Default
         0


.. container:: table-row

   Property
         **fe\_users.mapping.**
   
   Data type
         COA
   
   Description
         Configures the TYPO3 user table fields, the basic syntax is:
         
         ::
         
            <Extbase Property>.data = field:<LDAP attribute>
         
         **The LDAP attributes have to be written in lowercase!**
         
         Static values like “1” are assigned similarly:
         
         ::
         
            <Extbase Property>.value = <Static value>
         
         **Example**
         
         The following example updates the table field “name” with the value
         “displayname” of the user's LDAP record and wraps it with stars:
         
         ::
         
            name {
                data = field:displayname
                wrap = * | *
            }
   
   Default


.. container:: table-row

   Property
         **fe\_users.usergroups.**
   
   Data type
         COA
   
   Description
         Without a usergroup FE users are unable to login to TYPO3.
   
   Default


.. container:: table-row

   Property
         fe\_users.usergroups.importGroups
   
   Data type
         boolean
   
   Description
         Import usergroups from the LDAP directory.
   
   Default
         0


.. container:: table-row

   Property
         fe\_users.usergroups.restrictToGroups
   
   Data type
         List of strings
   
   Description
         Only import groups if the name satisfies the given pattern(s).
         
         Regular expression.
         
         **Example**
         
         The following example imports only users which belong to a group
         beginning with “typo3” (case insensitive):
         
         ::
         
            restrictToGroups = /^typo3.*/i
   
   Default


.. container:: table-row

   Property
         fe\_users.usergroups.addToGroups
   
   Data type
         List of int+
   
   Description
         Add each imported/updated user to this TYPO3 user group(s).
         
         Comma-separated list of usergroup UIDs.
   
   Default


.. container:: table-row

   Property
         fe\_users.usergroups.reverseMapping
   
   Data type
         boolean
   
   Description
         If your LDAP directory stores users as group attributes (OpenLDAP) set
         this value to 1.
   
   Default
         0


.. container:: table-row

   Property
         fe\_users.usergroups.preserveNonLdapGroups
   
   Data type
         Boolean
   
   Description
         Preserve relations to usergroups which have not been imported from an
         LDAP server
   
   Default


.. container:: table-row

   Property
         be\_users.
   
   Data type
         COA
   
   Description
         Same as “fe\_users” but property “pid” does not exist because BE users
         are stored on the root page (zero)
   
   Default


.. ###### END~OF~TABLE ######

