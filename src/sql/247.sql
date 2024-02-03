ALTER TABLE `peers` ADD `is_public_card_issuer` TINYINT(1) NOT NULL DEFAULT '0' AFTER `base_url`;
ALTER TABLE `peers` ADD INDEX (`is_public_card_issuer`);
