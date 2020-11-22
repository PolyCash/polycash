ALTER TABLE `transactions` CHANGE `fee_amount` `fee_amount` BIGINT(20) NULL DEFAULT '0';
ALTER TABLE `games` CHANGE `short_description` `short_description` TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;
ALTER TABLE `events` DROP `event_type_id`;
