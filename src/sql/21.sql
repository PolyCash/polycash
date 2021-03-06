ALTER TABLE `addresses` ADD `primary_blockchain_id` INT NULL DEFAULT NULL AFTER `user_id`;
ALTER TABLE `transaction_game_ios` ADD `coin_blocks_created` BIGINT NULL DEFAULT NULL AFTER `instantly_mature`, ADD `coin_blocks_destroyed` BIGINT NULL DEFAULT NULL AFTER `coin_blocks_created`;
ALTER TABLE `transaction_game_ios` ADD `ref_block_id` INT NULL DEFAULT NULL AFTER `instantly_mature`, ADD `ref_coin_blocks` BIGINT NULL DEFAULT NULL AFTER `ref_block_id`, ADD `ref_coin_rounds` BIGINT NULL DEFAULT NULL AFTER `ref_coin_blocks`;
ALTER TABLE `transaction_game_ios` ADD `ref_round_id` INT NULL DEFAULT NULL AFTER `ref_block_id`;
ALTER TABLE `transaction_game_ios` ADD `is_coinbase` TINYINT(1) NOT NULL DEFAULT '0' AFTER `colored_amount`;