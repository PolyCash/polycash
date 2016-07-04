ALTER TABLE `user_strategies` ADD `transaction_fee` BIGINT(20) NOT NULL DEFAULT '100000' AFTER `game_id`;
ALTER TABLE `transaction_IOs` CHANGE `spend_status` `spend_status` ENUM('spent','unspent','unconfirmed') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'unconfirmed';
