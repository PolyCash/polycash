<?php
function new_db_conn() {
	$conn = new PDO("mysql:host=".$GLOBALS['mysql_server'].";charset=utf8", $GLOBALS['mysql_user'], $GLOBALS['mysql_password']) or die("Error, failed to connect to the database.");
	return $conn;
}

function game_info_table(&$app, &$db_game) {
	$html = "";
	
	$blocks_per_hour = 3600/$db_game['seconds_per_block'];
	$round_reward = ($db_game['pos_reward']+$db_game['pow_reward']*$db_game['round_length'])/pow(10,8);
	$seconds_per_round = $db_game['seconds_per_block']*$db_game['round_length'];
	$db_game_url = $GLOBALS['base_url']."/".$db_game['url_identifier'];

	$invite_currency = false;
	if ($db_game['invite_currency'] > 0) {
		$q = "SELECT * FROM currencies WHERE currency_id='".$db_game['invite_currency']."';";
		$r = $app->run_query($q);
		$invite_currency = $r->fetch();
	}

	if ($db_game['game_id'] > 0) { // This public function can also be called with a game variation
		$html .= '<div class="row"><div class="col-sm-5">Game title:</div><div class="col-sm-7">'.$db_game['name']."</div></div>\n";
		
		$html .= '<div class="row"><div class="col-sm-5">Game URL:</div><div class="col-sm-7"><a href="'.$db_game_url.'">'.$db_game_url."</a></div></div>\n";
	}
	
	$html .= '<div class="row"><div class="col-sm-5">Length of game:</div><div class="col-sm-7">';
	if ($db_game['final_round'] > 0) $html .= $db_game['final_round']." rounds (".$app->format_seconds($seconds_per_round*$db_game['final_round']).")";
	else $html .= "Game does not end";
	$html .= "</div></div>\n";
	
	$html .= '<div class="row"><div class="col-sm-5">'.ucfirst($db_game['option_name_plural']).'</div><div class="col-sm-7">'.$db_game['num_voting_options']." </div></div>\n";
	
	$html .= '<div class="row"><div class="col-sm-5">'.ucfirst($db_game['option_name']).' voting cap:</div><div class="col-sm-7">'.(100*$db_game['max_voting_fraction'])."%</div></div>\n";
	
	$html .= '<div class="row"><div class="col-sm-5">Cost to join:</div><div class="col-sm-7">';
	if ($db_game['giveaway_status'] == "invite_pay" || $db_game['giveaway_status'] == "public_pay") $html .= $app->format_bignum($db_game['invite_cost'])." ".$invite_currency['short_name']."s";
	else $html .= "Free";
	$html .= "</div></div>\n";
	
	$html .= '<div class="row"><div class="col-sm-5">Additional buy-ins?</div><div class="col-sm-7">';
	if ($db_game['buyin_policy'] == "unlimited") $html .= "Unlimited";
	else if ($db_game['buyin_policy'] == "none") $html .= "Not allowed";
	else if ($db_game['buyin_policy'] == "per_user_cap") $html .= "Up to ".$app->format_bignum($db_game['per_user_buyin_cap'])." ".$invite_currency['short_name']."s per player";
	else if ($db_game['buyin_policy'] == "game_cap") $html .= $app->format_bignum($db_game['game_buyin_cap'])." ".$invite_currency['short_name']."s available";
	else if ($db_game['buyin_policy'] == "game_and_user_cap") $html .= $app->format_bignum($db_game['per_user_buyin_cap'])." ".$invite_currency['short_name']."s per person until ".$app->format_bignum($db_game['game_buyin_cap'])." ".$invite_currency['short_name']."s are reached";
	$html .= "</div></div>\n";
	
	$html .= '<div class="row"><div class="col-sm-5">Inflation:</div><div class="col-sm-7">'.ucwords($db_game['inflation'])." (";	
	if ($db_game['inflation'] == "linear") $html .= $app->format_bignum($round_reward)." coins per round";
	else $html .= 100*$db_game['exponential_inflation_rate']."% per round";
	$html .= ")</div></div>\n";
	
	$total_inflation_pct = game_final_inflation_pct($db_game);
	if ($total_inflation_pct) {
		$html .= '<div class="row"><div class="col-sm-5">Total inflation:</div><div class="col-sm-7">'.number_format($total_inflation_pct)."%</div></div>\n";
	}
	
	$html .= '<div class="row"><div class="col-sm-5">Distribution:</div><div class="col-sm-7">';
	if ($db_game['inflation'] == "linear") $html .= $app->format_bignum($db_game['pos_reward']/pow(10,8))." to voters, ".$app->format_bignum($db_game['pow_reward']*$db_game['round_length']/pow(10,8))." to miners";
	else $html .= (100 - 100*$db_game['exponential_inflation_minershare'])."% to voters, ".(100*$db_game['exponential_inflation_minershare'])."% to miners";
	$html .= "</div></div>\n";
	
	$html .= '<div class="row"><div class="col-sm-5">Blocks per round:</div><div class="col-sm-7">'.$db_game['round_length']."</div></div>\n";
	
	$html .= '<div class="row"><div class="col-sm-5">Block target time:</div><div class="col-sm-7">'.$app->format_seconds($db_game['seconds_per_block'])."</div></div>\n";
	
	$html .= '<div class="row"><div class="col-sm-5">Average time per round:</div><div class="col-sm-7">'.$app->format_seconds($db_game['round_length']*$db_game['seconds_per_block'])."</div></div>\n";
	
	if ($db_game['maturity'] != 0) {
		$html .= '<div class="row"><div class="col-sm-5">Transaction maturity:</div><div class="col-sm-7">'.$db_game['maturity']." block";
		if ($db_game['maturity'] != 1) $html .= "s";
		$html .= "</div></div>\n";
	}

	return $html;
}

