# Installed plugin integrity records

During safe ZIP extraction, the installer calculates SHA-256 for every regular file actually written. The sorted relative-path-to-hash map is stored in the plugin registry as `metadata.integrity_version=1` and `metadata.installed_file_hashes`. It is recorded with the lifecycle transition that marks the package installed.

`PluginIntegrityVerifier` is used during Admin activation preflight, health checks and runtime loading. It checks the canonical private plugin directory and rejects modified, missing, extra, linked, special or out-of-root files, malformed registry paths and invalid hash records.

This prevents an unlisted PHP file from being silently introduced and later included by a plugin entrypoint. The original uploaded ZIP is not needed for installed-file verification; release-signature verification separately uses the recorded package checksum and detached signature metadata.

Integrity verification does not make signed PHP sandboxed. Active plugin code runs with the application's PHP process privileges and must come from a reviewed, trusted release authority. Do not install untrusted vendor packages.

The current Admin workflow includes signed install, activation preflight, health checks, deactivation and data-preserving uninstall. Signed in-place updates, multi-version rollback and a complete backup/restore workflow remain separate release gates.
