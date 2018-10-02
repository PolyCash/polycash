ALTER TABLE `game_escrow_accounts` DROP `account_id`;
ALTER TABLE `game_escrow_accounts` DROP `time_created`;
ALTER TABLE `game_escrow_accounts` ADD `amount` DOUBLE NULL DEFAULT NULL AFTER `game_id`;
RENAME TABLE `game_escrow_accounts` TO `game_escrow_amounts`;
ALTER TABLE `game_escrow_amounts` ADD `currency_id` INT NULL DEFAULT NULL AFTER `game_id`;
ALTER TABLE `game_escrow_amounts` ADD INDEX (`currency_id`);
ALTER TABLE `game_escrow_amounts` ADD UNIQUE (`game_id`, `currency_id`);

CREATE TABLE `game_defined_escrow_amounts` (
  `escrow_amount_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `amount` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `game_defined_escrow_amounts`
  ADD PRIMARY KEY (`escrow_amount_id`),
  ADD UNIQUE KEY `game_id_2` (`game_id`,`currency_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `currency_id` (`currency_id`);

ALTER TABLE `game_defined_escrow_amounts`
  MODIFY `escrow_amount_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
