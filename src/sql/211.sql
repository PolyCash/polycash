ALTER TABLE `games` CHANGE `default_buyin_currency_id` `default_buyin_currency_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `blockchains` ADD `abbreviation` VARCHAR(20) NULL DEFAULT NULL AFTER `coin_name_plural`;
