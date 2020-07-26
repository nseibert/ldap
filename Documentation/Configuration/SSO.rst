.. include:: ../Includes.txt



Single Sign On (SSO)
====================

The extension is prepared for SSO Support using an HTTP header to
provide the username. The header is configured in the extension's
settings accessible from TYPO3's Extension Manager. The Username has
to be stored in the HTTP header configured and it has to be identical
to the one the user would type in the normal login form.

**Good to know**

If the configured HTTP header is filled this value is used to login in
the user. No credentials transmitted by a Login form are evaluated.

You should make sure that nobody is able to fake the HTTP header :-)
