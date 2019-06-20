ALTER TABLE `games` CHANGE `game_starting_block` `game_starting_block` INT(11) NOT NULL DEFAULT '1';
ALTER TABLE `games` CHANGE `giveaway_status` `giveaway_status` ENUM('public_free','invite_free','invite_pay','public_pay') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'public_free';
ALTER TABLE `games` CHANGE `events_per_round` `events_per_round` INT(11) NOT NULL DEFAULT '1';