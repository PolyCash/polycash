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