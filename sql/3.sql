INSERT INTO `images` (`image_id`, `access_key`, `extension`) VALUES (34, '', 'png'), (35, '', 'png');
ALTER TABLE `game_types` ADD `default_logo_image_id` INT NULL DEFAULT NULL AFTER `default_option_max_width`;
ALTER TABLE `games` ADD `logo_image_id` INT NULL DEFAULT NULL AFTER `invoice_address_id`;
UPDATE game_types SET default_logo_image_id=34;
UPDATE games SET logo_image_id=34;
UPDATE game_types SET coin_name='empirecoin', coin_name_plural='empirecoins', coin_abbreviation='EMP';
