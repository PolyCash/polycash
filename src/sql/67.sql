CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `parent_category_id` int(11) DEFAULT NULL,
  `url_identifier` varchar(100) DEFAULT NULL,
  `category_name` varchar(255) DEFAULT NULL,
  `category_level` int(11) DEFAULT NULL,
  `display_rank` float NOT NULL DEFAULT '0',
  `icon_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `categories` (`category_id`, `parent_category_id`, `url_identifier`, `category_name`, `category_level`, `display_rank`, `icon_name`) VALUES
(1, NULL, 'virtual-sports', 'Virtual Sports', 0, 3, 'trophy'),
(2, NULL, 'sports', 'Sports Betting', 0, 1, 'soccer-ball-o'),
(3, NULL, 'esports', 'eSports', 0, 2, 'gamepad'),
(4, NULL, 'assets', 'Financial Assets', 0, 4, 'line-chart'),
(5, 3, 'call-of-duty', 'Call of Duty', 1, 1, NULL),
(6, 3, 'counterstrike', 'Counter-Strike', 1, 2, NULL),
(7, 3, 'crossfire', 'Crossfire', 1, 3, NULL),
(8, 3, 'dota-2', 'Dota 2', 1, 4, NULL),
(9, 3, 'halo', 'Halo', 1, 5, NULL),
(10, 3, 'hearthstone', 'Hearthstone', 1, 6, NULL),
(11, 3, 'lol', 'League of Legends', 1, 7, NULL),
(12, 3, 'overwatch', 'Overwatch', 1, 8, NULL),
(13, 3, 'quake-champions', 'Quake Champions', 1, 9, NULL),
(14, 3, 'rocket-league', 'Rocket League', 1, 10, NULL),
(15, 3, 'smite', 'Smite', 1, 11, NULL),
(16, 3, 'starcraft-2', 'StarCraft II', 1, 12, NULL),
(17, 3, 'vainglory', 'Vainglory', 1, 13, NULL),
(18, 3, 'world-of-tanks', 'World of Tanks', 1, 14, NULL),
(19, 2, 'baseball', 'Baseball', 1, 1, NULL),
(20, 2, 'basketball', 'Basketball', 1, 2, NULL),
(21, 2, 'boxing', 'Boxing', 1, 3, NULL),
(22, 2, 'cricket', 'Cricket', 1, 4, NULL),
(23, 2, 'football', 'Football', 1, 5, NULL),
(24, 2, 'golf', 'Golf', 1, 6, NULL),
(25, 2, 'horse-racing', 'Horse Racing', 1, 7, NULL),
(26, 2, 'ice-hockey', 'Ice Hockey', 1, 8, NULL),
(27, 2, 'mma', 'Mixed Martial Arts', 1, 9, NULL),
(28, 2, 'motor-sports', 'Motor Sports', 1, 10, NULL),
(29, 2, 'soccer', 'Soccer', 1, 11, NULL),
(30, 2, 'tennis', 'Tennis', 1, 12, NULL),
(31, NULL, 'strategy-games', 'Strategy Games', 0, 0, 'flag');

ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `url_identifier` (`url_identifier`),
  ADD KEY `parent_category_id` (`parent_category_id`),
  ADD KEY `display_rank` (`display_rank`);

ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT;