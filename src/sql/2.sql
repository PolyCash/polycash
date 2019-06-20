ALTER TABLE `games` ADD `game_winning_inflation` DECIMAL(12,8) NOT NULL DEFAULT '0' AFTER `game_winning_field`;
ALTER TABLE `game_types` ADD `default_game_winning_inflation` DECIMAL(12,8) NOT NULL DEFAULT '0' AFTER `default_max_voting_fraction`;
UPDATE `game_types` SET `default_game_winning_inflation` = '1.00000000' WHERE `game_type_id` = 1;
ALTER TABLE `games` ADD `winning_entity_id` INT NULL DEFAULT NULL AFTER `game_winning_inflation`;
ALTER TABLE `games` ADD `game_winning_transaction_id` INT NULL DEFAULT NULL AFTER `winning_entity_id`;
