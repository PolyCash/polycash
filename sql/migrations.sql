ALTER TABLE `games` ADD `buyins_allowed` TINYINT(1) NOT NULL DEFAULT '0' AFTER `seconds_per_block`;
ALTER TABLE `game_types` ADD `buyins_allowed` TINYINT(1) NOT NULL DEFAULT '0' AFTER `payout_weight`;
ALTER TABLE `games` ADD `option_name` VARCHAR(100) NOT NULL DEFAULT '' , ADD `option_name_plural` VARCHAR(100) NOT NULL DEFAULT '' ;
UPDATE games g JOIN voting_option_groups og ON g.option_group_id=og.option_group_id SET g.option_name=og.option_name, g.option_name_plural=og.option_name_plural;
