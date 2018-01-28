ALTER TABLE `newsletter_subscribers` ADD `subscribed` TINYINT(1) NOT NULL DEFAULT '1' AFTER `time_created`;
