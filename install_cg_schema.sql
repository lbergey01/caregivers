-- Caregivers app schema
-- One-time install. Safe to re-run (IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS cg_clients (
  id           INT(11) NOT NULL AUTO_INCREMENT,
  name         VARCHAR(120) NOT NULL,
  notes        TEXT NULL,
  active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cg_caregivers (
  id           INT(11) NOT NULL AUTO_INCREMENT,
  name         VARCHAR(120) NOT NULL,
  phone        VARCHAR(32) NULL,
  email        VARCHAR(155) NULL,
  user_id      INT(11) NULL,
  color        VARCHAR(9) NOT NULL DEFAULT '#3788d8',
  active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user (user_id),
  KEY idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cg_shifts (
  id             INT(11) NOT NULL AUTO_INCREMENT,
  client_id      INT(11) NOT NULL,
  caregiver_id   INT(11) NOT NULL,
  start_dt       DATETIME NOT NULL,
  end_dt         DATETIME NOT NULL,
  created_by     INT(11) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_client (client_id),
  KEY idx_caregiver (caregiver_id),
  KEY idx_range (client_id, start_dt, end_dt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cg_shift_notes (
  id                  INT(11) NOT NULL AUTO_INCREMENT,
  shift_id            INT(11) NOT NULL,
  body                TEXT NOT NULL,
  author_user_id      INT(11) NULL,
  author_caregiver_id INT(11) NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  edited_at           DATETIME NULL,
  edited_by           INT(11) NULL,
  PRIMARY KEY (id),
  KEY idx_shift (shift_id),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cg_shift_attachments (
  id           INT(11) NOT NULL AUTO_INCREMENT,
  shift_id     INT(11) NOT NULL,
  note_id      INT(11) NULL,
  filename     VARCHAR(255) NOT NULL,
  orig_name    VARCHAR(255) NOT NULL,
  mime         VARCHAR(80)  NOT NULL,
  size_bytes   INT(11) NOT NULL,
  uploaded_by  INT(11) NULL,
  uploaded_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_shift (shift_id),
  KEY idx_note  (note_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cg_settings (
  id    INT(11) NOT NULL AUTO_INCREMENT,
  skey  VARCHAR(64) NOT NULL,
  sval  TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_skey (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed defaults (idempotent)
INSERT IGNORE INTO cg_settings (skey, sval) VALUES
  ('sms_provider', 'voipms'),
  ('sms_user_id', ''),
  ('sms_pass', ''),
  ('sms_did', ''),
  ('sms_private_ip', ''),
  ('sms_private_port', ''),
  ('sms_private_user', ''),
  ('sms_private_pass', ''),
  ('default_client_id', '');

-- Seed one client so v1 works immediately
INSERT INTO cg_clients (name, notes)
SELECT 'Primary Client', 'Auto-created on install. Rename me on the Clients page.'
WHERE NOT EXISTS (SELECT 1 FROM cg_clients LIMIT 1);

-- Set default_client_id to the first client if unset
UPDATE cg_settings
  SET sval = (SELECT MIN(id) FROM cg_clients)
  WHERE skey = 'default_client_id' AND (sval = '' OR sval IS NULL);

-- Add 'Caregiver' permission level (id auto-assigned). Idempotent.
INSERT INTO permissions (name, descrip)
SELECT 'Caregiver', 'Can view all shifts and manage their own'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'Caregiver');
