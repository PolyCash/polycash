ALTER TABLE `modules` ADD `primary_game_id` INT NULL DEFAULT NULL AFTER `module_name`;
UPDATE `modules` m JOIN games g ON m.module_name=g.module SET m.primary_game_id=g.game_id;
