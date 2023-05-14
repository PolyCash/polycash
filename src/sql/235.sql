INSERT INTO oracle_urls SET url='https://www.coindesk.com/price/litecoin/', format_id=6;
UPDATE currencies SET oracle_url_id=6 WHERE abbreviation='LTC';
