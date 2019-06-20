ALTER TABLE `user_strategies` ADD `time_next_apply` INT NULL DEFAULT NULL AFTER `time_last_applied`;
ALTER TABLE `user_strategies` DROP `time_last_applied`;