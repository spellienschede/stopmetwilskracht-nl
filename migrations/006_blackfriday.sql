-- Black Friday 1+1: promo en UTM's op book_orders (veilig opnieuw uitvoerbaar)
SET @db := DATABASE();

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'book_orders' AND COLUMN_NAME = 'promo'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE book_orders ADD COLUMN promo VARCHAR(64) NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'book_orders' AND COLUMN_NAME = 'utm_source'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE book_orders ADD COLUMN utm_source VARCHAR(120) NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'book_orders' AND COLUMN_NAME = 'utm_medium'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE book_orders ADD COLUMN utm_medium VARCHAR(120) NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'book_orders' AND COLUMN_NAME = 'utm_campaign'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE book_orders ADD COLUMN utm_campaign VARCHAR(120) NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'book_orders' AND COLUMN_NAME = 'utm_content'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE book_orders ADD COLUMN utm_content VARCHAR(120) NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'book_orders' AND INDEX_NAME = 'idx_order_promo'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE book_orders ADD INDEX idx_order_promo (promo)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
