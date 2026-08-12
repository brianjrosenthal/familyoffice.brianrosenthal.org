-- Family Office application schema
-- Create DB then use it
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Users table with exact structure specified
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) DEFAULT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL DEFAULT '',
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  email_verify_token VARCHAR(64) DEFAULT NULL,
  email_verified_at DATETIME DEFAULT NULL,
  password_reset_token_hash CHAR(64) DEFAULT NULL,
  password_reset_expires_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX idx_users_email_verify_token ON users(email_verify_token);
CREATE INDEX idx_users_pwreset_expires ON users(password_reset_expires_at);

-- Settings key-value table
CREATE TABLE settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(191) NOT NULL UNIQUE,
  value LONGTEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default settings
INSERT INTO settings (key_name, value) VALUES
  ('site_title', 'Family Office'),
  ('announcement', ''),
  ('timezone', 'America/New_York'),
  ('login_image_file_id', ''),
  ('site_base_url', 'https://familyoffice.brianrosenthal.org'),
  ('weekly_digest_enabled', '1')
ON DUPLICATE KEY UPDATE value=VALUES(value);

-- ===== Files Storage (DB-backed uploads) =====

-- Public files (profile photos)
CREATE TABLE public_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data LONGBLOB NOT NULL,
  content_type VARCHAR(100) DEFAULT NULL,
  original_filename VARCHAR(255) DEFAULT NULL,
  byte_length INT UNSIGNED DEFAULT NULL,
  sha256 CHAR(64) DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pf_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_pf_sha256 ON public_files(sha256);
CREATE INDEX idx_pf_created_by ON public_files(created_by_user_id);
CREATE INDEX idx_pf_created_at ON public_files(created_at);

-- Link columns (added via ALTER to avoid circular FK creation order)
ALTER TABLE users
  ADD COLUMN photo_public_file_id INT NULL;

ALTER TABLE users
  ADD CONSTRAINT fk_users_photo_public_file
    FOREIGN KEY (photo_public_file_id) REFERENCES public_files(id) ON DELETE SET NULL;

-- ===== Activity Log =====
CREATE TABLE activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT NULL,
  action_type VARCHAR(64) NOT NULL,
  json_metadata LONGTEXT NULL,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_al_created_at ON activity_log(created_at);
CREATE INDEX idx_al_user_id ON activity_log(user_id);
CREATE INDEX idx_al_action_type ON activity_log(action_type);

