ALTER TABLE `events` ADD `searchtext` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `external_identifier`;
ALTER TABLE `game_defined_events` ADD INDEX (`game_id`, `league_entity_id`);
