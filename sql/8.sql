ALTER TABLE `transactions` ADD `has_all_inputs` TINYINT(1) NOT NULL DEFAULT '0' AFTER `taper_factor`, ADD `has_all_outputs` TINYINT(1) NOT NULL DEFAULT '0' AFTER `has_all_inputs`;
