ALTER TABLE `events` ADD `event_winning_rule` enum('max_below_cap','game_definition') NOT NULL DEFAULT 'game_definition' AFTER `option_block_rule`,
  ADD `vote_effectiveness_function` enum('constant','linear_decrease') NOT NULL DEFAULT 'constant' AFTER `event_winning_rule`,
  ADD `effectiveness_param1` decimal(12,8) DEFAULT NULL AFTER `vote_effectiveness_function`,
  ADD `max_voting_fraction` decimal(3,2) DEFAULT NULL AFTER `effectiveness_param1`;

UPDATE events ev JOIN event_types et ON ev.event_type_id=et.event_type_id SET ev.event_winning_rule=et.event_winning_rule, ev.vote_effectiveness_function=et.vote_effectiveness_function, ev.effectiveness_param1=et.effectiveness_param1, ev.max_voting_fraction=et.max_voting_fraction;

ALTER TABLE `games`
  DROP `game_winning_rule`,
  DROP `game_winning_field`,
  DROP `game_winning_inflation`,
  DROP `winning_entity_id`,
  DROP `game_winning_transaction_id`;

ALTER TABLE `game_types`
  DROP `game_winning_rule`,
  DROP `game_winning_field`,
  DROP `default_game_winning_inflation`;

DROP TABLE `event_types`;
