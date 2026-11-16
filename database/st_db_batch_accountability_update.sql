-- Run once on an existing st_db database. Non-destructive migration.
CREATE TABLE IF NOT EXISTS accountability_batches (
 id INT AUTO_INCREMENT PRIMARY KEY, po_number VARCHAR(100) NOT NULL, created_by INT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_batch_po (po_number), FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS accountability_batch_items (
 id INT AUTO_INCREMENT PRIMARY KEY, batch_id INT NOT NULL, asset_id INT NOT NULL, asset_code VARCHAR(50) NOT NULL,
 part_number VARCHAR(150) NOT NULL, serial_number VARCHAR(150) NULL, employee_name VARCHAR(150) NOT NULL, employee_id INT NOT NULL,
 UNIQUE KEY uq_initial_batch_asset (asset_id), INDEX idx_batch_items_batch (batch_id),
 FOREIGN KEY (batch_id) REFERENCES accountability_batches(id) ON DELETE RESTRICT,
 FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
