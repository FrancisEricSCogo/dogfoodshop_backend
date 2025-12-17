-- Fix OTP Table - Add missing profile_pic column
-- Run this SQL in phpMyAdmin to fix the database

USE dogfoodshop;

-- Check if column exists, if not add it
SET @dbname = DATABASE();
SET @tablename = "otp_verifications";
SET @columnname = "profile_pic";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column already exists.' AS Result;",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " VARCHAR(255) NULL AFTER password;")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Or simply run this if the above doesn't work:
-- ALTER TABLE otp_verifications ADD COLUMN profile_pic VARCHAR(255) NULL AFTER password;

