ALTER TABLE `events` ADD INDEX `event_starting_time_index` (`game_id`, `event_starting_time`);
ALTER TABLE `events` ADD INDEX `event_final_time_index` (`game_id`, `event_final_time`);
ALTER TABLE `events` ADD INDEX `event_payout_time_index` (`game_id`, `event_payout_time`);
ALTER TABLE `transaction_game_ios` ADD INDEX (`is_game_coinbase`);
ALTER TABLE `transaction_game_ios` ADD INDEX (`is_resolved`);
