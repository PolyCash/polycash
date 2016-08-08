INSERT INTO `images` (`image_id`, `access_key`, `extension`) VALUES (34, '', 'png'), (35, '', 'png');
ALTER TABLE `game_types` ADD `default_logo_image_id` INT NULL DEFAULT NULL AFTER `default_option_max_width`;
ALTER TABLE `games` ADD `logo_image_id` INT NULL DEFAULT NULL AFTER `invoice_address_id`;
UPDATE game_types SET default_logo_image_id=34;
UPDATE games SET logo_image_id=34;
UPDATE game_types SET coin_name='empirecoin', coin_name_plural='empirecoins', coin_abbreviation='EMP';
RENAME TABLE `user_strategy_options` TO `user_strategy_entities`;
ALTER TABLE `user_strategy_entities` CHANGE `option_id` `entity_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `user_strategies` CHANGE `voting_strategy` `voting_strategy` ENUM('manual','by_rank','by_entity','by_plan','api','') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'manual';
