ALTER TABLE options ADD COLUMN `op_event_index` INT(11) DEFAULT NULL AFTER `vote_identifier`;
ALTER TABLE options ADD COLUMN `game_id` INT(11) DEFAULT NULL AFTER `event_id`;
UPDATE options op JOIN events ev ON op.event_id=ev.event_id SET op.op_event_index=ev.event_index, op.game_id=ev.game_id;
ALTER TABLE options ADD UNIQUE (`game_id`, `op_event_index`, `event_option_index`);
