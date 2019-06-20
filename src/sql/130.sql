INSERT INTO `modules` (`module_id`, `module_name`, `primary_game_id`) VALUES (NULL, 'eSports', NULL);
ALTER TABLE `game_defined_events` DROP `espn_event_uid`;
ALTER TABLE `game_defined_events` ADD `external_identifier` VARCHAR(255) NULL DEFAULT NULL AFTER `outcome_index`;
