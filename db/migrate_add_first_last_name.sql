-- Run once in phpMyAdmin (or mysql CLI) if you get:
--   #1054 - Unknown column 'first_name' in 'INSERT INTO'
-- If a column already exists, skip that line or ignore "Duplicate column" errors.

ALTER TABLE `customers` ADD COLUMN `first_name` VARCHAR(100) NULL DEFAULT NULL AFTER `name`;
ALTER TABLE `customers` ADD COLUMN `last_name` VARCHAR(100) NULL DEFAULT NULL AFTER `first_name`;

ALTER TABLE `admins` ADD COLUMN `first_name` VARCHAR(100) NULL DEFAULT NULL AFTER `name`;
ALTER TABLE `admins` ADD COLUMN `last_name` VARCHAR(100) NULL DEFAULT NULL AFTER `first_name`;

ALTER TABLE `sellers` ADD COLUMN `first_name` VARCHAR(100) NULL DEFAULT NULL AFTER `name`;
ALTER TABLE `sellers` ADD COLUMN `last_name` VARCHAR(100) NULL DEFAULT NULL AFTER `first_name`;
