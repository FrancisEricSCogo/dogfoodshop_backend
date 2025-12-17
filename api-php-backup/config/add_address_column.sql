-- Add address column to users table
-- Run this in phpMyAdmin or MySQL command line

USE dogfoodshop;

ALTER TABLE users 
ADD COLUMN IF NOT EXISTS address TEXT NULL AFTER phone;

