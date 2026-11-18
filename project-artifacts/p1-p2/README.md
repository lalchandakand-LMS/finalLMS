# User-supplied P1/P2 working materials

This directory is the indexed home for the two source archives supplied for the LMS P1/P2 work on 2026-11-17.

## Inputs audited

| Input archive | Contents | Size | SHA-256 |
|---|---:|---:|---|
| `finalLMS-source-p1-p2 (1).zip` | 715 source-tree files under `finalLMS-src/` | 1,209,885 bytes | `136a5909e34361601829f546980c5ce1cbb6c4fb8e41e66d763c0cfa69be12a3` |
| `finalLMS-all-patches-and-tracker (4).zip` | 9 patch, script, README and tracker entries | 72,474 bytes | `0aa930a554d53d93718c4d0f7be6c7c5e02d25f09b2fa5d0f842e3be84a48b33` |

Both ZIP archives passed ZIP integrity checks. The full source snapshot is used as an audit/reference input; it is not copied over the live repository tree. The patch bundle is likewise reviewed before any application.

## Safety / delivery rules

- Work on a dedicated branch and preserve existing branches and commits.
- Run `git apply --check` before applying any patch.
- Missing PHP, Composer dependencies, PHPUnit, or required security tests is a hard stop.
- No commit/push until required checks pass.
- Never merge or deploy with failing or incomplete checks.
- No production deployment is authorized by this work.

## Work sequence

1. Audit supplied source snapshot against the current repository and inspect existing Website Builder, Website Pages, Media Library, and Theme Manager.
2. Validate P1 Theme Manager hardening against its intended PR base.
3. Port P2 only after confirming dependencies and avoiding duplicate builder functionality.
4. Run exact-head automated checks; keep release/staging acceptance separate from code-level checks.

See [AI_TASK_TRACKER.md](./AI_TASK_TRACKER.md) for the supplied task tracker.
