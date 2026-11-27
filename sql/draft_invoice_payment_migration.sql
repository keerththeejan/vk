-- Additive metadata for invoice advances and payment collection.
-- Existing payment rows remain unchanged; NULL idempotency tokens are allowed.
SET @vk_db = DATABASE();

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@vk_db AND TABLE_NAME='payments' AND COLUMN_NAME='reference_number')=0, 'ALTER TABLE payments ADD COLUMN reference_number VARCHAR(128) NULL', 'SELECT 1'); PREPARE vk_stmt FROM @ddl; EXECUTE vk_stmt; DEALLOCATE PREPARE vk_stmt;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@vk_db AND TABLE_NAME='payments' AND COLUMN_NAME='received_by')=0, 'ALTER TABLE payments ADD COLUMN received_by INT UNSIGNED NULL', 'SELECT 1'); PREPARE vk_stmt FROM @ddl; EXECUTE vk_stmt; DEALLOCATE PREPARE vk_stmt;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@vk_db AND TABLE_NAME='payments' AND COLUMN_NAME='request_token')=0, 'ALTER TABLE payments ADD COLUMN request_token CHAR(48) NULL', 'SELECT 1'); PREPARE vk_stmt FROM @ddl; EXECUTE vk_stmt; DEALLOCATE PREPARE vk_stmt;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@vk_db AND TABLE_NAME='payments' AND INDEX_NAME='uq_payments_request_token')=0, 'ALTER TABLE payments ADD UNIQUE KEY uq_payments_request_token (request_token)', 'SELECT 1'); PREPARE vk_stmt FROM @ddl; EXECUTE vk_stmt; DEALLOCATE PREPARE vk_stmt;
