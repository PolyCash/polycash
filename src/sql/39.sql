CREATE TABLE `cached_urls` (
  `cached_url_id` int(11) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `cached_result` longtext,
  `time_created` int(11) DEFAULT NULL,
  `time_fetched` int(11) DEFAULT NULL,
  `load_time` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `cached_urls`
  ADD PRIMARY KEY (`cached_url_id`),
  ADD UNIQUE KEY `url` (`url`);

ALTER TABLE `cached_urls`
  MODIFY `cached_url_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `event_outcome_options` CHANGE `score` `data_value` VARCHAR(100) NULL DEFAULT NULL;