-- ===== Email Log =====
CREATE TABLE emails_sent (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_by_user_id INT NULL,
  to_email VARCHAR(255) NOT NULL,
  to_name VARCHAR(255) DEFAULT NULL,
  cc_email VARCHAR(255) DEFAULT NULL,
  subject VARCHAR(500) NOT NULL,
  body_html LONGTEXT NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  error_message TEXT DEFAULT NULL,
  CONSTRAINT fk_emails_sent_user FOREIGN KEY (sent_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_emails_sent_created_at ON emails_sent(created_at);
CREATE INDEX idx_emails_sent_user_id ON emails_sent(sent_by_user_id);
CREATE INDEX idx_emails_sent_to_email ON emails_sent(to_email);
CREATE INDEX idx_emails_sent_success ON emails_sent(success);

-- ===== Private Files (Document Vault attachments) =====
-- Unlike public_files, these are served only through a login-checked download
-- endpoint and are never written to the on-disk cache.
CREATE TABLE private_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data LONGBLOB NOT NULL,
  content_type VARCHAR(100) DEFAULT NULL,
  original_filename VARCHAR(255) DEFAULT NULL,
  byte_length INT UNSIGNED DEFAULT NULL,
  sha256 CHAR(64) DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prf_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_prf_sha256 ON private_files(sha256);
CREATE INDEX idx_prf_created_by ON private_files(created_by_user_id);

-- ===== Household Assets =====
CREATE TABLE assets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(100) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  purchase_date DATE DEFAULT NULL,
  purchase_price DECIMAL(12,2) DEFAULT NULL,
  warranty_info TEXT DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_assets_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_assets_name ON assets(name);
CREATE INDEX idx_assets_category ON assets(category);

-- An asset can have multiple photos (stored as public_files, like profile photos)
CREATE TABLE asset_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  public_file_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ap_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
  CONSTRAINT fk_ap_file FOREIGN KEY (public_file_id) REFERENCES public_files(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_ap_asset_id ON asset_photos(asset_id);

-- Receipts for an asset: proof-of-purchase records kept for taxes. Unlike
-- asset photos, the image is a private file (login-checked download, never
-- disk-cached) because receipts are financial records.
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

-- ===== Document Vault =====
CREATE TABLE documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  category VARCHAR(100) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  owner_user_id INT DEFAULT NULL COMMENT 'Family member this document belongs to',
  private_file_id INT DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Upload date',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_docs_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_docs_file FOREIGN KEY (private_file_id) REFERENCES private_files(id) ON DELETE SET NULL,
  CONSTRAINT fk_docs_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_docs_title ON documents(title);
CREATE INDEX idx_docs_category ON documents(category);
CREATE INDEX idx_docs_owner ON documents(owner_user_id);

-- ===== Contacts / Directory =====
CREATE TABLE contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  contact_type ENUM('person','organization') NOT NULL DEFAULT 'person',
  organization VARCHAR(255) DEFAULT NULL COMMENT 'Company/organization name (for a person: who they work for)',
  job_title VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  website VARCHAR(255) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_contacts_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_contacts_name ON contacts(name);

-- A contact may hold multiple categories/roles (e.g. both Plumber and Emergency Contact)
CREATE TABLE contact_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contact_id INT NOT NULL,
  category VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cc_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
  UNIQUE KEY unique_contact_category (contact_id, category)
) ENGINE=InnoDB;

CREATE INDEX idx_cc_category ON contact_categories(category);

-- ===== Insurance Policies =====
CREATE TABLE insurance_policies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(100) DEFAULT NULL,
  insurance_company VARCHAR(255) DEFAULT NULL,
  policy_number VARCHAR(100) DEFAULT NULL,
  effective_date DATE DEFAULT NULL,
  expiration_date DATE DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_policies_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_policies_name ON insurance_policies(name);
CREATE INDEX idx_policies_category ON insurance_policies(category);
CREATE INDEX idx_policies_expiration ON insurance_policies(expiration_date);

-- ===== Recurring Obligations =====
-- The app's central concept (see docs/app-spec.md). Recurrence types:
--   does_not_repeat: one-time obligation due on anchor_date; next_due_on
--     becomes NULL once completed
--   every_n_days / every_n_weeks / every_n_months / every_n_years:
--     fixed schedule of occurrences anchored at anchor_date, repeating every
--     recurrence_interval of the type's unit
--   day_of_month:  every month on day_of_month (clamped to short months)
--   date_of_year:  every year on annual_month_day 'MM-DD' (Feb 29 clamps to 28)
--   after_completion: due recurrence_interval recurrence_unit after the last
--     completion (anchor_date optionally sets the first due date)
CREATE TABLE obligations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL COMMENT 'Instructions for completing the obligation',
  category VARCHAR(100) DEFAULT NULL,
  recurrence_type ENUM('does_not_repeat','every_n_days','every_n_weeks','every_n_months','every_n_years','day_of_month','date_of_year','after_completion') NOT NULL,
  recurrence_interval INT DEFAULT NULL COMMENT 'N for every_n_* and after_completion',
  recurrence_unit ENUM('days','weeks','months') DEFAULT NULL COMMENT 'Unit for after_completion',
  day_of_month TINYINT DEFAULT NULL COMMENT '1-31 for day_of_month',
  annual_month_day CHAR(5) DEFAULT NULL COMMENT 'MM-DD for date_of_year',
  anchor_date DATE DEFAULT NULL COMMENT 'Due date for does_not_repeat; schedule start for every_n_*; optional first due date for after_completion',
  responsible_user_id INT DEFAULT NULL COMMENT 'NULL = unassigned (notifications fall back to admins)',
  applies_to_user_id INT DEFAULT NULL COMMENT 'NULL = entire family',
  reminder_lead_days INT NOT NULL DEFAULT 7 COMMENT 'Days before due date to start reminding',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_completed_on DATE DEFAULT NULL,
  next_due_on DATE DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_obl_responsible FOREIGN KEY (responsible_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_obl_applies_to FOREIGN KEY (applies_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_obl_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_obl_next_due ON obligations(next_due_on);
CREATE INDEX idx_obl_active ON obligations(is_active);
CREATE INDEX idx_obl_responsible ON obligations(responsible_user_id);
CREATE INDEX idx_obl_category ON obligations(category);

-- Permanent completion history (never overwritten; rows may only be added or deleted)
CREATE TABLE obligation_completions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  obligation_id INT NOT NULL,
  completed_by_user_id INT DEFAULT NULL,
  completed_on DATE NOT NULL,
  notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_oc_obligation FOREIGN KEY (obligation_id) REFERENCES obligations(id) ON DELETE CASCADE,
  CONSTRAINT fk_oc_user FOREIGN KEY (completed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_oc_obligation ON obligation_completions(obligation_id);
CREATE INDEX idx_oc_completed_on ON obligation_completions(completed_on);

-- Linked objects: the supporting records needed to complete an obligation
CREATE TABLE obligation_assets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  obligation_id INT NOT NULL,
  asset_id INT NOT NULL,
  CONSTRAINT fk_oa_obligation FOREIGN KEY (obligation_id) REFERENCES obligations(id) ON DELETE CASCADE,
  CONSTRAINT fk_oa_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
  UNIQUE KEY unique_obligation_asset (obligation_id, asset_id)
) ENGINE=InnoDB;

CREATE TABLE obligation_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  obligation_id INT NOT NULL,
  document_id INT NOT NULL,
  CONSTRAINT fk_od_obligation FOREIGN KEY (obligation_id) REFERENCES obligations(id) ON DELETE CASCADE,
  CONSTRAINT fk_od_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  UNIQUE KEY unique_obligation_document (obligation_id, document_id)
) ENGINE=InnoDB;

CREATE TABLE obligation_policies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  obligation_id INT NOT NULL,
  policy_id INT NOT NULL,
  CONSTRAINT fk_op_obligation FOREIGN KEY (obligation_id) REFERENCES obligations(id) ON DELETE CASCADE,
  CONSTRAINT fk_op_policy FOREIGN KEY (policy_id) REFERENCES insurance_policies(id) ON DELETE CASCADE,
  UNIQUE KEY unique_obligation_policy (obligation_id, policy_id)
) ENGINE=InnoDB;

CREATE TABLE obligation_contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  obligation_id INT NOT NULL,
  contact_id INT NOT NULL,
  CONSTRAINT fk_ocn_obligation FOREIGN KEY (obligation_id) REFERENCES obligations(id) ON DELETE CASCADE,
  CONSTRAINT fk_ocn_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
  UNIQUE KEY unique_obligation_contact (obligation_id, contact_id)
) ENGINE=InnoDB;

-- Progress updates on an obligation: a comment, an optional private-file
-- attachment (e.g. a receipt), and an optional link to the completion it
-- recorded (when the update marked the obligation complete).
CREATE TABLE obligation_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  obligation_id INT NOT NULL,
  created_by_user_id INT DEFAULT NULL,
  comment TEXT DEFAULT NULL,
  private_file_id INT DEFAULT NULL,
  completion_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ocm_obligation FOREIGN KEY (obligation_id) REFERENCES obligations(id) ON DELETE CASCADE,
  CONSTRAINT fk_ocm_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ocm_file FOREIGN KEY (private_file_id) REFERENCES private_files(id) ON DELETE SET NULL,
  CONSTRAINT fk_ocm_completion FOREIGN KEY (completion_id) REFERENCES obligation_completions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_ocm_obligation ON obligation_comments(obligation_id);
CREATE INDEX idx_ocm_completion ON obligation_comments(completion_id);

-- ===== Notification Log =====
-- Every emailed reminder is recorded here; the daily runner checks it before
-- sending so it is safe to run multiple times per day (idempotent).
-- notification_type:
--   overdue        — re-sent daily while overdue
--   due_today      — sent on the due date
--   entered_window — sent once when the obligation enters its reminder window
--   weekly_summary — Monday "week ahead" digest (obligation_id is NULL)
CREATE TABLE notification_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  obligation_id INT DEFAULT NULL,
  recipient_user_id INT NOT NULL,
  notification_type ENUM('overdue','due_today','entered_window','weekly_summary') NOT NULL,
  notification_date DATE NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  email_address VARCHAR(255) DEFAULT NULL,
  delivery_status ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  error_message TEXT DEFAULT NULL,
  CONSTRAINT fk_nl_obligation FOREIGN KEY (obligation_id) REFERENCES obligations(id) ON DELETE CASCADE,
  CONSTRAINT fk_nl_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_nl_dedup ON notification_log(obligation_id, recipient_user_id, notification_type, notification_date);
CREATE INDEX idx_nl_date ON notification_log(notification_date);

-- Optional: seed an admin user (update email and password hash, then remove)
INSERT INTO users (first_name,last_name,email,password_hash,is_admin,email_verified_at)
VALUES ('Brian','Rosenthal','brian.rosenthal@gmail.com','$2y$10$9xH7Jq4v3o6s9k3y8i4rVOyWb0yBYZ5rW.0f9pZ.gG9K6l7lS6b2S',1,NOW());
