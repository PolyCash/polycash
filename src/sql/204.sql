ALTER TABLE `blockchains` ADD `is_rpc_mining` TINYINT(1) NOT NULL DEFAULT '0' AFTER `genesis_address`;
