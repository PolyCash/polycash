ALTER TABLE `async_email_deliveries` ADD COLUMN `attachment_content` LONGTEXT NULL DEFAULT NULL AFTER `delivery_key`, ADD COLUMN `attachment_type` VARCHAR(30) NULL DEFAULT NULL AFTER `attachment_content`;
