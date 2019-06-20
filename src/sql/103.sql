ALTER TABLE `blocks` ADD `sec_since_prev_block` INT NULL DEFAULT NULL AFTER `load_time`;
ALTER TABLE `blockchains` ADD `average_seconds_per_block` FLOAT NULL DEFAULT NULL AFTER `seconds_per_block`;