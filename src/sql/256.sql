ALTER TABLE currency_accounts ADD COLUMN `account_transaction_fee` float NULL DEFAULT 0.0001;
UPDATE currency_accounts ca JOIN user_games ug ON ca.account_id=ug.account_id JOIN user_strategies us ON ug.strategy_id=us.strategy_id SET ca.account_transaction_fee=us.transaction_fee;
