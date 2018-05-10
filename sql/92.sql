ALTER TABLE `users` DROP `alias_preference`;
ALTER TABLE `users` DROP `alias`;
ALTER TABLE `user_games` ADD `prompt_notification_preference` TINYINT(1) NOT NULL DEFAULT '0' AFTER `show_intro_message`;