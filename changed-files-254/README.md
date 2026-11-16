# finalLMS Issue #254 — Complete Combined Bundle

Base commit: 348d9a7 (main)
Plugin key: aec.popup-ads v1.0.0

## Contents of this single ZIP
1. `finalLMS-repo/` — Full finalLMS source tree with all Issue #254 changes already applied (and 5 legacy core popup files removed).
2. `changed-files/` — Only the 20 added/modified files from Issue #254.
3. `DELETED_FILES.txt` — The 5 removed core files.
4. `issue-254.patch` — Unified git patch (`git apply issue-254.patch`).
5. `popup-ads-plugin-1.0.0.zip` — Standalone plugin ZIP ready for signing or Plugin Manager upload.

## Quick Apply (in an existing git clone)
```bash
git checkout main && git pull origin main
git checkout -b feat/popup-ads-plugin
git apply issue-254.patch
find app bootstrap config database plugins public routes tests .github/scripts -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php artisan route:clear && php artisan view:cache
vendor/bin/phpunit tests/Feature/VisitorPopupManagementTest.php tests/Feature/AdminModuleRegistryTest.php
git add -A
git commit -m "feat(plugin): move Popup Ads & Announcements into standalone aec.popup-ads plugin"
git push -u origin feat/popup-ads-plugin
```
