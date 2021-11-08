ALTER TABLE `games` ADD `pow_reward_type` ENUM('none','fixed') NOT NULL DEFAULT 'none' AFTER `exponential_inflation_rate`, ADD `pow_fixed_reward` FLOAT NULL DEFAULT NULL AFTER `pow_reward_type`;
ALTER TABLE `games` DROP `event_entity_type_id`;
ALTER TABLE `games` CHANGE `featured` `featured` TINYINT(1) NOT NULL DEFAULT '1';
ALTER TABLE `games` DROP `initial_coins`;
