ALTER TABLE `game_definitions` ADD `last_accessed_at` INT NULL DEFAULT NULL AFTER `definition`;
UPDATE game_definitions SET last_accessed_at=UNIX_TIMESTAMP(NOW());
ALTER TABLE `games` ADD `recommended_keep_definitions_hours` INT NULL DEFAULT NULL AFTER `save_every_definition`;
ALTER TABLE `games` ADD `keep_definitions_hours` FLOAT NULL DEFAULT NULL AFTER `recommended_keep_definitions_hours`;
UPDATE games SET recommended_keep_definitions_hours=48, keep_definitions_hours=48 WHERE module='Forex128';
ALTER TABLE `game_definitions` ADD `game_id` INT NULL DEFAULT NULL AFTER `game_definition_id`;
ALTER TABLE `game_definitions` ADD INDEX (`game_id`);
