ALTER TABLE `currency_accounts` ADD COLUMN `backups_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `faucet_amount_each`;
