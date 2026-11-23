# ST Asset Management - consolidated deployment

## Fresh install (new PC or new EMPTY st_db)
1. Install XAMPP, start Apache and MySQL.
2. Extract this ZIP into `C:\xampp\htdocs\st`.
3. In phpMyAdmin, import `database/st_db_install.sql` **once**, into an empty environment.
   The script creates `st_db`, all existing ST tables, and both inventory tables
   including reconciliation columns. Do NOT also import inventory update scripts.
4. Open `http://localhost/st/` and sign in using the installer credentials.
   Change the default administrator password immediately.
5. Configure the application base URL under Settings > QR Code Settings.
6. Verify assets, maintenance, QR labels, inventory, CSV reporting, and backups.

## Upgrade existing installation with existing st_db
1. Back up `st_db` and the uploads directory first.
2. Do NOT import `st_db_install.sql`: it is ONLY for a fresh, empty database.
3. If neither inventory table exists, import `database/st_db_physical_inventory_update.sql`.
   This now creates both tables WITH reconciliation fields; skip the second migration.
4. If inventory tables already exist but lack `reconciliation_status`, import
   `database/st_db_inventory_reconciliation_update.sql` once.
5. If the inventory tables already have reconciliation columns, import neither.
6. Merge/copy changed application pages, especially
   `frontend/pages/physical_inventory.php`; retain local settings and uploads.
7. Test on the Git testing branch before merging to main.

## Desktop and mobile inventory
- Desktop: enter asset code/QR token or scan with a USB keyboard-style QR scanner.
- Mobile: connect to same LAN, use the PC LAN IP (not `localhost`), log in,
  select an inventory session, and capture/select a QR image.
- Camera/photo QR detection requires browser BarcodeDetector support; where
  unavailable, use manual code entry or a compatible scanner.
- On some mobile browsers, direct camera access requires HTTPS; the LAN HTTP
  experience and permissions vary by browser.
- Inventory verification never automatically changes asset assignment.
- Resolve open discrepancies before closing sessions. Pending items are not
  automatically classified as missing.

## Limitations and release testing
- This package is source-integrated and PHP-syntax checked, but has not been
  run against your live XAMPP database or tested on a phone or printer.
- The backup script is manually invoked; configure Windows Task Scheduler
  separately if recurring backups are needed.
- Keep a second backup on an external drive and test restore on a separate DB.
