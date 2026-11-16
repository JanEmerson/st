-- ============================================================================
-- ST Asset Management System
-- File: database/st_db_branding_profile_update.sql
-- Purpose: Database installation/migration script.
-- Debugging: Verify the target database and inspect each ALTER/CREATE statement
--            before running a migration against an existing production database.
-- ============================================================================
-- Section: st_db_branding_profile_update database migration.
-- ============================================================
-- ST ASSET MANAGEMENT SYSTEM
-- BRANDING + USER PROFILE PICTURE UPDATE
-- For an EXISTING st_db installation
-- ============================================================

USE st_db;

-- Add profile picture storage to users.
ALTER TABLE users
ADD COLUMN avatar_filename VARCHAR(255) NULL
AFTER status;

-- Store application name and logo filename.
CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_settings (setting_key, setting_value)
VALUES ('app_name', 'ST Asset Management System')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

INSERT INTO app_settings (setting_key, setting_value)
VALUES ('app_logo_filename', '')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

-- Create these folders under C:\xampp\htdocs\st if they do not exist:
-- uploads\app
-- uploads\avatars
