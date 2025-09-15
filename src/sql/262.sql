ALTER TABLE `games` ADD COLUMN `boost_votes_by_missing_out_votes` TINYINT(1) NULL DEFAULT 0 AFTER `num_buffer_address_sets`;
