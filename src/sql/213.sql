ALTER TABLE `address_keys`
  DROP `priv_enc`,
  DROP `associated_email_address`;

ALTER TABLE `address_keys` CHANGE `priv_key` `priv_key` VARCHAR(60) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;
