ALTER TABLE `game_types` ADD `payout_taper_function` ENUM('constant', 'linear_decrease') NOT NULL DEFAULT 'constant' ;
UPDATE game_types SET payout_taper_function='linear_decrease';
INSERT INTO `images` (`image_id`, `access_key`, `extension`) VALUES
(31, '', 'png'),
(32, '', 'png');
INSERT INTO `voting_option_groups` (`option_group_id`, `option_name`, `option_name_plural`, `description`) VALUES (NULL, 'team', 'teams', 'Red & Blue teams');
INSERT INTO `empirecoin`.`voting_options` (`voting_option_id`, `option_group_id`, `name`, `voting_character`, `default_image_id`) VALUES
(NULL, '6', 'Blue Team', '1', '31'),
(NULL, '6', 'Red Team', '2', '32');
ALTER TABLE `games` ADD `featured_score` FLOAT NOT NULL DEFAULT '0' AFTER `featured`;