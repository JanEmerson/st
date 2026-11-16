-- ============================================================================
-- ST Asset Management System
-- File: database/st_db_legacy_assets_update.sql
-- Purpose: Database installation/migration script.
-- Debugging: Verify the target database and inspect each ALTER/CREATE statement
--            before running a migration against an existing production database.
-- ============================================================================
-- Section: st_db_legacy_assets_update database migration.
-- ============================================================
-- ST ASSET MANAGEMENT SYSTEM
-- LEGACY / EXISTING ASSETS DATABASE UPDATE
-- ============================================================
-- Run this file on an EXISTING st_db installation.
-- It does not delete existing asset records.
-- ============================================================

USE st_db;

ALTER TABLE assets
    ADD COLUMN IF NOT EXISTS asset_source ENUM(
        'New Purchase',
        'Legacy / Existing',
        'Donated',
        'Transferred',
        'Other'
    ) NOT NULL DEFAULT 'New Purchase'
    AFTER serial_number,

    ADD COLUMN IF NOT EXISTS condition_status ENUM(
        'New',
        'Good',
        'Fair',
        'Poor',
        'Damaged'
    ) NOT NULL DEFAULT 'New'
    AFTER asset_source,

    ADD COLUMN IF NOT EXISTS legacy_reference VARCHAR(100) NULL
    AFTER condition_status,

    ADD COLUMN IF NOT EXISTS date_added DATE NULL
    AFTER legacy_reference;

UPDATE assets
SET date_added = DATE(created_at)
WHERE date_added IS NULL;

CREATE TABLE IF NOT EXISTS asset_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    image_filename VARCHAR(255) NOT NULL,
    image_original_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_asset_images_asset
        FOREIGN KEY (asset_id)
        REFERENCES assets(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

SELECT 'Legacy / Existing asset fields installed successfully.' AS update_status;
