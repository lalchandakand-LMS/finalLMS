# Universal AI Assistant data schema

This plugin owns the optional AI settings, conversations and messages schema.
The signed package migration is run by the LMS Plugin Migration Runner only after
signature verification, private-path installation and installed-file integrity checks.

The Admin-configured retention period is applied when the assistant is opened or used; expired conversations and their messages are then removed. Deactivation and uninstall do not run migration down methods, so retained settings and history remain available for a later reinstall. An explicit rollback refuses to drop the AI schema while settings, conversations or messages remain; export/erase must be a separate, explicit administrator operation.
