INSERT INTO `images` (`image_id`, `access_key`, `extension`) VALUES
(33, '', 'jpg');
INSERT INTO `entities` (`entity_id`, `entity_type_id`, `default_image_id`, `entity_name`, `first_name`, `last_name`, `electoral_votes`) VALUES
(65, 1, 33, 'Gary Johnson', 'Gary', 'Johnson', 0);
ALTER TABLE `game_types` ADD `event_type_name` VARCHAR(50) NOT NULL DEFAULT '' AFTER `currency_id`;
ALTER TABLE `events` ADD `option_max_width` INT NOT NULL DEFAULT '0' AFTER `completion_datetime`;
ALTER TABLE `event_types` ADD `default_option_max_width` INT NOT NULL DEFAULT '0' AFTER `max_voting_fraction`;
ALTER TABLE `game_types` ADD `default_option_max_width` INT NOT NULL DEFAULT '0' AFTER `default_max_voting_fraction`;
UPDATE game_types SET default_option_max_width=120;
