ALTER TABLE blockchains ADD COLUMN `sync_mode` VARCHAR(30) DEFAULT 'full' AFTER `p2p_mode`;
