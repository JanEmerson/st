-- ============================================================================
-- ST Asset Management System
-- File: database/st_db_user_appearance_login_background_update.sql
-- Purpose: Database installation/migration script.
-- Debugging: Verify the target database and inspect each ALTER/CREATE statement
--            before running a migration against an existing production database.
-- ============================================================================
-- Section: st_db_user_appearance_login_background_update database migration.
-- ST Asset Management System
-- Personal UI appearance + administrator login background migration
-- Non-destructive for existing st_db.

USE st_db;

ALTER TABLE users
    ADD COLUMN ui_theme_preset VARCHAR(30) NULL DEFAULT NULL,
    ADD COLUMN ui_theme_primary VARCHAR(7) NULL,
    ADD COLUMN ui_theme_primary_hover VARCHAR(7) NULL,
    ADD COLUMN ui_theme_sidebar VARCHAR(7) NULL,
    ADD COLUMN ui_theme_sidebar_hover VARCHAR(7) NULL,
    ADD COLUMN ui_theme_accent VARCHAR(7) NULL,
    ADD COLUMN ui_theme_mode ENUM('system','light','dark') NOT NULL DEFAULT 'system';

INSERT INTO app_settings (setting_key, setting_value) VALUES
    ('login_background_filename', '')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

SELECT id, username, ui_theme_preset, ui_theme_mode FROM users ORDER BY id;
SELECT setting_key, setting_value FROM app_settings WHERE setting_key='login_background_filename';
