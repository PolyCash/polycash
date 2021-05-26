INSERT INTO oracle_urls SET format_id=5, url='https://api.coindesk.com/v1/bpi/currentprice.json';
UPDATE `currencies` SET oracle_url_id=5 WHERE currency_id=1;
UPDATE `currencies` SET oracle_url_id=NULL WHERE oracle_url_id=4;
