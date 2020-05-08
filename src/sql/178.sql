INSERT INTO `oracle_urls` (`oracle_url_id`, `format_id`, `url`) VALUES (NULL, 4, 'https://www.bitcoinprice.com/');
UPDATE `currencies` SET oracle_url_id=4 WHERE abbreviation IN ('USD','ETH','XRP','BCH');
