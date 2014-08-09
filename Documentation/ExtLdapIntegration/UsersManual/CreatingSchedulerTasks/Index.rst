

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


Creating Scheduler tasks
^^^^^^^^^^^^^^^^^^^^^^^^

Using an Extbase Command Controller as a Scheduler Task allows
scheduled execution of an action. To create a scheduled execution
simply add a new Scheduler task with class “Extbase CommandController
Task (extbase)”. Choose the CommandController Command, save the task
and reopen it to set parameters. These are the same as described in
the section above.

