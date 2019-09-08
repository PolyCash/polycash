ALTER TABLE addresses ADD is_passthrough_address TINYINT(1) NOT NULL DEFAULT '0' AFTER is_separator_address;
UPDATE addresses SET is_passthrough_address=1 WHERE option_index=2;
ALTER TABLE transaction_ios ADD is_passthrough TINYINT(1) NOT NULL DEFAULT '0' AFTER is_separator;
UPDATE transaction_ios SET is_passthrough=1 WHERE option_index=2;
ALTER TABLE transaction_ios ADD is_receiver TINYINT(1) NOT NULL DEFAULT '0' AFTER is_passthrough;
