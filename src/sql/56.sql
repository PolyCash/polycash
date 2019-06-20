ALTER TABLE `users` DROP `api_access_code`;
ALTER TABLE `user_games` ADD `api_access_code` VARCHAR(50) NULL DEFAULT NULL AFTER `notification_preference`;