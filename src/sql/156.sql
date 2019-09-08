ALTER TABLE addresses ADD is_passthrough_address TINYINT NOT NULL DEFAULT '0' AFTER is_separator_address;
UPDATE addresses SET is_passthrough_address=1 WHERE option_index=2;
ALTER TABLE transaction_ios ADD is_passthrough TINYINT NOT NULL DEFAULT '0' AFTER is_separator;
UPDATE transaction_ios SET is_passthrough=1 WHERE option_index=2;
