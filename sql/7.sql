CREATE TABLE `newsletter_subscribers` (
  `subscriber_id` int(11) NOT NULL,
  `email_address` varchar(255) NOT NULL DEFAULT '',
  `time_created` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `newsletter_subscribers`
  ADD PRIMARY KEY (`subscriber_id`),
  ADD UNIQUE KEY `email_address` (`email_address`);

ALTER TABLE `newsletter_subscribers`
  MODIFY `subscriber_id` int(11) NOT NULL AUTO_INCREMENT;

