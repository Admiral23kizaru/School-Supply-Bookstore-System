-- Add profile image URL for admin avatars (run once if column is missing).

ALTER TABLE `admins` ADD COLUMN `profile_image_url` VARCHAR(255) NULL DEFAULT NULL AFTER `email`;
