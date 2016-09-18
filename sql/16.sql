ALTER TABLE `blocks` ADD `num_transactions` INT NULL DEFAULT NULL AFTER `effectiveness_factor`;
ALTER TABLE `transactions` ADD `num_inputs` INT NULL DEFAULT NULL AFTER `ref_coin_rounds_destroyed`, ADD `num_outputs` INT NULL DEFAULT NULL AFTER `num_inputs`;
ALTER TABLE `transactions` ADD `position_in_block` INT NULL DEFAULT NULL AFTER `ref_coin_rounds_destroyed`;
ALTER TABLE `blocks` ADD `load_time` FLOAT NOT NULL DEFAULT '0' AFTER `locally_saved`;
ALTER TABLE `transactions` ADD `load_time` FLOAT NOT NULL DEFAULT '0' AFTER `has_all_outputs`;