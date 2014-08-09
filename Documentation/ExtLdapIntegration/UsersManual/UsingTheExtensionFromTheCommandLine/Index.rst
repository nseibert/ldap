

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


Using the extension from the command line
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

The extension provides a so called “Command Controller” which can be
invoked via the command line:

./typo3/cli\_dispatch.phpsh extbase ldap:<function> <parameters>

The following functions are supported:

.. ### BEGIN~OF~TABLE ###

.. container:: table-row

   Function
         Function
   
   Description
         Description
   
   Parameters
         Parameters


.. container:: table-row

   Function
         importUsers
   
   Description
         Imports new users
   
   Parameters
         **servers [string]**
         
         comma separated list (no spaces) of server uids from the configuration
         file
         
         **processFe [boolean, 0/1]**
         
         Import frontend users
         
         **ProcessBe [boolean, 0/1]**
         
         Import backend users


.. container:: table-row

   Function
         updateUsers
   
   Description
         Updates existing users
   
   Parameters
         **servers [string]**
         
         comma separated list (no spaces) of server uids from the configuration
         file
         
         **processFe [boolean, 0/1]**
         
         Update frontend users
         
         **processBe [boolean, 0/1]**
         
         Update backend users


.. container:: table-row

   Function
         importAndUpdateUsers
   
   Description
         Imports new users and updates existing ones
   
   Parameters
         **servers [string]**
         
         comma separated list (no spaces) of server uids from the configuration
         file
         
         **processFe [boolean, 0/1]**
         
         Import/update frontend users
         
         **processBe [boolean, 0/1]**
         
         Import/update backend users


.. container:: table-row

   Function
         deleteUsers
   
   Description
         Deletes or disables users not found in any LDAP directory
   
   Parameters
         **processFe [boolean, 0/1]**
         
         Delete frontend users
         
         **processBe [boolean, 0/1]**
         
         Delete backend users
         
         **hideNotDelete [boolean, 0/1]**
         
         Disable users instead of deleting them
         
         **deleteNonLdapUsers [boolean, 0/1]**
         
         Delete/deactivate also users which have not been imported from a
         directory


.. ###### END~OF~TABLE ######

