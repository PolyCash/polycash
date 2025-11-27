ALTER TABLE `featured_strategies` ADD COLUMN `strategy_params` JSON NULL DEFAULT NULL AFTER `base_url`;
ALTER TABLE `user_strategies` ADD COLUMN `featured_strategy_params` JSON NULL DEFAULT NULL AFTER `time_next_apply`;
ALTER TABLE `featured_strategies` ADD COLUMN `strategy_class` VARCHAR(100) NULL DEFAULT NULL AFTER `strategy_params`;
