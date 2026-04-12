-- Email inbound storage, outbound logs, queue, and auto-responder rate limiting
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS email_inbound (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  imap_folder VARCHAR(191) NOT NULL DEFAULT 'INBOX',
  imap_uid INT UNSIGNED NOT NULL DEFAULT 0,
  from_email VARCHAR(255) NOT NULL DEFAULT '',
  from_name VARCHAR(255) NOT NULL DEFAULT '',
  to_email VARCHAR(255) NOT NULL DEFAULT '',
  subject VARCHAR(998) NOT NULL DEFAULT '',
  body_text MEDIUMTEXT,
  message_date DATETIME NULL DEFAULT NULL,
  autoresponder_sent TINYINT(1) NOT NULL DEFAULT 0,
  autoresponder_skip_reason VARCHAR(191) NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mailbox_uid (imap_folder, imap_uid),
  KEY idx_from (from_email),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_send_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  direction ENUM('outbound','inbound_note') NOT NULL DEFAULT 'outbound',
  template_type VARCHAR(64) NOT NULL DEFAULT '',
  to_email VARCHAR(255) NOT NULL DEFAULT '',
  to_name VARCHAR(255) NOT NULL DEFAULT '',
  subject VARCHAR(998) NOT NULL DEFAULT '',
  body_preview VARCHAR(500) NOT NULL DEFAULT '',
  status ENUM('queued','sending','sent','failed') NOT NULL DEFAULT 'sent',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 1,
  error_message VARCHAR(2000) NULL DEFAULT NULL,
  meta_json JSON NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_status (status, created_at),
  KEY idx_to (to_email),
  KEY idx_template (template_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_outbound_queue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  template_type VARCHAR(64) NOT NULL DEFAULT '',
  to_email VARCHAR(255) NOT NULL DEFAULT '',
  to_name VARCHAR(255) NOT NULL DEFAULT '',
  subject VARCHAR(998) NOT NULL DEFAULT '',
  body_text MEDIUMTEXT,
  body_html MEDIUMTEXT,
  status ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,
  next_attempt_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_error VARCHAR(2000) NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_poll (status, next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_autoresponder_rate (
  sender_email VARCHAR(255) NOT NULL,
  last_sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (sender_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
