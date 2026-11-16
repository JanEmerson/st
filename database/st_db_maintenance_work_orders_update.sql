-- ST Asset Management System
-- Maintenance work orders migration
-- Safe for an existing st_db database. Does not modify existing asset records.

USE st_db;

CREATE TABLE IF NOT EXISTS maintenance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_order_no VARCHAR(30) NULL UNIQUE,
    asset_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    maintenance_type ENUM('Repair','Preventive','Inspection','Other') NOT NULL DEFAULT 'Repair',
    priority ENUM('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
    status ENUM('Open','In Progress','Waiting for Parts','Completed','Cancelled') NOT NULL DEFAULT 'Open',
    description TEXT NULL,
    diagnosis TEXT NULL,
    work_performed TEXT NULL,
    vendor_name VARCHAR(150) NULL,
    labor_cost DECIMAL(12,2) NULL,
    parts_cost DECIMAL(12,2) NULL,
    due_date DATE NULL,
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    asset_status_before ENUM('Available','Maintenance') NOT NULL DEFAULT 'Available',
    attachment_filename VARCHAR(255) NULL,
    attachment_original_name VARCHAR(255) NULL,
    created_by INT NOT NULL,
    updated_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_maintenance_records_asset FOREIGN KEY (asset_id) REFERENCES assets(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_records_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_records_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_maintenance_records_status (status),
    INDEX idx_maintenance_records_asset (asset_id),
    INDEX idx_maintenance_records_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    maintenance_id INT NOT NULL,
    event_type ENUM('Created','Updated','Status Changed') NOT NULL,
    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NULL,
    remarks TEXT NULL,
    performed_by INT NOT NULL,
    event_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_maintenance_history_record FOREIGN KEY (maintenance_id) REFERENCES maintenance_records(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_maintenance_history_user FOREIGN KEY (performed_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_maintenance_history_record (maintenance_id, event_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;