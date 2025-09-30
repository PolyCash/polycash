CREATE TABLE `faucet_claims` (
  `claim_id` INT NOT NULL AUTO_INCREMENT,
  `faucet_id` INT NULL,
  `user_id` INT NULL,
  `receiver_id` INT NULL,
  `from_account_id` INT NULL,
  `to_account_id` INT NULL,
  `claim_count` INT NULL,
  `address_ids` MEDIUMTEXT NULL,
  `game_amounts_int` MEDIUMTEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `claim_time` INT NULL,
  PRIMARY KEY (`claim_id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_bin;

ALTER TABLE faucet_claims ADD INDEX (`faucet_id`);
ALTER TABLE faucet_claims ADD INDEX (`user_id`);
ALTER TABLE faucet_claims ADD INDEX (`receiver_id`);
ALTER TABLE faucet_claims ADD INDEX (`from_account_id`);
ALTER TABLE faucet_claims ADD INDEX (`to_account_id`);
ALTER TABLE faucet_claims ADD INDEX (`claim_time`);
