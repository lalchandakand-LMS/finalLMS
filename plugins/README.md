# First-party LMS plugins

Every business capability lives in its own directory under this folder. The protected platform Core and Identity foundation remain outside this directory.

## Directory contract

Each plugin directory contains a `plugin.json` manifest. As extraction proceeds, plugin-owned implementation is placed only in that directory:

- `src/` — plugin services, providers, contracts and policies
- `routes/` — plugin web/API routes
- `resources/` — views, translations and frontend assets
- `database/` — migrations, factories and seeders
- `config/` — plugin defaults
- `tests/` — isolated plugin tests

The manifest is metadata, not permission to execute arbitrary code. Only reviewed, first-party plugins may be registered. Uploaded PHP packages are never loaded or executed. Disabling a plugin must preserve its data.

The existing Laravel application is being migrated incrementally; legacy implementations are not moved automatically by adding a manifest. See `docs/module-plugin-architecture.md`.
