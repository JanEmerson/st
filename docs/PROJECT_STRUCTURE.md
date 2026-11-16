# ST Asset Management System — Project Structure

This release keeps the public entry points small and groups supporting files by purpose.

```text
st/
├── index.php                         # Authenticated dashboard entry point
├── login.php                         # Login page / authentication entry point
├── asset_scan.php                    # Public QR asset verification entry point
│
├── backend/
│   ├── auth/                         # Authentication/session actions
│   ├── config/                       # Database connection/configuration
│   └── includes/                     # Shared PHP services/layout/security helpers
│
├── frontend/
│   ├── assets/
│   │   └── icons/                    # Favicon and browser/app icons
│   ├── css/                          # Application styles
│   ├── js/                           # Application JavaScript
│   └── pages/                        # Application pages and page-specific actions
│
├── database/                         # SQL installation and migration scripts
├── templates/                        # User-editable Excel templates
├── docs/
│   ├── manual/                       # User manual and presentation workflow guides
│   ├── setup/                        # Feature/setup instructions
│   ├── notes/                        # Release/polish notes
│   └── reports/                      # Validation/lint reports
│
└── uploads/                          # Runtime user-uploaded files (do not delete)
    ├── app/                          # Application-level uploads
    ├── assets/                       # Asset images
    └── avatars/                      # User profile pictures
```

## Debugging order

1. Check `backend/config/database.php` for database connection errors.
2. Check `backend/includes/auth_check.php` and `backend/includes/security.php` for login/session/CSRF issues.
3. Check the affected page in `frontend/pages/`.
4. Check shared layout files in `backend/includes/` before changing page-specific markup.
5. Check `frontend/css/style.css` for visual/layout issues and `frontend/js/app.js` for shared browser behavior.
6. Check the relevant SQL file in `database/` before changing the live database schema.
7. Never delete `uploads/` when deploying an updated code package if existing asset images, avatars, or login backgrounds must be preserved.

## Important deployment note

This is a code-organization release. Existing database data and the contents of `uploads/` are preserved by the application; replacing the project folder should be done carefully so runtime uploads are not accidentally removed.


## User Accountability Reports (1.4.7)

- `frontend/pages/user_assets.php` — current assets assigned to one employee.
- `frontend/pages/user_assets_export.php`
- `frontend/pages/assignment_export.php` — filtered current-assignment XLSX export.
 — employee-specific Excel download.
- `backend/includes/user_asset_report.php` — shared query/report helper.

These pages are Administrator-only and use `assets.current_user_id` as the
source of truth for current accountability. Historical transactions remain in
`asset_history`.
