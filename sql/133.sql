ALTER TABLE `games` ADD `finite_events` TINYINT(1) NULL DEFAULT '1' AFTER `decimal_places`;
UPDATE games SET finite_events=0 WHERE event_rule NOT IN ('', 'game_definition');
UPDATE games SET finite_events=0 WHERE module IN ('DailyCryptoMarkets','CryptoDuels','CoinBattles','ElectionSim','EmpirecoinClassic','ImageTournament','SingleElimination');