function game_final_inflation_pct(&$db_game) {
	if ($db_game['final_round'] > 0) {
		if ($db_game['inflation'] == "exponential") {
			$inflation_factor = pow(1+$db_game['exponential_inflation_rate'], $db_game['final_round']);
		}
		else {
			if ($db_game['start_condition'] == "players_joined") {
				$db_game['initial_coins'] = $db_game['start_condition_players']*$db_game['giveaway_amount'];
				$final_coins = ideal_coins_in_existence_after_round($db_game, $db_game['final_round']);
				$inflation_factor = $final_coins/$db_game['initial_coins'];
			}
			else return false;
		}
		$inflation_pct = round(($inflation_factor-1)*100);
		return $inflation_pct;
	}
	else return false;
}

function ideal_coins_in_existence_after_round(&$db_game, $round_id) {
	if ($db_game['inflation'] == "linear") return $db_game['initial_coins'] + $round_id*($db_game['pos_reward'] + $db_game['round_length']*$db_game['pow_reward']);
	else return floor($db_game['initial_coins'] * pow(1 + $db_game['exponential_inflation_rate'], $round_id));
}

function coins_created_in_round(&$db_game, $round_id) {
	$thisround_coins = ideal_coins_in_existence_after_round($db_game, $round_id);
	$prevround_coins = ideal_coins_in_existence_after_round($db_game, $round_id-1);
	if (is_nan($thisround_coins) || is_nan($prevround_coins) || is_infinite($thisround_coins) || is_infinite($prevround_coins)) return 0;
	else return $thisround_coins - $prevround_coins;
}

function pow_reward_in_round(&$db_game, $round_id) {
	if ($db_game['inflation'] == "linear") return $db_game['pow_reward'];
	else {
		$round_coins_created = coins_created_in_round($db_game, $round_id);
		$round_pow_coins = floor($db_game['exponential_inflation_minershare']*$round_coins_created);
		return floor($round_pow_coins/$db_game['round_length']);
	}
}

function pos_reward_in_round(&$db_game, $round_id) {
	if ($db_game['inflation'] == "linear") return $db_game['pos_reward'];
	else {
		$round_coins_created = coins_created_in_round($db_game, $round_id);
		return floor((1-$db_game['exponential_inflation_minershare'])*$round_coins_created);
	}
}
?>
