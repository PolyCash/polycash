<?php
function game_info_table(&$game) {
	$html = "";
	
	$blocks_per_hour = 3600/$game['seconds_per_block'];
	$round_reward = ($game['pos_reward']+$game['pow_reward']*$game['round_length'])/pow(10,8);
	$seconds_per_round = $game['seconds_per_block']*$game['round_length'];
	$game_url = $GLOBALS['base_url']."/".$game['url_identifier'];

	$invite_currency = false;
	if ($game['invite_currency'] > 0) {
		$q = "SELECT * FROM currencies WHERE currency_id='".$game['invite_currency']."';";
		$r = $GLOBALS['app']->run_query($q);
		$invite_currency = mysql_fetch_array($r);
	}

	if ($game['game_id'] > 0) { // This public function can also be called with a game variation
		$html .= '<div class="row"><div class="col-sm-5">Game title:</div><div class="col-sm-7">'.$game['name']."</div></div>\n";
		
		$html .= '<div class="row"><div class="col-sm-5">Game URL:</div><div class="col-sm-7"><a href="'.$game_url.'">'.$game_url."</a></div></div>\n";
	}
	
	$html .= '<div class="row"><div class="col-sm-5">Length of game:</div><div class="col-sm-7">';
	if ($game['final_round'] > 0) $html .= $game['final_round']." rounds (".$GLOBALS['app']->format_seconds($seconds_per_round*$game['final_round']).")";
	else $html .= "Game does not end";
	$html .= "</div></div>\n";
	
	$html .= '<div class="row"><div class="col-sm-5">'.ucfirst($game['option_name_plural']).'</div><div class="col-sm-7">'.$game['num_voting_options']." </div></div>\n";
	
	$html .= '<div class="row"><div class="col-sm-5">'.ucfirst($game['option_name']).' voting cap:</div><div class="col-sm-7">'.(100*$game['max_voting_fraction'])."%</div></div>\n";
	
	$html .= '<div class="row"><div class="col-sm-5">Cost to join:</div><div class="col-sm-7">';
	if ($game['giveaway_status'] == "invite_pay" || $game['giveaway_status'] == "public_pay") $html .= $GLOBALS['app']->format_bignum($game['invite_cost'])." ".$invite_currency['short_name']."s";
	else $html .= "Free";
	$html .= "</div></div>\n";
	
	$html .= '<div class="row"><div class="col-sm-5">Additional buy-ins?</div><div class="col-sm-7">';
	if ($game['buyin_policy'] == "unlimited") $html .= "Unlimited";
	else if ($game['buyin_policy'] == "none") $html .= "Not allowed";
	else if ($game['buyin_policy'] == "per_user_cap") $html .= "Up to ".$GLOBALS['app']->format_bignum($game['per_user_buyin_cap'])." ".$invite_currency['short_name']."s per player";
	else if ($game['buyin_policy'] == "game_cap") $html .= $GLOBALS['app']->format_bignum($game['game_buyin_cap'])." ".$invite_currency['short_name']."s available";
	else if ($game['buyin_policy'] == "game_and_user_cap") $html .= $GLOBALS['app']->format_bignum($game['per_user_buyin_cap'])." ".$invite_currency['short_name']."s per person until ".$GLOBALS['app']->format_bignum($game['game_buyin_cap'])." ".$invite_currency['short_name']."s are reached";
	$html .= "</div></div>\n";
	
	$html .= '<div class="row"><div class="col-sm-5">Inflation:</div><div class="col-sm-7">'.ucwords($game['inflation'])." (";	
	if ($game['inflation'] == "linear") $html .= $GLOBALS['app']->format_bignum($round_reward)." coins per round";
	else $html .= 100*$game['exponential_inflation_rate']."% per round";
	$html .= ")</div></div>\n";
	
	$total_inflation_pct = game_final_inflation_pct($game);
	if ($total_inflation_pct) {
		$html .= '<div class="row"><div class="col-sm-5">Total inflation:</div><div class="col-sm-7">'.number_format($total_inflation_pct)."%</div></div>\n";
	}
	
	$html .= '<div class="row"><div class="col-sm-5">Distribution:</div><div class="col-sm-7">';
	if ($game['inflation'] == "linear") $html .= $GLOBALS['app']->format_bignum($game['pos_reward']/pow(10,8))." to voters, ".$GLOBALS['app']->format_bignum($game['pow_reward']*$game['round_length']/pow(10,8))." to miners";
	else $html .= (100 - 100*$game['exponential_inflation_minershare'])."% to voters, ".(100*$game['exponential_inflation_minershare'])."% to miners";
	$html .= "</div></div>\n";
	
	$html .= '<div class="row"><div class="col-sm-5">Blocks per round:</div><div class="col-sm-7">'.$game['round_length']."</div></div>\n";
	
	$html .= '<div class="row"><div class="col-sm-5">Block target time:</div><div class="col-sm-7">'.$GLOBALS['app']->format_seconds($game['seconds_per_block'])."</div></div>\n";
	
	$html .= '<div class="row"><div class="col-sm-5">Average time per round:</div><div class="col-sm-7">'.$GLOBALS['app']->format_seconds($game['round_length']*$game['seconds_per_block'])."</div></div>\n";
	
	if ($game['maturity'] != 0) {
		$html .= '<div class="row"><div class="col-sm-5">Transaction maturity:</div><div class="col-sm-7">'.$game['maturity']." block";
		if ($game['maturity'] != 1) $html .= "s";
		$html .= "</div></div>\n";
	}

	return $html;
}

