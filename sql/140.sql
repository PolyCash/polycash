ALTER TABLE `blockchains` ADD `load_unconfirmed_transactions` TINYINT(1) NOT NULL DEFAULT '1' AFTER `first_required_block`;
UPDATE blockchains SET load_unconfirmed_transactions=0 WHERE blockchain_name='Bitcoin';
