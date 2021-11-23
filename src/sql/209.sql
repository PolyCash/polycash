ALTER TABLE `transaction_game_ios` DROP `instantly_mature`;
ALTER TABLE `transaction_ios` ADD `is_coinbase` TINYINT NOT NULL DEFAULT '0' AFTER `in_index`, ADD `is_mature` TINYINT NOT NULL DEFAULT '1' AFTER `is_coinbase`;
ALTER TABLE `transaction_ios` ADD INDEX (`blockchain_id`, `is_mature`);
ALTER TABLE `transaction_game_ios` CHANGE `is_coinbase` `is_game_coinbase` TINYINT(1) NOT NULL DEFAULT '0';
