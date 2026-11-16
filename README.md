# ST Asset Management System — Polished Development Build

## Start here
- **Existing installation:** Back up the database and `uploads/` before updating application files. Do not run the master installer on an existing database.
- **New installation:** Follow the **Local Setup** section below and `database/DATABASE_DEPLOYMENT_GUIDE.md`.
- **Optional batch accountability:** Apply `database/st_db_batch_accountability_update.sql` once (if not already applied), then open **Asset Assignments → Create / Reprint Batch**. Batch forms are only for first-time assignments of newly purchased assets from one PO; select at least two. Individual printing and subsequent lifecycle actions remain unchanged.
- **Testing:** See `tests/RELEASE_TEST_PLAN.md`. PHP syntax and static checks are not a substitute for live XAMPP/MySQL testing.

## Navigation
- `frontend/pages/assignments.php` — assigned assets and optional batch entry point
- `frontend/pages/assignment_batch_select.php` — select eligible assets and reprint existing batches
- `frontend/pages/assignment_batch_create.php` — validate and save the immutable initial batch
- `frontend/pages/assignment_batch_print.php` — print original batch snapshot

---


## Release 1.6.0 — Non-Technical User Experience Pass

This release focuses on making the system easier for non-technical office users.

- Dashboard includes task-oriented Quick Actions for common workflows.
- A Help shortcut is always available in the top bar.
- Assignment, Transfer, Turnover, and Maintenance forms show simple step-by-step guidance.
- Asset registration fields include plain-language explanations.
- No database migration is required for this UX pass.

## Previous Release 1.5.1 — User-Friendly UX

- Shared collapsible Search & Filters panels across list pages.
- Filter panels are hidden by default and reopen automatically when active filters exist.
- Filter open/closed state is remembered per page for the browser session.
- Asset Assignments includes filtered Excel export with a summary sheet.
- Responsive filter controls and horizontal table handling for smaller screens.
- Accessible keyboard focus states and reduced-motion support.
- Existing database and transaction history behavior is preserved.

## Reusable Base Features
- Industrial graphite, steel, and safety-yellow theme presets, with per-user appearance preferences.
- Asset history search and date/action filters, with configurable Excel columns and optional linked-item and assignment-detail sheets.
- Responsive navigation, filters, tables, and export controls.

## Local Setup (new installation only)
1. Place the project in `C:\xampp\htdocs\st` (the default supported URL path).
2. Back up any existing database and uploads before making changes.
3. For a **new empty** `st_db`, import `database/st_db_install.sql` once. This script no longer drops tables; it stops if application tables already exist.
4. Existing installations: **do not import the master installer**. Review the relevant `database/*_update.sql` scripts and take a backup before applying any required migration.
5. Check `backend/config/database.php` and ensure `uploads/` is writable by PHP. The local development credentials are intentionally unchanged.
6. Open `http://localhost/st/login.php`, change the default admin password, and configure the QR Base URL in Settings.
7. For Apache/XAMPP, keep `uploads/.htaccess` in place. For Nginx or servers that ignore `.htaccess`, configure the server to deny execution of scripts under `/uploads/` and disable directory listings.

**Deployment path:** This build still uses `/st/` in internal links and redirects. Deploy under `/st/` unless these links are comprehensively updated and tested for a different base path.

Keep database backups and any installation-specific uploads separately when archiving a clean base copy.

# ST Asset Management System

## Release 1.6.1 — Security & Reliability Review

This release follows UX 1.6.0 and strengthens protection for state-changing operations. CSRF validation is now enforced on assignment, transfer, turnover, asset editing/image deletion, spreadsheet import, and Settings POST actions. PHP and JavaScript syntax checks pass. See `docs/notes/SECURITY_RELIABILITY_REVIEW_1.6.1.txt` for the review notes and deployment checks.


Local/LAN PHP + MySQL/MariaDB asset management application.

## Entry points
- `login.php` — login screen
- `index.php` — authenticated dashboard
- `asset_scan.php` — public QR verification endpoint

## Main folders
- `backend/` — authentication, database configuration, shared services and layout
- `frontend/` — CSS, JavaScript, icons and application pages
- `database/` — installation and migration SQL
- `templates/` — Excel import/export templates
- `docs/` — setup notes, manual, release notes and validation reports
- `uploads/` — runtime uploaded files; preserve this folder when updating the code

See `docs/PROJECT_STRUCTURE.md` for the detailed map and debugging order.

## Documentation
- `docs/manual/ST_Asset_Management_User_Manual.pdf` — updated full user manual.
- `docs/manual/ST_Asset_Management_Presentation_Workflow_Guide.pdf` — presentation/demo workflow guide.
- The in-app `User Guide` page also includes the complete asset lifecycle and links to both guides.

### Automatic Asset Codes (November 2026 update)
- **New Purchase, Donated, Transferred and Other:** the Add Asset form hides Asset Code and the server generates a unique random code in the form `ST-<16 hexadecimal characters>` when the asset is saved. The code is stored in `assets.asset_code`; existing QR-token generation remains unchanged.
- **Legacy / Existing:** the Asset Code field is visible and required. Enter the original organizational code; the existing duplicate check applies.
- No database migration is required. Existing asset codes are not changed. The edit-asset screen is unchanged.
- Verify in XAMPP by creating a test New Purchase, viewing its generated code and QR label, and then creating a test Legacy / Existing asset with a manually entered code. Also verify the duplicate-code warning.
