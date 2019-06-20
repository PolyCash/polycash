ALTER TABLE `currency_accounts` CHANGE `is_sale_account` `is_game_sale_account` TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE `currency_accounts` ADD `is_blockchain_sale_account` TINYINT(1) NULL DEFAULT '0' AFTER `is_game_sale_account`;
UPDATE `currencies` SET `oracle_url_id` = '3' WHERE `currency_id` = 6;
UPDATE currencies SET oracle_url_id=NULL WHERE oracle_url_id<3;
ALTER TABLE `currency_invoices` CHANGE `invoice_type` `invoice_type` ENUM('join_buyin','buyin','sale_buyin','') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT '';