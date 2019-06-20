ALTER TABLE `user_strategies` ADD `time_last_applied` INT NULL DEFAULT NULL AFTER `min_coins_available`;
ALTER TABLE `featured_strategies` ADD `game_id` INT NULL DEFAULT NULL AFTER `featured_strategy_id`;
ALTER TABLE `featured_strategies` ADD INDEX (`game_id`);
ALTER TABLE `featured_strategies` ADD INDEX (`reference_account_id`);
ALTER TABLE `featured_strategies` ADD `hit_url` TINYINT(1) NOT NULL DEFAULT '0' AFTER `reference_account_id`;