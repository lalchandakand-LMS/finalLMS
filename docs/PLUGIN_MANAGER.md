# Admin Plugin Manager

The Admin Plugin Manager is the guarded web entry point for signed plugin
installation. It is available only to the root admin role under the authenticated
Admin workspace.

## Install flow

1. Admin uploads a ZIP archive (default maximum 50 MiB).
2. Admin supplies the exact manifest JSON, trusted release key ID and detached
   Base64 Ed25519 signature.
3. The server validates the manifest, checks the private staging directory,
   creates a registry record and advances it through discovered, validated and
   staged.
4. The signature verifier checks the configured trusted public key and binds the
   manifest to the SHA-256 checksum of the exact uploaded ZIP bytes.
5. The secure archive installer rechecks the checksum and safely extracts the
   package into plugins/vendor/plugin/version.
6. The registry is marked installed only after the atomic filesystem move.
7. The staged ZIP is deleted. The action is written to the admin activity log.

A failed, not-yet-installed registry entry can be retried after correcting the
package or signature. An existing installed version cannot be overwritten.
In-place updates are not enabled yet.

## Example manifest

    {
      "schema_version": 1,
      "key": "aec.example-plugin",
      "name": "Example Plugin",
      "version": "1.0.0",
      "type": "module",
      "entrypoint": "src/Plugin.php",
      "requires": {
        "php": "^8.3",
        "laravel": "^12.0",
        "lms": "^1.0"
      },
      "dependencies": [],
      "conflicts": [],
      "portals": ["admin", "teacher", "student"],
      "permissions": ["example.manage"],
      "capabilities": ["example"]
    }

The detached signature must be generated over the exact canonical payload from
PluginSignatureVerifier::payloadForSignature($manifest, $sha256). See
PLUGIN_SIGNATURE_VERIFICATION.md for the trusted public key configuration and
canonical signing rules.

## Not yet enabled

The Manager deliberately does not offer activate, deactivate, update or uninstall
controls yet. The isolated runtime must first enforce plugin boundaries for web
routes, API routes, queued jobs, schedules, events and portal navigation. A
plugin being installed does not mean its PHP entrypoint is loaded or executed.
Manifest permissions are stored for review and are not automatically granted.
