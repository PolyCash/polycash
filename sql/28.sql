ALTER TABLE blocks DROP INDEX block_id;
ALTER TABLE `blocks` ADD UNIQUE (`blockchain_id`, `block_id`);