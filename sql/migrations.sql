ALTER TABLE `user_strategies` ADD `transaction_fee` BIGINT(20) NOT NULL DEFAULT '100000' AFTER `game_id`;
ALTER TABLE `transaction_IOs` CHANGE `spend_status` `spend_status` ENUM('spent','unspent','unconfirmed') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'unconfirmed';
ALTER TABLE `games` ADD `giveaway_status` ENUM('on','off','invite_only') NOT NULL DEFAULT 'off' AFTER `maturity`, ADD `giveaway_amount` BIGINT(16) NOT NULL DEFAULT '0' AFTER `giveaway_status`;
ALTER TABLE `transaction_IOs` ADD `coin_blocks_created` BIGINT(20) NULL DEFAULT NULL AFTER `amount`;
ALTER TABLE `games` ADD `pow_reward` BIGINT(20) NOT NULL DEFAULT '0' AFTER `maturity`, ADD `pos_reward` BIGINT(20) NOT NULL DEFAULT '0' AFTER `pow_reward`;
ALTER TABLE `webwallet_transactions` ADD `ref_block_id` BIGINT(20) NULL DEFAULT NULL AFTER `bet_round_id`, ADD `ref_coin_blocks_destroyed` BIGINT(20) NOT NULL DEFAULT '0' AFTER `reference_block_id`;
ALTER TABLE `games` ADD `game_status` ENUM('unstarted','running','paused') NOT NULL DEFAULT 'unstarted' AFTER `game_type`;
