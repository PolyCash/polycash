ALTER TABLE transactions DROP INDEX tx_hash;
ALTER TABLE `transactions` ADD UNIQUE (`game_id`, `tx_hash`);
