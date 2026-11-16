-- ============================================================================
-- ST Asset Management System
-- File: database/st_db_theme_update.sql
-- Purpose: Database installation/migration script.
-- Debugging: Verify the target database and inspect each ALTER/CREATE statement
--            before running a migration against an existing production database.
-- ============================================================================
-- Section: st_db_theme_update database migration.
-- ST Asset Management System
-- Theme / Appearance settings migration
-- Safe for an existing st_db database. Does not modify asset/user data.

USE st_db;

INSERT INTO app_settings (setting_key, setting_value) VALUES
    ('theme_preset', 'st-green'),
    ('theme_primary', '#2A835F'),
    ('theme_primary_hover', '#12544F'),
    ('theme_sidebar', '#092328'),
    ('theme_sidebar_hover', '#12544F'),
    ('theme_accent', '#8BBB92')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

SELECT setting_key, setting_value
FROM app_settings
WHERE setting_key LIKE 'theme_%'
ORDER BY setting_key;
