-- Create order_items table and update orders structure
USE dogfoodshop;

-- Create order_items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Add order_number and total_amount to orders table if they don't exist
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'dogfoodshop' 
    AND TABLE_NAME = 'orders' 
    AND COLUMN_NAME = 'order_number');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE orders ADD COLUMN order_number VARCHAR(50) UNIQUE NULL AFTER id, ADD COLUMN total_amount DECIMAL(10, 2) NULL AFTER status',
    'SELECT "Columns already exist" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create index for order_number
CREATE INDEX IF NOT EXISTS idx_order_number ON orders(order_number);

