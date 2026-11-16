-- ST ASSET MANAGEMENT SYSTEM - MASTER DATABASE DEPLOYMENT SCRIPT
-- Fresh installation only. Drops and recreates all ST tables.
-- Import this file on a new PC instead of running the individual *_update.sql files.

CREATE DATABASE IF NOT EXISTS st_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE st_db;
SET NAMES utf8mb4;
-- SAFETY: This installer is for a NEW, EMPTY database only.
-- No DROP TABLE statements are included. Existing tables will cause this
-- script to stop rather than deleting application records.
-- Back up the database before any schema operation.

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY, employee_id VARCHAR(50) NULL UNIQUE, username VARCHAR(100) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL, full_name VARCHAR(150) NOT NULL, department VARCHAR(150) NULL,
 position VARCHAR(100) NULL, role ENUM('Administrator','Asset Custodian','HR / Clearance Officer','Viewer') NOT NULL DEFAULT 'Viewer',
 status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active', avatar_filename VARCHAR(255) NULL,
 ui_theme_preset VARCHAR(30) NULL, ui_theme_primary VARCHAR(7) NULL, ui_theme_primary_hover VARCHAR(7) NULL,
 ui_theme_sidebar VARCHAR(7) NULL, ui_theme_sidebar_hover VARCHAR(7) NULL, ui_theme_accent VARCHAR(7) NULL,
 ui_theme_mode ENUM('system','light','dark') NOT NULL DEFAULT 'system', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO users (employee_id,username,password,full_name,department,position,role,status) VALUES
('ADMIN-001','admin','$2y$12$QirMHXBLQWEPj3A3j3TMW.C2va1.T/XFSl7hs99wtNm0ZeD9cPHpu','System Administrator','Administration','Administrator','Administrator','Active');

