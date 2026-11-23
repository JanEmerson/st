CREATE TABLE IF NOT EXISTS inventory_sessions (
 id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150) NOT NULL,
 status ENUM('Open','Closed') NOT NULL DEFAULT 'Open',
 created_by INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 closed_at DATETIME NULL,
 FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS inventory_session_items (
 id INT AUTO_INCREMENT PRIMARY KEY, session_id INT NOT NULL, asset_id INT NOT NULL,
 expected_status VARCHAR(30) NOT NULL, expected_user_id INT NULL,
 verification_status ENUM('Pending','Verified','Discrepancy') NOT NULL DEFAULT 'Pending',
 notes VARCHAR(500) NULL, verified_by INT NULL, verified_at DATETIME NULL,
 reconciliation_status ENUM('Not Required','Open','Resolved') NOT NULL DEFAULT 'Not Required',
 reconciliation_notes VARCHAR(1000) NULL, reconciled_by INT NULL, reconciled_at DATETIME NULL,
 UNIQUE KEY uq_inventory_session_asset(session_id,asset_id),
 FOREIGN KEY(session_id) REFERENCES inventory_sessions(id) ON DELETE CASCADE,
 FOREIGN KEY(asset_id) REFERENCES assets(id),
 FOREIGN KEY(verified_by) REFERENCES users(id),
 FOREIGN KEY(reconciled_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
