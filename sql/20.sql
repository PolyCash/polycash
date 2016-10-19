RENAME TABLE `currency_addresses` TO `address_keys`;
ALTER TABLE `address_keys` CHANGE `currency_address_id` `address_key_id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `address_keys` ADD `address_id` INT NULL DEFAULT NULL AFTER `address_key_id`;
ALTER TABLE `address_keys` ADD INDEX (`account_id`);
ALTER TABLE `address_keys` ADD UNIQUE INDEX (`address_id`);
ALTER TABLE `currency_invoices` CHANGE `currency_address_id` `address_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `currency_accounts` ADD PRIMARY KEY(`account_id`);
ALTER TABLE `currency_accounts` CHANGE `account_id` `account_id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `user_games` CHANGE `buyin_currency_address_id` `buyin_address_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `blockchains`
  DROP `identifier_case_sensitive`,
  DROP `identifier_first_char`;
UPDATE `game_types` SET buyin_policy='unlimited';
ALTER TABLE `currency_invoices`
  DROP `game_id`,
  DROP `user_id`,
  DROP `settle_currency_id`,
  DROP `pay_price_id`,
  DROP `settle_price_id`,
  DROP `settle_amount`,
  DROP `time_seen`;
ALTER TABLE `currency_invoices` ADD INDEX (`address_id`);
ALTER TABLE `currency_invoices` ADD `user_game_id` INT NULL DEFAULT NULL AFTER `address_id`;
ALTER TABLE `currency_invoices` ADD INDEX (`user_game_id`);
ALTER TABLE `currency_invoices` ADD INDEX (`pay_currency_id`);
ALTER TABLE `currency_invoices` ADD `invoice_type` ENUM('join_buyin','buyin','') NOT NULL DEFAULT '' AFTER `user_game_id`;
ALTER TABLE `currency_invoices` ADD `buyin_amount` DECIMAL(16,8) NULL DEFAULT NULL AFTER `invoice_key_string`, ADD `color_amount` DECIMAL(16,8) NULL DEFAULT NULL AFTER `buyin_amount`;
ALTER TABLE `currency_accounts` ADD `game_id` INT NULL DEFAULT NULL AFTER `user_id`;
ALTER TABLE `currency_accounts` ADD INDEX (`currency_id`);
ALTER TABLE `currency_accounts` ADD INDEX (`user_id`);
ALTER TABLE `currency_accounts` ADD INDEX (`game_id`);
ALTER TABLE `currency_accounts` ADD INDEX (`current_address_id`);
ALTER TABLE `address_keys` ADD `save_method` ENUM('db','wallet.dat') NOT NULL DEFAULT 'db' AFTER `account_id`;
ALTER TABLE `games` ADD `events_until_block` INT NULL DEFAULT NULL AFTER `sync_coind_by_cron`;