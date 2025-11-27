<?php
require(__DIR__.'/classes/MonsterDuelsBetFavorites.php');
require(dirname(__DIR__)."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$allowed_params = ['api_key', 'amount_per_event', 'force', 'amount_mode', 'fee_per_txo', 'bet_for_qty'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

$run_params = array_intersect_key($_REQUEST, array_flip($allowed_params));

$strategy = new MonsterDuelsBetFavorites($app);
[$status_code, $message] = $strategy->run($run_params);

$app->output_message($status_code, $message, false);
