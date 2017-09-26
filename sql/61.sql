ALTER TABLE `blocks` ADD `time_mined` INT NULL DEFAULT NULL AFTER `time_loaded`;
ALTER TABLE `option_blocks` ADD UNIQUE (`option_id`, `block_height`);