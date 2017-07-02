ALTER TABLE `option_blocks` ADD `rand_prob` FLOAT NULL DEFAULT NULL AFTER `rand_bytes`;
INSERT INTO modules SET module_name='SingleElimination';