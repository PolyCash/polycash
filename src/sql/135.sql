RENAME TABLE `card_issuers` TO `peers`;
ALTER TABLE `peers` CHANGE `issuer_id` `peer_id` INT(11) NOT NULL AUTO_INCREMENT, CHANGE `issuer_identifier` `peer_identifier` VARCHAR(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `issuer_name` `peer_name` VARCHAR(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;
ALTER TABLE `cards` CHANGE `issuer_card_id` `peer_card_id` INT(20) NULL DEFAULT NULL;
ALTER TABLE `blockchains` CHANGE `authoritative_issuer_id` `authoritative_peer_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `card_printrequests` CHANGE `issuer_id` `peer_id` INT(11) NULL DEFAULT NULL;
