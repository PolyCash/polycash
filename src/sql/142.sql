ALTER TABLE `options` ADD `target_probability` FLOAT NULL DEFAULT NULL AFTER `event_option_index`;
UPDATE options op JOIN events ev ON op.event_id=ev.event_id JOIN game_defined_options gdo ON op.event_option_index=gdo.option_index SET op.target_probability=gdo.target_probability WHERE gdo.event_index=ev.event_index AND gdo.game_id=ev.game_id AND gdo.target_probability IS NOT NULL;
