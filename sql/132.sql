ALTER TABLE `events` ADD `external_identifier` VARCHAR(255) NULL DEFAULT NULL AFTER `payout_transaction_id`;
