# ST Database Deployment

## Fresh installation on another PC
Import **`st_db_install.sql` only**. It is the consolidated master schema for the current application.

It includes users/roles, settings/branding/theme, categories, assets, QR fields, asset images, linked items/loose tools, accountability history, maintenance work orders, maintenance history, indexes, foreign keys, default categories, and the default administrator.

### Default administrator
- Username: `admin`
- Password: `admin123`

Change the password immediately after first login.

### Important
The master installer is **non-destructive**: it no longer drops tables and is intended for a **new empty database only**. It stops if the application tables already exist. Never import it as an upgrade script. Back up any existing database first.

## Existing/production database
For an existing installation, **do not run `st_db_install.sql`**. Back up the database, inspect the existing schema, and apply only the needed `*_update.sql` migrations in the correct order. Test migrations on a copy first.

## Deployment checklist
1. Install XAMPP and start Apache + MySQL/MariaDB.
2. Copy the ST project to `C:\xampp\htdocs\st`.
3. Import `database/st_db_install.sql` through phpMyAdmin.
4. Verify that `st_db` and all nine tables were created.
5. Check `backend/config/database.php` if the target MySQL username/password differs.
6. Log in with the default administrator.
7. Change the administrator password.
8. Configure application branding and QR Base URL in Settings.
9. Preserve the project's `uploads/` directory when migrating an existing installation so images, avatars, branding, login backgrounds, and maintenance attachments are not lost.
10. Test login, asset creation, assignment, transfer, turnover, maintenance, QR scan, history, clearance, and reports.

## Web server upload protection
Apache/XAMPP: retain `uploads/.htaccess`, which denies execution of common script extensions and disables directory listings. Confirm your Apache configuration permits these rules. On Nginx, add an equivalent server-level rule preventing script execution in `/uploads/`.

## Application URL path
The current build assumes installation at `/st/`; other URL prefixes are not validated. Keep `/st/` for this release.
