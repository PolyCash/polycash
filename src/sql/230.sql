DROP TABLE `currency_account_backups`;
CREATE TABLE `backup_address_exports` (
  `export_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL DEFAULT NULL,
  `ip_address` VARCHAR(40) NULL DEFAULT NULL,
  `exported_at` INT NULL DEFAULT NULL,
  `extra_info` MEDIUMTEXT NULL DEFAULT NULL,
  PRIMARY KEY (`export_id`),
  INDEX (`user_id`),
  INDEX (`exported_at`)
);
