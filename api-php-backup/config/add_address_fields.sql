-- Add postal_code and city columns to users table if they don't exist
USE dogfoodshop;

-- Check and add postal_code column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'dogfoodshop' 
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'postal_code');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE users ADD COLUMN postal_code VARCHAR(20) NULL AFTER address',
    'SELECT "Column postal_code already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add city column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'dogfoodshop' 
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'city');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE users ADD COLUMN city VARCHAR(100) NULL AFTER postal_code',
    'SELECT "Column city already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

