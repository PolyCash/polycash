ALTER TABLE `currency_accounts` ADD `join_txos_on_quantity` INT NULL DEFAULT NULL AFTER `faucet_amount_each`, ADD INDEX `join_txos_on_quantity` (`join_txos_on_quantity`);
