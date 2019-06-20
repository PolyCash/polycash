ALTER TABLE `user_strategies` CHANGE `voting_strategy` `voting_strategy` ENUM('manual','by_rank','by_entity','by_plan','api','featured','') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'manual';
ALTER TABLE `user_strategies` ADD `featured_strategy_id` INT NULL DEFAULT NULL AFTER `game_id`;
ALTER TABLE `user_games` CHANGE `show_planned_votes` `show_intro_message` TINYINT(1) NOT NULL DEFAULT '0';

CREATE TABLE `featured_strategies` (
  `featured_strategy_id` int(11) NOT NULL,
  `reference_account_id` int(11) DEFAULT NULL,
  `strategy_name` varchar(255) DEFAULT NULL,
  `base_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `featured_strategies`
  ADD PRIMARY KEY (`featured_strategy_id`);

ALTER TABLE `featured_strategies`
  MODIFY `featured_strategy_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
