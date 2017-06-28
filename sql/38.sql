ALTER TABLE `events` ADD `option_block_rule` VARCHAR(100) NULL DEFAULT NULL AFTER `event_payout_block`;
ALTER TABLE `game_defined_events` ADD `option_block_rule` VARCHAR(100) NULL DEFAULT NULL AFTER `event_payout_block`;
ALTER TABLE `game_defined_options` ADD `entity_id` INT NULL DEFAULT NULL AFTER `game_id`;

CREATE TABLE `option_blocks` (
  `option_block_id` int(11) NOT NULL,
  `option_id` int(11) DEFAULT NULL,
  `block_height` int(11) DEFAULT NULL,
  `score` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `option_blocks`
  ADD PRIMARY KEY (`option_block_id`),
  ADD KEY `option_id` (`option_id`),
  ADD KEY `block_height` (`block_height`);

ALTER TABLE `option_blocks`
  MODIFY `option_block_id` int(11) NOT NULL AUTO_INCREMENT;