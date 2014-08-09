

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


FAQ
^^^

**Is there a limit on the number of user records which can be imported
from a directory?**

No, there isn't – at least not in the extension. Many LDAP servers are
configured to retrieve only 1000 records per search, so please check
your LDAP server if you get only 1000 entries.

**Can I import nested user groups from an LDAP directory?**

No, this is (currently) not supported.

