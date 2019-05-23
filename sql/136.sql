ALTER TABLE `games` ADD `definitive_game_peer_id` INT NULL DEFAULT NULL AFTER `category_id`;

CREATE TABLE `game_peers` (
  `game_peer_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `peer_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `game_peers`
  ADD PRIMARY KEY (`game_peer_id`),
  ADD UNIQUE KEY `game_id` (`game_id`,`peer_id`);

ALTER TABLE `game_peers`
  MODIFY `game_peer_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
