ALTER TABLE `games` CHANGE `pow_reward_type` `pow_reward_type` ENUM('none','fixed','pegged_to_supply') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'none';
ALTER TABLE `games` ADD `blocks_per_pow_reward_ajustment` INT NULL DEFAULT NULL AFTER `initial_pow_reward`;
ALTER TABLE `games` ADD `current_pow_reward` FLOAT NULL DEFAULT NULL AFTER `blocks_per_pow_reward_ajustment`;
