# Installed plugin integrity records

During safe ZIP extraction, the installer calculates SHA-256 for every regular
file actually written. The sorted relative-path-to-hash map is stored in the
plugin registry as metadata.integrity_version=1 and
metadata.installed_file_hashes. It is recorded in the same lifecycle transition
that marks the package installed.

PluginIntegrityVerifier checks the canonical private plugin directory before
runtime code is loaded. It rejects modified, missing, extra, linked, special or
out-of-root files, malformed registry paths and invalid hash records.

This makes the extracted package independently verifiable after the uploaded ZIP
has been removed. It also prevents an unlisted PHP file from being silently
introduced and later included by a plugin entrypoint.

The verifier is deliberately not yet wired to plugin activation or application
boot. Runtime registration remains disabled until the provider contract, route
and API permission boundaries, background-job isolation, dependency checks,
failure handling and rollback are implemented and tested.
