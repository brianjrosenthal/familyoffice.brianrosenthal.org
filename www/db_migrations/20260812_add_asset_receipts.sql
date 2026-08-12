-- Receipts for household assets: proof-of-purchase records kept for taxes.
-- Each receipt has a title, a description, and an image stored as a private
-- file (login-checked download, never written to the public disk cache).

CREATE TABLE asset_receipts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  private_file_id INT DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ar_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
  CONSTRAINT fk_ar_file FOREIGN KEY (private_file_id) REFERENCES private_files(id) ON DELETE SET NULL,
  CONSTRAINT fk_ar_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_ar_asset_id ON asset_receipts(asset_id);
