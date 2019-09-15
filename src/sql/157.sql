ALTER TABLE transaction_game_ios ADD contract_parts BIGINT NULL DEFAULT NULL AFTER destroy_amount;
ALTER TABLE games ADD default_contract_parts BIGINT NULL DEFAULT '100000000' AFTER default_buyin_currency_id;
UPDATE transaction_game_ios gio JOIN games g ON gio.game_id=g.game_id SET gio.contract_parts=g.default_contract_parts WHERE gio.option_id IS NOT NULL;
ALTER TABLE transaction_game_ios ADD resolved_before_spent TINYINT(1) NULL DEFAULT NULL AFTER is_resolved;
