ALTER TABLE `blockchains` CHANGE `p2p_mode` `p2p_mode` ENUM('none','rpc','web_api') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'none';
ALTER TABLE `blockchains` ADD `authoritative_issuer_id` INT NULL DEFAULT NULL AFTER `default_image_id`;
UPDATE `blockchains` SET `p2p_mode` = 'web_api' WHERE url_identifier='stakechain';