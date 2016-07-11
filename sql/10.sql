INSERT INTO `images` (`image_id`, `access_key`, `extension`) VALUES
(30, '', 'jpg');
UPDATE voting_options SET default_image_id=30 WHERE voting_option_id=42;
UPDATE game_voting_options SET image_id=30 WHERE voting_option_id=42;