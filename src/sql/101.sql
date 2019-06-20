ALTER TABLE `transaction_game_ios` ADD `create_block_id` INT NULL DEFAULT NULL AFTER `instantly_mature`;
ALTER TABLE `transaction_game_ios` CHANGE `original_io_id` `parent_io_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `transaction_game_ios` DROP `payout_game_io_id`;
ALTER TABLE `transaction_game_ios` ADD `payout_io_id` INT NULL DEFAULT NULL AFTER `parent_io_id`;
ALTER TABLE `transaction_game_ios` ADD `is_resolved` TINYINT NOT NULL DEFAULT '0' AFTER `is_coinbase`;