ALTER TABLE `blockchains` ADD `decimal_places` INT NOT NULL DEFAULT '8' AFTER `seconds_per_block`;
ALTER TABLE `games` ADD `decimal_places` INT NOT NULL DEFAULT '8' AFTER `coin_abbreviation`;
ALTER TABLE `user_strategies` CHANGE `transaction_fee` `transaction_fee` DECIMAL(16,10) NOT NULL DEFAULT '0.001';