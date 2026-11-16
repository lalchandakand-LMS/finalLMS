# Private plugin packages

This directory is the canonical application-root location for first-party and
approved third-party plugin packages. It must remain outside the web document
root and must be denied by web-server rules as defense in depth.

A package directory never activates code by itself. Plugin manifests are
declarative metadata; packages must pass manifest validation, detached-signature
verification, integrity verification, dependency checks and explicit Admin
activation before their entrypoints can be loaded.

The `.staging/` subdirectory is reserved for private, size-limited package
archives awaiting manifest and detached-signature verification. It is not a
public upload directory and must never be web-accessible.

Do not place secrets, user uploads, generated content, or unsigned packages in
this directory. Do not expose this directory through public storage or a
symbolic link. First-party source packages in this repository are maintained
as reviewed application code; distributable plugin archives still require an
approved release signature before installation.
