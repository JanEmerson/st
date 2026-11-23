# ST Physical Inventory — Mobile + Desktop upgrade

## Install on your existing Windows XAMPP system
1. Back up the `st_db` database and `frontend/pages/physical_inventory.php`.
2. Ensure you already imported `database/st_db_physical_inventory_update.sql` from the previous build. If not, import it first.
3. Import `database/st_db_inventory_reconciliation_update.sql` using phpMyAdmin on `st_db`.
4. Copy ONLY `frontend/pages/physical_inventory.php` from this ZIP into `C:\xampp\htdocs\st\frontend\pages\`.
5. Sign in and visit `http://localhost/st/frontend/pages/physical_inventory.php`.

## Use
- Desktop: create/open session, focus code field, scan with USB QR scanner, review and save.
- Phone: connect to same Wi-Fi/LAN, open `http://YOUR-PC-LAN-IP/st/frontend/pages/physical_inventory.php`, sign in, select session, use camera/photo QR capture and Save Verification.
- Camera/photo picker works without direct live video. Browser support and local network configuration vary.
- Scanned ST QR URLs with `?token=...` are accepted, along with raw QR tokens and asset codes.
- Resolve discrepancies with notes, then close session. Pending is NOT automatically missing.
- Export CSV for Excel, or use Print Report for paper/PDF via browser.

## Safeguards
- Does not change the `assets` table, assignments, maintenance, QR label layout or other existing modules.
- Existing inventory records remain in place; no deletion migration.
- Resolution changes are recorded in session items. This is not a full append-only audit trail.
- Check the QR/photo feature on your own phone/browser and verify database imports before production use.
