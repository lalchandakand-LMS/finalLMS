# Universal AI Assistant (advanced optional plugin)

This is an unsigned source package. It is not executable merely because it exists in the repository.
It must be built into a ZIP, signed with an approved Ed25519 release key, installed through Plugin Manager,
reviewed and explicitly activated. Never commit a release private key or provider API key.

- Provider adapter in this first tranche: OpenAI Chat Completions.
- Role switches default to off.
- Global enable defaults to off.
- API keys are encrypted with Laravel Crypt and never rendered back to the browser.
- The assistant receives only the user's messages and limited conversation history.
- No private LMS retrieval tools or records are available; data access is fixed to General Guidance Only.
- Conversations and usage rows remain in the database after package uninstall. Do not run the plugin migration rollback during uninstall.
- The package source lives under the private application-root plugins directory, outside the public document root.

Before release, build and sign the package using the organization's protected release key and test on staging.