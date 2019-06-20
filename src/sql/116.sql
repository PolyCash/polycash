ALTER TABLE `currency_accounts` ADD `is_escrow_account` TINYINT(1) NOT NULL DEFAULT '0' AFTER `is_blockchain_sale_account`;
UPDATE `currency_accounts` SET is_escrow_account=1 WHERE account_name LIKE 'Escrow account for%';
INSERT INTO `modules` (`module_id`, `module_name`) VALUES (NULL, 'CryptoDuels');