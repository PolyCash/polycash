ALTER TABLE transactions DROP INDEX tx_hash;
ALTER TABLE `transactions` ADD UNIQUE (`game_id`, `tx_hash`);
ALTER TABLE `events` ADD UNIQUE (`game_id`, `event_index`);
ALTER TABLE `transactions` ADD `votebase_event_id` INT NULL DEFAULT NULL AFTER `transaction_desc`;