ALTER TABLE `entities` ADD `image_url` VARCHAR(255) NULL DEFAULT NULL AFTER `electoral_votes`;
ALTER TABLE `entities` ADD `content_url` VARCHAR(255) NULL DEFAULT NULL AFTER `image_url`;
ALTER TABLE `games` ADD `view_mode` ENUM('default','simple') NOT NULL DEFAULT 'default' AFTER `default_payout_block_delay`;
ALTER TABLE `user_games` ADD `event_index` INT NOT NULL DEFAULT '0' AFTER `faucet_claims`;
ALTER TABLE `games` ADD `event_winning_rule` VARCHAR(100) NULL DEFAULT NULL AFTER `event_rule`;