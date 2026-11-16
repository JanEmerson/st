# ST Asset Management System — release verification

## Automated checks
Run from a machine with PHP 8+, Node.js, and Python 3 installed:

```sh
python tests/static_release_check.py
```

This checks PHP/JS syntax and basic package safeguards only. It does not validate business workflows.

## XAMPP manual acceptance tests
1. Back up both the current application directory and MySQL database before deployment.
2. Extract the package to `C:\xampp\htdocs\st\` (keep the `/st/` URL path).
3. For a **new empty** database, follow `database/DATABASE_DEPLOYMENT_GUIDE.md`. Do not run `st_db_install.sql` on an existing database.
4. Start Apache and MySQL, then open `http://localhost/st/login.php`.
5. Sign in and sign out; confirm sessions and role restrictions.
6. Create/edit/view an asset, add an image, and test QR generation and mobile scanning.
7. Test assignment, transfer, turnover, maintenance creation and attachment download, and history.
8. Test Excel import/export using disposable records; verify the import rejects malformed input safely.
9. Test user management and password changes, and verify non-admin accounts cannot access admin actions.
10. Verify that a request for a test `.php` file inside `/st/uploads/` is denied by Apache (do not upload or execute a real script).
11. Test browser printing and responsive layout; check PHP and Apache error logs.
12. Verify the database data is preserved after updating the application files.

## Remaining limitations
- All application URLs and session-cookie path are designed for `/st/`. Moving the app to another subdirectory requires a separate migration and browser regression testing.
- Upload protections depend on Apache honoring `.htaccess`. Configure equivalent rules for other servers.
- A live Apache/MySQL/browser acceptance run has not been performed by the automated checker.
- Development database credentials are deliberately left as requested.
