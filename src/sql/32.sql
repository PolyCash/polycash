ALTER TABLE address_keys DROP INDEX address_id;
ALTER TABLE address_keys ADD INDEX (address_id);