-- Add profile_pic column to otp_verifications table
-- Run this SQL in phpMyAdmin to fix the error

USE dogfoodshop;

ALTER TABLE otp_verifications 
ADD COLUMN profile_pic VARCHAR(255) NULL AFTER password;
