INSERT INTO currency_prices SET currency_id=6, reference_currency_id=6, price=1, time_added=1561602626;
DELETE FROM currency_prices WHERE currency_id=1 AND reference_currency_id=1;
UPDATE currency_prices SET price=1/price, currency_id=1, reference_currency_id=6 WHERE currency_id=6 AND reference_currency_id=1;
UPDATE site_constants SET constant_value=6 WHERE constant_name='reference_currency_id';
UPDATE currencies SET oracle_url_id=NULL WHERE currency_id=6;
UPDATE currencies SET oracle_url_id=3 WHERE currency_id=1;
ALTER TABLE `currency_prices` CHANGE `price` `price` FLOAT NOT NULL DEFAULT '0';
