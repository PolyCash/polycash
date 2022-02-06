ALTER TABLE `user_games` ADD `latest_speedup_reminder_time` INT NULL DEFAULT NULL AFTER `latest_event_reminder_time`;
ALTER TABLE `user_games` ADD `auto_stake_key` VARCHAR(50) NULL DEFAULT NULL AFTER `latest_speedup_reminder_time`;
ALTER TABLE `user_games` ADD `auto_stake_tx_hash` VARCHAR(64) NULL DEFAULT NULL AFTER `auto_stake_key`;
ALTER TABLE `games` ADD `auto_stake_featured_strategy_id` INT NULL DEFAULT NULL AFTER `wallet_promo_text`;
