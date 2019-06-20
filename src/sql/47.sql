ALTER TABLE `transaction_game_ios` CHANGE `effectiveness_factor` `effectiveness_factor` DECIMAL(9,8) NULL DEFAULT NULL;
UPDATE game_types SET default_logo_image_id=NULL;