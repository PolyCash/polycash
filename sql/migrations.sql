ALTER TABLE `games` ADD `invoice_address_id` INT(11) NULL DEFAULT NULL AFTER `option_group_id`;
ALTER TABLE `games` ADD `completion_datetime` DATETIME NULL DEFAULT NULL AFTER `start_datetime`;
ALTER TABLE `games` ADD `payout_reminder_datetime` DATETIME NULL DEFAULT NULL AFTER `completion_datetime`;
ALTER TABLE `games` ADD `payout_complete` TINYINT(1) NOT NULL DEFAULT '0' AFTER `payout_reminder_datetime`;
ALTER TABLE `users` ADD `bitcoin_address_id` INT(11) NULL DEFAULT NULL AFTER `game_id`;
ALTER TABLE `user_games` ADD `bitcoin_address_id` INT(11) NULL DEFAULT NULL ;
