# Universal AI Assistant data schema

The signed package migration directory is processed by the LMS Plugin Migration Runner after
the package has been installed and its file integrity verified. This first AI add-on version
uses the LMS-owned additive schema migration at
database/migrations/2026_11_15_180000_create_universal_ai_assistant_tables.php because these
tables are part of the current LMS storage release. Conversation and settings records are
retained when the plugin is deactivated or uninstalled. Uninstall never runs destructive down
migrations. Package migration records are tracked separately per plugin key and version.
