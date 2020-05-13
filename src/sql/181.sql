ALTER TABLE `games` ADD `min_buyin_amount` FLOAT NOT NULL DEFAULT '2' AFTER `default_transaction_fee`, ADD `min_sellout_amount` FLOAT NOT NULL DEFAULT '0' AFTER `min_buyin_amount`;
