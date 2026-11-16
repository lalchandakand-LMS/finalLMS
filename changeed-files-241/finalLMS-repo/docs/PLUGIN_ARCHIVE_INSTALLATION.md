# Secure plugin archive installation

Only a plugin registry record with status staged, a recorded signature
verification timestamp, a valid SHA-256 package checksum, a currently trusted
release key ID, a valid manifest and the canonical destination path can be
installed.

The installer verifies the exact archive checksum again before opening the ZIP. The defaults are 50 MiB compressed, 1,000 entries, 100 MiB total expanded size, 20 MiB per file and a maximum 100:1 compression ratio. These limits can be tuned with PLUGIN_MAX_PACKAGE_BYTES, PLUGIN_MAX_ARCHIVE_ENTRIES, PLUGIN_MAX_UNCOMPRESSED_BYTES, PLUGIN_MAX_FILE_BYTES and PLUGIN_MAX_COMPRESSION_RATIO in the private environment file.
It rejects:

- absolute paths, drive paths, backslashes, null bytes and dot-dot segments;
- duplicate paths and case-colliding entries;
- symlinks and special filesystem entries;
- encrypted entries, invalid sizes and excessive compression ratios;
- too many entries, oversized files and excessive total uncompressed size;
- path/file-directory collisions, environment files and missing entrypoints;
- destinations outside plugins/vendor/plugin/version or existing versions.

Entries are streamed into a randomly named private extraction directory under
plugins/.staging. The installer never calls ZipArchive::extractTo. It creates
regular files only, applies restrictive permissions, checks the signed
entrypoint exists, then atomically renames the complete directory into the
canonical private plugins path.

The lifecycle is advanced to installed only after the filesystem move succeeds.
If the lifecycle transition fails, the newly moved directory is removed unless
the registry has already advanced successfully.

Installation does not load PHP files, register plugin routes, jobs, schedules or
menus, and does not activate the plugin. Runtime registration and the Admin
Plugin Manager remain separate, later security-reviewed stages.
