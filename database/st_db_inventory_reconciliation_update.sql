-- Import into existing st_db after the original physical inventory migration.
-- Run once; safe to re-run when columns already exist only on MariaDB versions
-- supporting ADD COLUMN IF NOT EXISTS (standard Windows XAMPP MariaDB).
ALTER TABLE inventory_session_items
 ADD COLUMN IF NOT EXISTS reconciliation_status ENUM('Not Required','Open','Resolved') NOT NULL DEFAULT 'Not Required',
 ADD COLUMN IF NOT EXISTS reconciliation_notes VARCHAR(1000) NULL,
 ADD COLUMN IF NOT EXISTS reconciled_by INT NULL,
 ADD COLUMN IF NOT EXISTS reconciled_at DATETIME NULL;
UPDATE inventory_session_items SET reconciliation_status='Open' WHERE verification_status='Discrepancy' AND reconciliation_status='Not Required';
