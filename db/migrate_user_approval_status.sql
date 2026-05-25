-- Run once in phpMyAdmin (or mysql CLI) to support admin approval flow.
-- If a column already exists, skip that line or ignore "Duplicate column" errors.

ALTER TABLE `customers` ADD COLUMN `approval_status` VARCHAR(20) NOT NULL DEFAULT 'Approved' AFTER `last_name`;
ALTER TABLE `sellers` ADD COLUMN `approval_status` VARCHAR(20) NOT NULL DEFAULT 'Approved' AFTER `last_name`;
