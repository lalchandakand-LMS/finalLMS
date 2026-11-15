# Private plugin packages

This directory is the canonical application-root location for first-party and
approved third-party plugin packages. It must remain outside the web document
root and must be denied by web-server rules as defense in depth.

The directory's presence never activates code. Plugin manifests are declarative
metadata; they must be validated and the package must be signature-verified,
registered, explicitly approved and activated before any plugin entrypoint can
be loaded.

Do not place secrets, user uploads, generated content, or unsigned packages in
this directory. Do not expose this directory through public storage or a
symbolic link. The runtime installer and loader are intentionally not enabled
by this foundation tranche.
