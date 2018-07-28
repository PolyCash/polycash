DROP TABLE `event_outcome_options`;
DROP TABLE `event_outcomes`;

ALTER TABLE `events`
  ADD `sum_score` bigint(20) DEFAULT NULL,
  ADD `destroy_score` bigint(20) NOT NULL DEFAULT '0',
  ADD `sum_votes` bigint(20) NOT NULL DEFAULT '0',
  ADD `effective_destroy_score` bigint(20) NOT NULL DEFAULT '0',
  ADD `winning_option_id` int(20) DEFAULT NULL,
  ADD `winning_votes` bigint(20) NOT NULL DEFAULT '0',
  ADD `winning_effective_destroy_score` bigint(20) NOT NULL DEFAULT '0',
  ADD `payout_transaction_id` int(20) DEFAULT NULL;

ALTER TABLE `events`
  ADD UNIQUE (`payout_transaction_id`),
  ADD UNIQUE (`winning_option_id`);
