-- ============================================================================
-- ST Asset Management System
-- File: database/st_db_qr_update.sql
-- Purpose: Database installation/migration script.
-- Debugging: Verify the target database and inspect each ALTER/CREATE statement
--            before running a migration against an existing production database.
-- ============================================================================
-- Section: st_db_qr_update database migration.
USE st_db;

-- Add QR identity fields to assets. Run only once on an existing database.
ALTER TABLE assets
    ADD COLUMN qr_token VARCHAR(64) NULL UNIQUE AFTER asset_name,
    ADD COLUMN qr_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER qr_token;

UPDATE assets
SET qr_token = LOWER(HEX(RANDOM_BYTES(24)))
WHERE qr_token IS NULL OR qr_token = '';

INSERT INTO app_settings (setting_key, setting_value)
VALUES ('app_base_url', '')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- If your server does not support RANDOM_BYTES(), replace the UPDATE with: 
-- UPDATE assets SET qr_token = LOWER(REPLACE(UUID(), '-', '')) WHERE qr_token IS NULL OR qr_token = '';
