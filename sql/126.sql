ALTER TABLE `addresses` ADD `address_set_id` INT NULL DEFAULT NULL AFTER `primary_blockchain_id`;
ALTER TABLE `addresses` ADD UNIQUE (`address_set_id`, `option_index`);
ALTER TABLE `addresses` ADD INDEX (`address_set_id`);

CREATE TABLE `address_sets` (
  `address_set_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `applied` tinyint(1) NOT NULL DEFAULT '0',
  `has_option_indices_until` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `address_sets`
  ADD PRIMARY KEY (`address_set_id`),
  ADD KEY `game_id` (`game_id`,`applied`);

ALTER TABLE `address_sets`
  MODIFY `address_set_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
