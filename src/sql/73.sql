ALTER TABLE game_blocks DROP INDEX internal_block_id_2;
ALTER TABLE `game_blocks` ADD UNIQUE (`game_id`, `block_id`);
ALTER TABLE `games` ADD `min_option_index` INT NULL DEFAULT NULL AFTER `coins_in_existence_block`, ADD `max_option_index` INT NULL DEFAULT NULL AFTER `min_option_index`;