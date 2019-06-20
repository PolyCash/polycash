ALTER TABLE `games` ADD `cached_definition_hash` VARCHAR(64) NULL DEFAULT NULL AFTER `module`;
ALTER TABLE `games` ADD `cached_definition_time` INT(20) NULL DEFAULT NULL AFTER `cached_definition_hash`;
