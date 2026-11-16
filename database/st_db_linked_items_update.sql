-- ============================================================================
-- ST Asset Management System
-- File: database/st_db_linked_items_update.sql
-- Purpose: Database installation/migration script.
-- Debugging: Verify the target database and inspect each ALTER/CREATE statement
--            before running a migration against an existing production database.
-- ============================================================================
-- Section: st_db_linked_items_update database migration.
-- ST Asset Management System
-- Linked Items / Loose Tools migration
-- Run this once on an existing st_db database.

USE st_db;

CREATE TABLE IF NOT EXISTS asset_linked_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    item_code VARCHAR(50) NULL,
    item_name VARCHAR(150) NOT NULL,
    item_type ENUM(
        'Loose Tool',
        'Accessory',
        'Component',
        'Safety Equipment',
        'Included Item',
        'Other'
    ) NOT NULL DEFAULT 'Loose Tool',
    serial_number VARCHAR(150) NULL,
    quantity INT NOT NULL DEFAULT 1,
    condition_status ENUM(
        'New',
        'Good',
        'Fair',
        'Poor',
        'Damaged'
    ) NOT NULL DEFAULT 'Good',
    status ENUM('With Asset','Removed') NOT NULL DEFAULT 'With Asset',
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_asset_linked_items_asset
        FOREIGN KEY (asset_id) REFERENCES assets(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_asset_linked_items_asset (asset_id),
    INDEX idx_asset_linked_items_code (item_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Linked Items / Loose Tools migration completed.' AS status;
