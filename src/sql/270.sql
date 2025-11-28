ALTER TABLE `blockchains` ADD `simple_tx_recommended_fee_float` DECIMAL(16,8) NULL DEFAULT NULL AFTER `auto_claim_to_account_id`;
UPDATE blockchains SET simple_tx_recommended_fee_float=0.00001 WHERE url_identifier='bitcoin';
UPDATE blockchains SET simple_tx_recommended_fee_float=0.0001 WHERE url_identifier='litecoin';
UPDATE blockchains SET simple_tx_recommended_fee_float=0.0001 WHERE url_identifier='datachain';
