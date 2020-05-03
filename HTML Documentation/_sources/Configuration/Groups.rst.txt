.. include:: ../Includes.txt



Creation of TYPO3 Groups
========================

The extension supports multiple ways of creating TYPO3 groups to cover different kinds of directory servers like Microsoft Active Directory or OpenLDAP.


Based on text attribute
~~~~~~~~~~~~~~~~~~~~~~~

The user's group or groups will be created from an LDAP attribute of the user record.

.. code-block:: php

   usergroups {
      mapping {
         # TEXT means that an attribute ("st" in this case) will be used
         field = TEXT
         title.data = field:st
      }
   }


Based on the parent node
~~~~~~~~~~~~~~~~~~~~~~~~

The user's group will be created from the user record's parent node.

.. code-block:: php

   usergroups {
      mapping {
         # PARENT means that the parent record will be used for "title.data"
         
         field = PARENT
         title.data = field:ou
      }
   }


mamberOf / Active Directory
~~~~~~~~~~~~~~~~~~~~~~~~~~~

The user's group or groups will be created from an attribute of the user record which holds the distinguised names of groups.

.. code-block:: php

   usergroups {
      mapping {
         # DN means that the used field ("memberof" in this case) contains a DN
         # This DN will be used for "title.data"
         # This configuration is valid e.g. for an Active Directory
         
         field = DN
         field.data = field:memberof
         title.data = field:name
      }
   }


groupOfNames / OpenLDAP
~~~~~~~~~~~~~~~~~~~~~~~

When user groups hold the users like it's the case in OpenLDAP.

.. code-block:: php

   # reverseMapping is needed when the usergroups are stored in a separate OU and the groups hold the usernames in an attribute
   # This is the case for e.g. OpenLDAP
   
   usergroups {

      reverseMapping = 1

      # Base-DN of the OU containing the usergroup records
         
      baseDN = 
         
      # <search> is replaced by the user record's DN or - if given - the "searchAttribute"
         
      filter = (memberUid=<search>)
      searchAttribute = uid
   
      mapping {     
         title.data = field:commonname
      }
   }
