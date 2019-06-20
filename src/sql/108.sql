ALTER TABLE `currency_invoices` CHANGE `invoice_type` `invoice_type` ENUM('join_buyin','buyin','sale_buyin','sellout','') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT '';
ALTER TABLE `currency_invoices` ADD `receive_address_id` INT NULL DEFAULT NULL AFTER `address_id`;
ALTER TABLE `currency_invoices` ADD INDEX (`receive_address_id`);
ALTER TABLE `currency_invoices` ADD `fee_amount` DECIMAL(16,8) NOT NULL DEFAULT '0' AFTER `pay_amount`;
ALTER TABLE `games` CHANGE `game_status` `game_status` ENUM('editable','published','running','completed','deleted') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'editable';