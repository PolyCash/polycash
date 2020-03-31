ALTER TABLE `user_games` ADD `latest_event_reminder_time` INT(20) NULL DEFAULT NULL AFTER `buyin_currency_id`;
ALTER TABLE `user_games` ADD `created_at` INT(20) NULL DEFAULT NULL AFTER `latest_event_reminder_time`;
UPDATE user_games SET created_at=UNIX_TIMESTAMP();
ALTER TABLE `user_games` DROP INDEX `user_id`;
ALTER TABLE `user_games` ADD INDEX (`user_id`, `game_id`, `created_at`);
ALTER TABLE `user_games` ADD `latest_claim_time` INT(20) NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `user_games` ADD INDEX (`user_id`, `game_id`, `latest_claim_time`);
