# ST integrated upgrade - existing XAMPP installation
1. Back up `st_db` and `uploads` before changing any files.
2. Do NOT replace your entire working `C:\xampp\htdocs\st` folder.
3. Copy the new files: `frontend/pages/physical_inventory.php`,
   `frontend/pages/print_center.php`, `frontend/pages/asset_alerts.php`,
   `frontend/pages/local_backup.php`, `tools/backup-st.ps1`,
   `docs/setup/BACKUP_RESTORE.md`.
4. Review/merge `backend/includes/sidebar.php` and `index.php` if you have
   newer local edits; these add navigation and dashboard shortcuts.
5. In phpMyAdmin, select `st_db` and import ONLY
   `database/st_db_physical_inventory_update.sql`.
   Never import the master `st_db_install.sql` into your live database.
6. Test login, existing QR print, maintenance, asset lifecycle, and new modules.
7. Test a database backup with `tools/backup-st.ps1` before relying on it.
8. Keep Git `main` unchanged until testing passes.
