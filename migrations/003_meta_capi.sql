-- Meta CAPI / browser event dedupe fields on orders (safe re-run)
SET @db := DATABASE();

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'book_orders' AND COLUMN_NAME = 'meta_fbp'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE book_orders ADD COLUMN meta_fbp VARCHAR(255) NULL AFTER mollie_payment_id',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'book_orders' AND COLUMN_NAME = 'meta_fbc'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE book_orders ADD COLUMN meta_fbc VARCHAR(255) NULL AFTER meta_fbp',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'book_orders' AND COLUMN_NAME = 'meta_purchase_sent_at'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE book_orders ADD COLUMN meta_purchase_sent_at DATETIME NULL AFTER meta_fbc',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'book_orders' AND COLUMN_NAME = 'meta_ic_sent_at'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE book_orders ADD COLUMN meta_ic_sent_at DATETIME NULL AFTER meta_purchase_sent_at',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
