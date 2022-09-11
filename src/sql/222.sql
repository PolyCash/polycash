ALTER TABLE `currency_accounts` 
ADD COLUMN `faucet_donations_on` TINYINT(1) NOT NULL AFTER `target_balance`,
ADD COLUMN `faucet_target_balance` FLOAT NULL DEFAULT NULL AFTER `faucet_donations_on`,
ADD COLUMN `faucet_amount_each` FLOAT NULL DEFAULT NULL AFTER `faucet_target_balance`;
