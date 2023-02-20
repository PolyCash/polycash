ALTER TABLE `currency_accounts` ADD COLUMN `backups_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `faucet_amount_each`;
ALTER TABLE `address_keys` ADD COLUMN `backed_up_at` DATETIME NULL DEFAULT NULL AFTER `priv_key`;
ALTER TABLE `address_keys` ADD INDEX `backed_up_at` (`backed_up_at` ASC);
ALTER TABLE `address_keys` ADD COLUMN `exported_backup_at` DATETIME NULL DEFAULT NULL AFTER `backed_up_at`;
ALTER TABLE `address_keys` ADD INDEX `exported_backup_at` (`exported_backup_at` ASC);
