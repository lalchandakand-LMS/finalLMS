# Universal AI Assistant data schema

This plugin owns the optional AI settings, conversations and messages schema.
The signed package migration is run by the LMS Plugin Migration Runner only after
signature verification, private-path installation and installed-file integrity checks.

Deactivation and uninstall do not run migration down methods. Conversation history,
settings and user data are retained. An explicit rollback refuses to drop these tables
while any settings, conversations or messages remain; export/erase must be an explicit
separate administrator operation.
