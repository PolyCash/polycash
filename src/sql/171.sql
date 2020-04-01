ALTER TABLE `games` ADD `sec_per_faucet_claim` INT NULL DEFAULT '86400' AFTER `every_event_bet_reminder_minutes`, ADD `min_sec_between_claims` INT NULL DEFAULT '43200' AFTER `sec_per_faucet_claim`;
