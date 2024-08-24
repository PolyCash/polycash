ALTER TABLE game_definition_migrations ADD COLUMN `missing_game_defs_at` INT(11) NULL DEFAULT NULL;
ALTER TABLE game_definition_migrations ADD INDEX (`missing_game_defs_at`);
