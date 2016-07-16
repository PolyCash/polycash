ALTER TABLE `users` DROP `notification_preference`;
ALTER TABLE `user_games` ADD `notification_preference` ENUM('email','none') NOT NULL DEFAULT 'none' ;
