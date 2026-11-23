# ST Asset Management — Windows XAMPP backup and restore

## Backup
Open PowerShell in `C:\xampp\htdocs\st` and run:

`powershell -ExecutionPolicy Bypass -File .\tools\backup-st.ps1`

The ZIP is stored in your Documents\ST-Backups directory (outside htdocs).
It contains `st_db.sql` and a copy of `uploads/`. Verify the archive and
keep another copy on an external drive. The script assumes the standard
local XAMPP root account and database `st_db`.

## Safe restore procedure (manual, administrator only)
1. Close the application for all users. Copy your current `uploads` folder.
2. Make a fresh backup of the current database before any restore.
3. Extract the desired backup ZIP outside `htdocs`.
4. **First test** the SQL import in a new database named `st_restore_test`
   using phpMyAdmin. Inspect the records and verify the backup is correct.
5. For a real restore, use phpMyAdmin to import `st_db.sql` into a freshly
   created replacement database during a maintenance window. This overwrites
   database state: do not import into a populated live database without
   understanding the consequences.
6. Restore `uploads/` from the same backup, preserving filenames and paths.
7. Verify login, asset counts, images, QR scanning, accountability and maintenance.

There is deliberately no one-click web restore: accidental restore could
overwrite live data. The backup script itself does not modify the database.
