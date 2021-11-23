ALTER TABLE `games` ADD `save_every_definition` TINYINT NOT NULL DEFAULT '1' AFTER `cached_definition_time`;

CREATE TABLE `game_definition_migrations` (
  `migration_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `migration_time` int(11) DEFAULT NULL,
  `migration_type` varchar(100) DEFAULT NULL,
  `internal_params` tinyint(4) DEFAULT NULL,
  `from_hash` varchar(64) DEFAULT NULL,
  `to_hash` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `game_definition_migrations`
  ADD PRIMARY KEY (`migration_id`);

ALTER TABLE `game_definition_migrations`
  MODIFY `migration_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
