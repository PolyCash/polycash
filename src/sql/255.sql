CREATE TABLE `blockchain_checks` (
	`blockchain_check_id` INT NOT NULL AUTO_INCREMENT,
	`blockchain_id` INT NULL DEFAULT NULL,
	`creator_id` INT NULL DEFAULT NULL,
	`from_block` INT NULL DEFAULT NULL,
	`check_type` VARCHAR(30) NULL DEFAULT NULL,
	`first_error_block` INT NULL DEFAULT NULL,
	`first_error_message` VARCHAR(255) NULL DEFAULT NULL,
	`created_at` INT NULL DEFAULT NULL,
	`completed_at` INT NULL DEFAULT NULL,
	`processed_to_block` INT NULL DEFAULT NULL,
	PRIMARY KEY (`blockchain_check_id`)
) ENGINE = InnoDB;
