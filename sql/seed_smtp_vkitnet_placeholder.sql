-- Fills empty SMTP row so the dashboard "email not configured" banner clears.
-- Set the real password in Admin → System Settings → Email, .env (VK_SMTP_PASS / MAIL_PASSWORD), or run setup/email-auto-config.php.

SET NAMES utf8mb4;

INSERT INTO smtp_settings (id, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure, from_email, from_name, updated_at)
VALUES (1, 'vkitnet.info', 465, 'info@vkitnet.info', '', 'ssl', 'info@vkitnet.info', 'VK IT', CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
  smtp_host = IF(CHAR_LENGTH(TRIM(COALESCE(smtp_host, ''))) = 0, VALUES(smtp_host), smtp_host),
  smtp_port = IF(CHAR_LENGTH(TRIM(COALESCE(smtp_host, ''))) = 0, VALUES(smtp_port), smtp_port),
  smtp_user = IF(CHAR_LENGTH(TRIM(COALESCE(smtp_user, ''))) = 0, VALUES(smtp_user), smtp_user),
  smtp_pass = IF(CHAR_LENGTH(TRIM(COALESCE(smtp_pass, ''))) = 0, VALUES(smtp_pass), smtp_pass),
  smtp_secure = IF(CHAR_LENGTH(TRIM(COALESCE(smtp_host, ''))) = 0, VALUES(smtp_secure), smtp_secure),
  from_email = IF(CHAR_LENGTH(TRIM(COALESCE(from_email, ''))) = 0, VALUES(from_email), from_email),
  from_name = IF(CHAR_LENGTH(TRIM(COALESCE(from_name, ''))) = 0, VALUES(from_name), from_name),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO settings (key_name, value) VALUES
  ('smtp_host', 'vkitnet.info'),
  ('smtp_port', '465'),
  ('smtp_username', 'info@vkitnet.info'),
  ('smtp_secure', 'ssl'),
  ('email_from', 'info@vkitnet.info'),
  ('from_name', 'VK IT')
ON DUPLICATE KEY UPDATE
  value = IF(CHAR_LENGTH(TRIM(COALESCE(value, ''))) = 0, VALUES(value), value);
