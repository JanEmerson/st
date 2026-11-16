# Final build correction report — 2026-11-10

## Completed
- Removed destructive DROP TABLE commands from fresh-install master SQL. The installer now fails rather than overwriting existing tables. **Not an upgrade migration.**
- Added Apache upload-directory `.htaccess` to prevent execution of common script extensions and disable listing, plus empty `index.html`.
- Corrected contradictory README installation instructions and documented new-vs-existing database procedures.
- Updated database deployment guide, including upload hardening and URL-path constraints.
- Preserved development MySQL root/empty-password settings as explicitly requested.

## Still requires verification / not claimed fixed
- The application uses hardcoded `/st/` links. Changing this broadly without live browser tests risks breaking navigation and redirects. `/st/` is documented as the supported path.
- Apache must honor `.htaccess`; Nginx requires equivalent server configuration.
- Live database migrations, workflows, QR scanning, upload behavior, role access and browser testing cannot be verified by syntax checks alone.
- No application module functionality has been altered.

## Additional verification pass
- Added `tests/static_release_check.py` and `tests/RELEASE_TEST_PLAN.md`.
- Static checks: 8/8 passed, including syntax validation of 61 PHP files.
- No live MySQL, Apache or browser acceptance testing has been performed.
