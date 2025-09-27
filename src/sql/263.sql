CREATE TABLE `faucets` (
  `faucet_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `game_id` INT NOT NULL,
  `account_id` INT NOT NULL,
  `display_from_name` VARCHAR(100) NULL DEFAULT NULL,
  `faucet_enabled` TINYINT(1) NULL DEFAULT 1,
  `everyone_eligible` TINYINT(1) NOT NULL,
  `approval_method` VARCHAR(24) NOT NULL,
  `txo_size` DOUBLE NULL,
  `sec_per_faucet_claim` INT NULL DEFAULT NULL,
  `min_sec_between_claims` INT NULL DEFAULT NULL,
  `bonus_claims` INT NULL DEFAULT NULL,
  `max_claims_at_once` INT NULL DEFAULT NULL,
  `created_at` INT NOT NULL,
  PRIMARY KEY (`faucet_id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_bin;

ALTER TABLE faucets ADD INDEX (`user_id`);
ALTER TABLE faucets ADD INDEX (`game_id`);
ALTER TABLE faucets ADD INDEX (`account_id`);
ALTER TABLE faucets ADD INDEX (`everyone_eligible`);
ALTER TABLE faucets ADD INDEX (`created_at`);

ALTER TABLE currency_accounts DROP COLUMN faucet_donations_on;
ALTER TABLE `currency_accounts` ADD COLUMN `donate_to_faucet_id` INT NULL DEFAULT NULL AFTER `account_transaction_fee`;

CREATE TABLE `faucet_receivers` (
  `receiver_id` INT NOT NULL AUTO_INCREMENT,
  `faucet_id` INT NULL,
  `user_id` INT NULL,
  `join_time` INT NULL,
  `latest_claim_time` INT NULL,
  `faucet_claims` INT NULL DEFAULT 0,
  PRIMARY KEY (`receiver_id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_bin;

ALTER TABLE faucet_receivers ADD INDEX (`faucet_id`);
ALTER TABLE faucet_receivers ADD INDEX (`user_id`);
ALTER TABLE faucet_receivers ADD INDEX (`join_time`);

CREATE TABLE `faucet_join_requests` (
  `request_id` INT NOT NULL AUTO_INCREMENT,
  `faucet_id` INT NULL,
  `user_id` INT NULL,
  `game_id` INT NULL,
  `receiver_id` INT NULL,
  `request_time` INT NULL,
  `approve_time` INT NULL,
  PRIMARY KEY (`request_id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_bin;

ALTER TABLE faucet_join_requests ADD INDEX (`faucet_id`);
ALTER TABLE faucet_join_requests ADD INDEX (`user_id`);
ALTER TABLE faucet_join_requests ADD INDEX (`game_id`);
ALTER TABLE faucet_join_requests ADD INDEX (`receiver_id`);
ALTER TABLE faucet_join_requests ADD INDEX (`request_time`);

ALTER TABLE `games` 
DROP COLUMN `max_claims_at_once`,
DROP COLUMN `bonus_claims`,
DROP COLUMN `min_sec_between_claims`,
DROP COLUMN `sec_per_faucet_claim`,
DROP COLUMN `faucet_policy`;

ALTER TABLE `currency_accounts` DROP COLUMN `is_faucet`;

ALTER TABLE `currency_accounts` DROP COLUMN `faucet_amount_each`;
