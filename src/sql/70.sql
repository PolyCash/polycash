INSERT INTO `images` (`image_id`, `access_key`, `extension`) VALUES (77, '', 'png');
UPDATE `blockchains` SET default_image_id=77 WHERE url_identifier='stakechain';