ALTER TABLE `event_types` ADD `game_id` INT NULL DEFAULT NULL AFTER `event_type_id`;
ALTER TABLE `event_types` ADD `block_repetition_length` INT NULL DEFAULT NULL AFTER `vote_effectiveness_function`;
ALTER TABLE `game_types` CHANGE `event_rule` `event_rule` ENUM('entity_type_option_group','single_event_series') CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;
INSERT INTO `entity_types` (`entity_type_id`, `entity_name`) VALUES (NULL, 'nations in the world');
INSERT INTO `entities` (`entity_id`, `entity_type_id`, `default_image_id`, `entity_name`, `first_name`, `last_name`, `electoral_votes`) VALUES
(66, 4, 1, 'China', '', '', 0),
(67, 4, 2, 'United States', '', '', 0),
(68, 4, 3, 'India', '', '', 0),
(69, 4, 4, 'Brazil', '', '', 0),
(70, 4, 5, 'Indonesia', '', '', 0),
(71, 4, 6, 'Japan', '', '', 0),
(72, 4, 7, 'Russia', '', '', 0),
(73, 4, 8, 'Germany', '', '', 0),
(74, 4, 9, 'Mexico', '', '', 0),
(75, 4, 10, 'Nigeria', '', '', 0),
(76, 4, 11, 'France', '', '', 0),
(77, 4, 12, 'United Kingdom', '', '', 0),
(78, 4, 13, 'Pakistan', '', '', 0),
(79, 4, 14, 'Italy', '', '', 0),
(80, 4, 15, 'Turkey', '', '', 0),
(81, 4, 16, 'Iran', '', '', 0);
INSERT INTO `game_types` (`game_type_id`, `event_rule`, `event_entity_type_id`, `option_group_id`, `currency_id`, `event_type_name`, `events_per_round`, `target_open_games`, `featured`, `game_type`, `game_winning_rule`, `game_winning_field`, `url_identifier`, `name`, `short_description`, `inflation`, `exponential_inflation_rate`, `exponential_inflation_minershare`, `pos_reward`, `pow_reward`, `round_length`, `seconds_per_block`, `maturity`, `payout_weight`, `game_starting_block`, `final_round`, `coin_name`, `coin_name_plural`, `coin_abbreviation`, `start_condition`, `start_condition_players`, `block_timing`, `buyin_policy`, `per_user_buyin_cap`, `game_buyin_cap`, `rpc_port`, `rpc_username`, `rpc_password`, `giveaway_status`, `giveaway_amount`, `public_unclaimed_invitations`, `invite_cost`, `invite_currency`, `invitation_link`, `always_generate_coins`, `min_unallocated_addresses`, `sync_coind_by_cron`, `send_round_notifications`, `default_vote_effectiveness_function`, `default_max_voting_fraction`, `default_game_winning_inflation`, `default_option_max_width`, `default_logo_image_id`) VALUES
(2, 'single_event_series', NULL, 5, NULL, 'EmpireCoin Classic', 1, 1, 1, 'simulation', 'none', '', 'empirecoin-classic', 'EmpireCoin Classic', '', 'linear', '0.00000000', '0.00000000', 75000000000, 2500000000, 10, 20, 0, 'coin', 1, 20, 'empirecoin', 'empirecoins', 'EMP', 'players_joined', 2, 'realistic', 'none', '0.00000000', '0.00000000', NULL, NULL, NULL, 'public_free', 80000000000, 0, '0.00000000', NULL, '', 0, 2, 0, 1, 'constant', '0.25000000', '0.00000000', 200, 34);