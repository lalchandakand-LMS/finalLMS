# Universal AI Assistant data schema

The initial release uses the LMS-owned additive migration at
database/migrations/2026_11_15_180000_create_universal_ai_assistant_tables.php.
The tables are intentionally retained when this plugin is deactivated or uninstalled.
No migration is automatically executed by plugin activation. A future plugin migration
runner must add explicit install/update/rollback bookkeeping before this package can
ship migrations independently.
