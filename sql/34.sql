ALTER TABLE `games` CHANGE `event_rule` `event_rule` ENUM('entity_type_option_group','single_event_series','all_pairs','game_definition') CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;
ALTER TABLE `events` ADD `event_payout_block` INT NULL DEFAULT NULL AFTER `event_final_block`;
UPDATE events SET event_payout_block=event_final_block;
ALTER TABLE `games` ADD `default_payout_block_delay` INT NULL DEFAULT NULL AFTER `default_option_max_width`;
UPDATE games SET default_payout_block_delay=0;
ALTER TABLE `event_types` CHANGE `max_voting_fraction` `max_voting_fraction` DECIMAL(3,2) NOT NULL DEFAULT '0.25';
ALTER TABLE `event_types` ADD `event_winning_rule` ENUM('max_below_cap','game_definition') NOT NULL DEFAULT 'max_below_cap' AFTER `url_identifier`;
ALTER TABLE `options` DROP `last_win_round`;

CREATE TABLE `game_defined_events` (
  `game_defined_event_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `event_index` int(11) DEFAULT NULL,
  `event_starting_block` int(11) DEFAULT NULL,
  `event_final_block` int(11) DEFAULT NULL,
  `event_payout_block` int(11) DEFAULT NULL,
  `event_name` varchar(255) DEFAULT NULL,
  `option_name` varchar(255) DEFAULT NULL,
  `option_name_plural` varchar(255) DEFAULT NULL,
  `outcome_index` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `game_defined_options` (
  `game_defined_option_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `event_index` int(11) DEFAULT NULL,
  `option_index` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `game_definitions` (
  `game_definition_id` int(11) NOT NULL,
  `definition_hash` varchar(100) DEFAULT NULL,
  `definition` longtext
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `game_defined_events`
  ADD PRIMARY KEY (`game_defined_event_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `event_index` (`event_index`);

ALTER TABLE `game_defined_options`
  ADD PRIMARY KEY (`game_defined_option_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `event_index` (`event_index`),
  ADD KEY `option_index` (`option_index`);

ALTER TABLE `game_definitions`
  ADD PRIMARY KEY (`game_definition_id`),
  ADD KEY `definition_hash` (`definition_hash`);

ALTER TABLE `game_defined_events`
  MODIFY `game_defined_event_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `game_defined_options`
  MODIFY `game_defined_option_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `game_definitions`
  MODIFY `game_definition_id` int(11) NOT NULL AUTO_INCREMENT;