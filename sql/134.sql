ALTER TABLE `games` CHANGE `start_condition` `start_condition` ENUM('fixed_block','fixed_time','players_joined') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'fixed_block';
UPDATE games SET start_condition='fixed_block' WHERE game_starting_block IS NOT NULL;
