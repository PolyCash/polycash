CREATE TABLE `user_login_links` (
  `login_link_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `access_key` varchar(32) DEFAULT NULL,
  `time_created` int(11) DEFAULT NULL,
  `time_clicked` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `user_login_links`
  ADD PRIMARY KEY (`login_link_id`),
  ADD UNIQUE KEY `access_key` (`access_key`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `user_login_links`
  MODIFY `login_link_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
