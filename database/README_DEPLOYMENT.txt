ST Asset Management - consolidated database installer

FRESH INSTALL ONLY:
1. Back up any existing data.
2. In phpMyAdmin, create/select an EMPTY st_db database.
3. Import st_db_install.sql once.
4. Confirm all tables exist, including inventory_sessions, inventory_session_items,
   accountability_batches, accountability_batch_items, maintenance_records.
5. Change the default admin password immediately.

EXISTING DATABASE:
DO NOT import st_db_install.sql into your existing populated st_db.
Keep your existing records. Apply only migrations that have not already been
applied, after a backup and schema review. The *_verify.sql file is read-only
and is NOT part of the installer.

SOURCE: ST-Asset-Management-Consolidated-Deployment.zip available in this chat.
This installer was statically reviewed but has NOT been executed in MySQL.
If your local SQL files have changed since that archive, upload the current
database folder so they can be reconciled precisely.