CREATE TABLE app_settings (
 setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO app_settings (setting_key,setting_value) VALUES
('app_name','ST Asset Management System'),('app_logo_filename',''),('login_background_filename',''),('app_base_url',''),
('theme_preset','st-green'),('theme_primary','#2A835F'),('theme_primary_hover','#12544F'),('theme_sidebar','#092328'),
('theme_sidebar_hover','#12544F'),('theme_accent','#8BBB92');

CREATE TABLE categories (
 id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, description TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO categories (name,description) VALUES
('IT Equipment','Computers, laptops, monitors, printers and other IT equipment'),
('Office Supplies','Furniture, office equipment and general office supplies'),
('Vehicles','Company-owned vehicles and transportation equipment');

CREATE TABLE assets (
 id INT AUTO_INCREMENT PRIMARY KEY, asset_code VARCHAR(50) NOT NULL UNIQUE, asset_name VARCHAR(150) NOT NULL,
 qr_token VARCHAR(64) NULL UNIQUE, qr_enabled TINYINT(1) NOT NULL DEFAULT 1, category_id INT NOT NULL,
 asset_type VARCHAR(100) NOT NULL, serial_number VARCHAR(150) NULL UNIQUE,
 asset_source ENUM('New Purchase','Legacy / Existing','Donated','Transferred','Other') NOT NULL DEFAULT 'New Purchase',
 condition_status ENUM('New','Good','Fair','Poor','Damaged') NOT NULL DEFAULT 'New', legacy_reference VARCHAR(100) NULL,
 date_added DATE NULL, supplier_name VARCHAR(150) NULL, purchase_number VARCHAR(100) NULL, purchase_date DATE NULL,
 purchase_cost DECIMAL(12,2) NULL, status ENUM('Available','Assigned','Maintenance','Retired') NOT NULL DEFAULT 'Available',
 current_user_id INT NULL, description TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_assets_category FOREIGN KEY(category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_assets_current_user FOREIGN KEY(current_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
 INDEX idx_assets_status(status), INDEX idx_assets_category(category_id), INDEX idx_assets_current_user(current_user_id), INDEX idx_assets_qr_token(qr_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asset_images (
 id INT AUTO_INCREMENT PRIMARY KEY, asset_id INT NOT NULL, image_filename VARCHAR(255) NOT NULL,
 image_original_name VARCHAR(255) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_asset_images_asset FOREIGN KEY(asset_id) REFERENCES assets(id) ON UPDATE CASCADE ON DELETE CASCADE,
 INDEX idx_asset_images_asset(asset_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asset_linked_items (
 id INT AUTO_INCREMENT PRIMARY KEY, asset_id INT NOT NULL, item_code VARCHAR(50) NULL, item_name VARCHAR(150) NOT NULL,
 item_type ENUM('Loose Tool','Accessory','Component','Safety Equipment','Included Item','Other') NOT NULL DEFAULT 'Loose Tool',
 serial_number VARCHAR(150) NULL, quantity INT NOT NULL DEFAULT 1,
 condition_status ENUM('New','Good','Fair','Poor','Damaged') NOT NULL DEFAULT 'Good', status ENUM('With Asset','Removed') NOT NULL DEFAULT 'With Asset',
 remarks TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_asset_linked_items_asset FOREIGN KEY(asset_id) REFERENCES assets(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 INDEX idx_asset_linked_items_asset(asset_id), INDEX idx_asset_linked_items_code(item_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asset_history (
 id INT AUTO_INCREMENT PRIMARY KEY, asset_id INT NOT NULL, action ENUM('ASSIGNED','TRANSFERRED','RETURNED') NOT NULL,
 from_user_id INT NULL, to_user_id INT NULL, performed_by INT NOT NULL, transaction_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 condition_status VARCHAR(100) NULL, remarks TEXT NULL,
 CONSTRAINT fk_history_asset FOREIGN KEY(asset_id) REFERENCES assets(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_history_from_user FOREIGN KEY(from_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT fk_history_to_user FOREIGN KEY(to_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT fk_history_performed_by FOREIGN KEY(performed_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 INDEX idx_asset_history_asset_date(asset_id,transaction_date), INDEX idx_asset_history_action(action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE maintenance_records (
 id INT AUTO_INCREMENT PRIMARY KEY, work_order_no VARCHAR(30) NULL UNIQUE, asset_id INT NOT NULL, title VARCHAR(150) NOT NULL,
 maintenance_type ENUM('Repair','Preventive','Inspection','Other') NOT NULL DEFAULT 'Repair',
 priority ENUM('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
 status ENUM('Open','In Progress','Waiting for Parts','Completed','Cancelled') NOT NULL DEFAULT 'Open',
 description TEXT NULL, diagnosis TEXT NULL, work_performed TEXT NULL, vendor_name VARCHAR(150) NULL,
 labor_cost DECIMAL(12,2) NULL, parts_cost DECIMAL(12,2) NULL, due_date DATE NULL, opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 completed_at DATETIME NULL, asset_status_before ENUM('Available','Maintenance') NOT NULL DEFAULT 'Available',
 attachment_filename VARCHAR(255) NULL, attachment_original_name VARCHAR(255) NULL, created_by INT NOT NULL, updated_by INT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_maintenance_records_asset FOREIGN KEY(asset_id) REFERENCES assets(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_maintenance_records_created_by FOREIGN KEY(created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_maintenance_records_updated_by FOREIGN KEY(updated_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 INDEX idx_maintenance_records_status(status), INDEX idx_maintenance_records_asset(asset_id), INDEX idx_maintenance_records_due_date(due_date), INDEX idx_maintenance_records_priority(priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE maintenance_history (
 id INT AUTO_INCREMENT PRIMARY KEY, maintenance_id INT NOT NULL,
 event_type ENUM('Created','Updated','Status Changed') NOT NULL, old_status VARCHAR(30) NULL, new_status VARCHAR(30) NULL,
 remarks TEXT NULL, performed_by INT NOT NULL, event_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_maintenance_history_record FOREIGN KEY(maintenance_id) REFERENCES maintenance_records(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_maintenance_history_user FOREIGN KEY(performed_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 INDEX idx_maintenance_history_record(maintenance_id,event_at), INDEX idx_maintenance_history_user(performed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'ST Asset Management System database installed successfully.' AS installation_status;
SELECT DATABASE() AS database_name;
SELECT 'Complete schema installed: users, app_settings, categories, assets, asset_images, asset_linked_items, asset_history, maintenance_records, maintenance_history.' AS schema_status;
SELECT 'Configure Settings -> QR Code Settings -> Base URL before printing QR labels.' AS qr_next_step;
SELECT 'Fresh-install administrator: admin / admin123. CHANGE THE PASSWORD IMMEDIATELY.' AS admin_next_step;
