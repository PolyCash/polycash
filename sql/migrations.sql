ALTER TABLE `games` ADD `url_identifier` VARCHAR(100) NULL DEFAULT NULL AFTER `creator_game_index`;
ALTER TABLE `cached_rounds` ADD `payout_transaction_id` INT(20) NULL DEFAULT NULL AFTER `payout_block_id`;
ALTER TABLE `cached_rounds` ADD UNIQUE (`payout_transaction_id`);
ALTER TABLE `invitations` ADD `sent_email_id` INT(20) NULL DEFAULT NULL AFTER `time_created`;
ALTER TABLE `user_messages` ADD `game_id` INT(20) NULL DEFAULT NULL AFTER `message_id`;
ALTER TABLE `user_messages` ADD `seen` TINYINT(1) NOT NULL DEFAULT '0' AFTER `message`;
ALTER TABLE `user_strategies` CHANGE `voting_strategy` `voting_strategy` ENUM('manual','by_rank','by_nation','by_plan','api','') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'manual';
ALTER TABLE `strategy_round_allocations` ADD `applied` TINYINT(1) NOT NULL DEFAULT '0' AFTER `points`;
