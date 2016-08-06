INSERT INTO `images` (`image_id`, `access_key`, `extension`) VALUES
(33, '', '');
INSERT INTO `entities` (`entity_id`, `entity_type_id`, `default_image_id`, `entity_name`, `first_name`, `last_name`, `electoral_votes`) VALUES
(65, 1, 33, 'Gary Johnson', 'Gary', 'Johnson', 0);
INSERT INTO `option_groups` (`group_id`, `option_name`, `option_name_plural`, `description`) VALUES
(4, 'candidate', 'candidates', 'nominees for president in 2016 from the top three parties');
INSERT INTO `option_group_memberships` (`membership_id`, `option_group_id`, `entity_id`) VALUES
(8, 4, 2),
(9, 4, 3),
(10, 4, 65);
ALTER TABLE `game_types` ADD `event_type_name` VARCHAR(50) NOT NULL DEFAULT '' AFTER `currency_id`;
ALTER TABLE `events` ADD `option_max_width` INT NOT NULL DEFAULT '0' AFTER `completion_datetime`;
ALTER TABLE `event_types` ADD `default_option_max_width` INT NOT NULL DEFAULT '0' AFTER `max_voting_fraction`;
ALTER TABLE `game_types` ADD `default_option_max_width` INT NOT NULL DEFAULT '0' AFTER `default_max_voting_fraction`;
UPDATE game_types SET default_option_max_width=120;
