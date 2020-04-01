.. include:: ../Includes.txt


.. _configuration:

=============
Configuration
=============

Correct configuration of LDAP server records is crucial and most
problems result from wrong configurations. A general advice is to set
the logging level to “2” in the extension's configuration (in the
extension manager).

.. important::

   In Version 3.4.x the UIDs of LDAP server records in the configuration file changed.

   **UIDs have to be integer now!**


Typical Example
===============

An example configuration file is included in the directory “example”.

.. _configuration-typoscript:


.. only:: html

	**Sections:**

.. toctree::
	:maxdepth: 1
	:titlesonly:
	
	Reference
	Groups
	Extbase
	SSO