function game_final_inflation_pct(&$game) {
	if ($game['final_round'] > 0) {
		if ($game['inflation'] == "exponential") {
			$inflation_factor = pow(1+$game['exponential_inflation_rate'], $game['final_round']);
		}
		else {
			if ($game['start_condition'] == "players_joined") {
				$game['initial_coins'] = $game['start_condition_players']*$game['giveaway_amount'];
				$final_coins = ideal_coins_in_existence_after_round($game, $game['final_round']);
				$inflation_factor = $final_coins/$game['initial_coins'];
			}
			else return false;
		}
		$inflation_pct = round(($inflation_factor-1)*100);
		return $inflation_pct;
	}
	else return false;
}
	
function coins_in_existence(&$game, $block_id) {
	$q = "SELECT SUM(amount) FROM transactions WHERE block_id IS NOT NULL AND game_id='".$game['game_id']."' AND transaction_desc IN ('giveaway','votebase','coinbase')";
	if ($block_id) $q .= " AND block_id <= ".$block_id;
	$q .= ";";
	$r = $GLOBALS['app']->run_query($q);
	$coins = mysql_fetch_row($r);
	$coins = $coins[0];
	if ($coins > 0) return $coins;
	else return 0;
}

function ideal_coins_in_existence_after_round(&$game, $round_id) {
	if ($game['inflation'] == "linear") return $game['initial_coins'] + $round_id*($game['pos_reward'] + $game['round_length']*$game['pow_reward']);
	else return floor($game['initial_coins'] * pow(1 + $game['exponential_inflation_rate'], $round_id));
}

function coins_created_in_round(&$game, $round_id) {
	return ideal_coins_in_existence_after_round($game, $round_id) - ideal_coins_in_existence_after_round($game, $round_id-1);
}

function pow_reward_in_round(&$game, $round_id) {
	if ($game['inflation'] == "linear") return $game['pow_reward'];
	else {
		$round_coins_created = coins_created_in_round($game, $round_id);
		$round_pow_coins = floor($game['exponential_inflation_minershare']*$round_coins_created);
		return floor($round_pow_coins/$game['round_length']);
	}
}

function pos_reward_in_round(&$game, $round_id) {
	if ($game['inflation'] == "linear") return $game['pos_reward'];
	else {
		$round_coins_created = coins_created_in_round($game, $round_id);
		return floor((1-$game['exponential_inflation_minershare'])*$round_coins_created);
	}
}
?>