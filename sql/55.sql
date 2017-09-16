ALTER TABLE `user_games` ADD `selected` TINYINT(1) NOT NULL DEFAULT '0' AFTER `account_value`;
ALTER TABLE user_games DROP INDEX user_id;
ALTER TABLE `user_games` ADD INDEX (`user_id`, `game_id`);