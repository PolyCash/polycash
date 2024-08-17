CREATE INDEX gios_game_id_io_id ON transaction_game_ios (game_id, io_id);
CREATE INDEX io_address_status_mature ON transaction_ios (address_id, spend_status, is_mature);
