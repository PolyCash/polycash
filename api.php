<?php
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$uri = $_SERVER['REQUEST_URI'];
$uri_parts = explode("/", $uri);

if ($uri_parts[1] == "api") {
	if ($uri_parts[2] == "about") {
		$pagetitle = $GLOBALS['coin_brand_name']." API Documentation";
		$nav_tab_selected = "api";
		include('includes/html_start.php');
		
		if (empty($game_id)) {
			$game_id = $app->run_query("SELECT * FROM games WHERE featured=1 ORDER BY game_id ASC LIMIT 1;")->fetch()['game_id'];
		}
		$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$api_game = new Game($blockchain, $game_id);
		$api_game->load_current_events();
		?>
		<div class="container-fluid">
			<div class="panel panel-default" style="margin-top: 15px;">
				<div class="panel-heading">
					<div class="panel-title"><?php echo $GLOBALS['coin_brand_name']; ?> API Documentation</div>
				</div>
				<div class="panel-body">
					<p>
						<?php echo $GLOBALS['coin_brand_name']; ?> web wallets provide several strategies for automating your <?php echo $GLOBALS['coin_brand_name']; ?> voting behavior.  However, some users may wish to use custom logic in their voting strategies. The <?php echo $GLOBALS['coin_brand_name']; ?> API allows this functionality through a standardized format for sharing <?php echo $GLOBALS['coin_brand_name']; ?> voting recommendations. Using the <?php echo $GLOBALS['coin_brand_name']; ?> API can be as simple as finding a public recommendations URL and plugging it into your <?php echo $GLOBALS['coin_brand_name']; ?> user account.  Or you can set up your own voting recommendations client using the information below.
					</p>
					<p>
						To get started, please download this example API client written in PHP.<br/>
						<a class="btn btn-success" href="/api/download-client-example/">Download example API client</a>
						<br/><br/>
					</p>
					<p>
						<b><a target="_blank" href="/api/<?php echo $api_game->db_game['url_identifier']; ?>/status/">/api/<?php echo $api_game->db_game['url_identifier']; ?>/status/</a></b><br/>
						Yields information about current status of the blockchain.
						<br/>
					</p>
					<pre id="api_status_example" style="display: none;"></pre>
					<p>
						<b>/api/<?php echo $api_game->db_game['url_identifier']; ?>/status/?api_access_code=&lt;ACCESS_CODE&gt;</b><br/>
						Supply your API access code to get relevant info on your user account in addition to general blockchain information.
						<br/>
					</p>
					<pre id="api_status_user_example" style="display: none;"></pre>
				</div>
			</div>
		</div>
		<?php
		include('includes/html_stop.php');
	}
	else if ($uri_parts[2] == "download-client-example") {
		$example_password = "password123";
		$fname = "api_client.php";
		
		$fh = fopen($fname, 'r');
		$raw = fread($fh, filesize($fname));
		$raw = str_replace('include("includes/config.php");', '', $raw);
		$raw = str_replace('$access_key = $GLOBALS[\'cron_key_string\']', '$access_key = "'.$example_password.'"', $raw);
		$raw = str_replace('$GLOBALS[\'cron_key_string\']', $example_password, $raw);
		$raw = str_replace('$GLOBALS[\'default_server_api_access_key\']', '""', $raw);
		
		header('Content-Type: application/x-download');
		header('Content-disposition: attachment; filename="'.$GLOBALS['coin_brand_name'].'APIClient.php"');
		header('Cache-Control: public, must-revalidate, max-age=0');
		header('Pragma: public');
		header('Content-Length: '.strlen($raw));
		header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
		echo $raw;
	}
	else if ($uri_parts[2] == "card" || $uri_parts[2] == "cards") {
		$this_issuer = $app->get_issuer_by_server_name($GLOBALS['base_url']);
		
		if ($uri_parts[2] == "card" && !empty($uri_parts[4]) && !empty($uri_parts[5]) && $uri_parts[4] == "check") {
			$card_id = (int) $uri_parts[3];
			$supplied_secret = $uri_parts[5];
			$supplied_secret_hash = $app->card_secret_to_hash($supplied_secret);
			
			$card_q = "SELECT * FROM cards WHERE issuer_card_id='".$card_id."' AND issuer_id='".$this_issuer['issuer_id']."';";
			$card_r = $app->run_query($card_q);
			
			if ($card_r->rowCount() > 0) {
				$card = $card_r->fetch();
				
				if ($supplied_secret == $card['secret_hash'] || $supplied_secret_hash == $card['secret_hash']) {
					$app->output_message(1, "Correct!");
				}
				else {
					$app->output_message(2, "Incorrect");
				}
			}
			else $app->output_message(3, "Invalid card ID");
			
			die();
		}
		else if ($uri_parts[2] == "card" && !empty($uri_parts[4]) && $uri_parts[4] == "withdraw") {
			$card_id = (int) $uri_parts[3];
			
			$supplied_secret = $_REQUEST['secret'];
			$supplied_secret_hash = $app->card_secret_to_hash($supplied_secret);
			$fee = $_REQUEST['fee'];
			$address = $_REQUEST['address'];
			
			$card_q = "SELECT * FROM cards WHERE issuer_card_id='".$card_id."' AND issuer_id='".$this_issuer['issuer_id']."';";
			$card_r = $app->run_query($card_q);
			
			if ($card_r->rowCount() > 0) {
				$card = $card_r->fetch();
				
				if ($fee > 0 && $fee < $card['amount']) {
					if ($supplied_secret == $card['secret_hash'] || $supplied_secret_hash == $card['secret_hash']) {
						$transaction = $app->pay_out_card($card, $address, $fee);
						
						if ($transaction) {
							$app->output_message(1, $transaction['tx_hash'], false);
						}
						else $app->output_message(6, $message, false);
					}
					else $app->output_message(5, "Error: wrong secret key.", false);
				}
				else $app->output_message(4, "Error: invalid fee amount.", false);
			}
			else $app->output_message(3, "Invalid card ID");
		}
		else {
			$card_public_vars = $app->card_public_vars();
			
			if ($uri_parts[2] == "card") {
				$card_id = (int) $uri_parts[3];
			}
			else {
				$uri_parts[3] = str_replace(":", "-", $uri_parts[3]);
				$card_range = explode("-", $uri_parts[3]);
				$from_card_id = (int) $card_range[0];
				$to_card_id = (int) $card_range[1];
			}
			
			$q = "SELECT ";
			foreach ($card_public_vars as $var_name) {
				$q .= "c.".$var_name.", ";
			}
			$q .= "curr.abbreviation AS currency_abbreviation, fv_curr.abbreviation AS fv_currency_abbreviation FROM cards c JOIN currencies curr ON c.currency_id=curr.currency_id JOIN currencies fv_curr ON c.fv_currency_id=fv_curr.currency_id LEFT JOIN card_designs d ON c.design_id=d.design_id WHERE c.issuer_id='".$this_issuer['issuer_id']."' AND ";
			if ($uri_parts[2] == "card") $q .= "c.issuer_card_id='".$card_id."'";
			else $q .= "c.issuer_card_id >= ".$from_card_id." AND c.issuer_card_id <= ".$to_card_id;
			$q .= ";";
			$r = $app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$cards = array();
				while ($card = $r->fetch(PDO::FETCH_ASSOC)) {
					array_push($cards, $card);
				}
				$api_output['status_code'] = 1;
				$api_output['cards'] = $cards;
				echo json_encode($api_output, JSON_PRETTY_PRINT);
			}
			else $app->output_message(0, "Error: card not found.");
		}
	}
	else if (count($uri_parts) >= 5 && ($uri_parts[2] == "block" || $uri_parts[2] == "blocks")) {
		$blockchain_identifier = $uri_parts[3];
		
		if ($uri_parts[2] == "block") {
			$block_height = (int) $uri_parts[4];
		}
		else {
			$uri_parts[4] = str_replace(":", "-", $uri_parts[4]);
			$block_range = explode("-", $uri_parts[4]);
			$from_block_height = (int) $block_range[0];
			$to_block_height = (int) $block_range[1];
		}
		
		$db_blockchain = $app->fetch_blockchain_by_identifier($blockchain_identifier);
		
		if ($db_blockchain) {
			$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
			
			$block_q = "SELECT block_id, block_hash, num_transactions, time_mined FROM blocks WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."'";
			if ($uri_parts[2] == "block") $block_q .= " AND block_id='".$block_height."'";
			else $block_q .= " AND block_id >= ".$from_block_height." AND block_id <= ".$to_block_height;
			$block_q .= ";";
			$block_r = $app->run_query($block_q);
			
			$blocks = array();
			
			while ($db_block = $block_r->fetch(PDO::FETCH_ASSOC)) {
				$transactions = array();
				
				$tx_q = "SELECT transaction_id, block_id, transaction_desc, tx_hash, amount, fee_amount, time_created, position_in_block, num_inputs, num_outputs FROM transactions WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND block_id='".$db_block['block_id']."' ORDER BY position_in_block ASC;";
				$tx_r = $app->run_query($tx_q);
				
				while ($tx = $tx_r->fetch(PDO::FETCH_ASSOC)) {
					list($inputs, $outputs) = $app->web_api_transaction_ios($tx['transaction_id']);
					
					unset($tx['transaction_id']);
					$tx['inputs'] = $inputs;
					$tx['outputs'] = $outputs;
					
					array_push($transactions, $tx);
				}
				$db_block['transactions'] = $transactions;
				
				array_push($blocks, $db_block);
			}
			
			$api_output['status_code'] = 1;
			$api_output['blocks'] = $blocks;
			echo json_encode($api_output, JSON_PRETTY_PRINT);
		}
	}
	else if ($uri_parts[2] == "transactions") {
		$url_identifier = $uri_parts[3];
		$db_blockchain = $app->fetch_blockchain_by_identifier($url_identifier);
		
		if ($db_blockchain) {
			$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
			
			if ($uri_parts[4] == "post" && $blockchain->db_blockchain['p2p_mode'] != "rpc") {
				$data = $_REQUEST['data'];
				$tx = get_object_vars(json_decode($data));
				
				$transaction_id = $blockchain->add_transaction_from_web_api(false, $tx);
				
				$coin_rpc = false;
				$successful = true;
				$db_transaction = $blockchain->add_transaction($coin_rpc, $tx['tx_hash'], false, true, $successful, $i, false, false);
				
				if ($db_transaction) $app->output_message(1, "Transaction successfully imported!", false);
				else $app->output_message(4, "There was an error importing the transaction.", false);
			}
			else $app->output_message(3, "Invalid action specified.", false);
		}
		else $app->output_message(2, "Error: invalid blockchain identifier.", false);
	}
	else if ($uri_parts[2] == "blockchain") {
		$url_identifier = $uri_parts[3];
		$blockchain_r = $app->run_query("SELECT blockchain_id, blockchain_name, url_identifier, p2p_mode, coin_name, coin_name_plural, seconds_per_block, decimal_places, initial_pow_reward FROM blockchains WHERE url_identifier=".$app->quote_escape($url_identifier).";");
		
		if ($blockchain_r->rowCount() > 0) {
			$db_blockchain = $blockchain_r->fetch(PDO::FETCH_ASSOC);
			$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
			$db_blockchain['last_block_id'] = $blockchain->last_block_id();
			unset($db_blockchain['blockchain_id']);
			
			echo json_encode($db_blockchain, JSON_PRETTY_PRINT);
		}
		else $app->output_message(2, "Error: invalid blockchain identifier.", false);
	}
	else if (!empty($uri_parts[2])) {
		$game_identifier = $uri_parts[2];
		
		$q = "SELECT game_id, blockchain_id, maturity, pos_reward, pow_reward, round_length, payout_weight, name FROM games WHERE url_identifier=".$app->quote_escape($game_identifier).";";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$db_game = $r->fetch();
			
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			$game->load_current_events();
			
			$last_block_id = $game->blockchain->last_block_id();
			$current_round = $game->block_to_round($last_block_id+1);
			$coins_per_vote = $game->blockchain->app->coins_per_vote($game->db_game);
			
			if ($game->db_game['module'] == "CoinBattles") {
				$btc_currency = $app->get_currency_by_abbreviation("BTC");
			}
			
			$intval_vars = array('game_id','round_length','maturity');
			for ($i=0; $i<count($intval_vars); $i++) {
				$game->db_game[$intval_vars[$i]] = intval($game->db_game[$intval_vars[$i]]);
			}
			
			if (empty($uri_parts[3]) || $uri_parts[3] == "status") {
				$api_user = FALSE;
				$api_user_info = FALSE;
				
				if (!empty($_REQUEST['api_access_code'])) {
					$q = "SELECT * FROM users u JOIN user_games ug ON u.user_id=ug.user_id WHERE ug.game_id='".$game->db_game['game_id']."' AND ug.api_access_code=".$app->quote_escape($_REQUEST['api_access_code']).";";
					$r = $app->run_query($q);
					
					if ($r->rowCount() == 1) {
						$user_game = $r->fetch();
						
						$api_user = new User($app, $user_game['user_id']);
						$account_value = $api_user->account_coin_value($game, $user_game);
						$immature_balance = $api_user->immature_balance($game, $user_game);
						$mature_balance = $api_user->mature_balance($game, $user_game);
						$votes_available = $api_user->user_current_votes($game, $last_block_id, $current_round, $user_game);
						
						$api_user_info['username'] = $api_user->db_user['username'];
						$api_user_info['account_id'] = intval($user_game['account_id']);
						$api_user_info['balance'] = $account_value;
						$api_user_info['mature_balance'] = $mature_balance;
						$api_user_info['immature_balance'] = $immature_balance;
						$api_user_info['votes_available'] = $votes_available;
						
						$mature_utxos = array();
						$mature_utxo_q = "SELECT io.*, ak.pub_key AS address FROM transaction_game_ios gio JOIN transaction_ios io ON io.io_id=gio.io_id JOIN address_keys ak ON io.address_id=ak.address_id WHERE io.spend_status='unspent' AND io.spend_transaction_id IS NULL AND ak.account_id=".$user_game['account_id']." AND gio.game_id=".$game->db_game['game_id']." AND (io.create_block_id <= ".($last_block_id-$game->db_game['maturity'])." OR gio.instantly_mature = 1) GROUP BY io.io_id ORDER BY io.io_id ASC;";
						$mature_utxo_r = $app->run_query($mature_utxo_q);
						
						$utxo_i = 0;
						
						while ($utxo = $mature_utxo_r->fetch()) {
							$game_io_q = "SELECT * FROM transaction_game_ios WHERE game_id='".$game->db_game['game_id']."' AND io_id='".$utxo['io_id']."'";
							if ($utxo['create_block_id'] > $last_block_id-$game->db_game['maturity']) $game_io_q .= " AND instantly_mature = 1";
							$game_io_q .= " ORDER BY game_io_id ASC;";
							$game_io_r = $app->run_query($game_io_q);
							
							$mature_utxo = array('io_id'=>intval($utxo['io_id']), 'coins'=>$utxo['amount'], 'create_block_id'=>intval($utxo['create_block_id']), 'address'=>$utxo['address']);
							$game_utxos = array();
							
							while ($game_io = $game_io_r->fetch()) {
								array_push($game_utxos, array('game_io_id'=>intval($game_io['game_io_id']), 'coins'=>$game_io['colored_amount'], 'is_coinbase'=>intval($game_io['is_coinbase'])));
							}
							$mature_utxo['game_utxos'] = $game_utxos;
							$mature_utxos[$utxo_i] = $mature_utxo;
							
							$utxo_i++;
						}
						$api_user_info['my_utxos'] = $mature_utxos;
					}
				}
				
				$output_game['game_id'] = $game->db_game['game_id'];
				$output_game['name'] = $game->db_game['name'];
				$output_game['last_block_id'] = intval($last_block_id);
				$output_game['current_round'] = $current_round;
				$output_game['block_within_round'] = $game->block_id_to_round_index($last_block_id+1);
				$output_game['exponential_inflation_rate'] = (float) $game->db_game['exponential_inflation_rate'];
				$output_game['payout_weight'] = $game->db_game['payout_weight'];
				
				$event_vars = array('event_id','event_type_id','event_name','event_starting_block','event_final_block','option_name','option_name_plural');
				$current_events = array();
				for ($i=0; $i<count($game->current_events); $i++) {
					for ($j=0; $j<count($event_vars); $j++) {
						$api_event[$event_vars[$j]] = $game->current_events[$i]->db_event[$event_vars[$j]];
						if (in_array($event_vars[$j], array('event_id', 'event_type_id', 'event_starting_block', 'event_final_block'))) $api_event[$event_vars[$j]] = (int) $api_event[$event_vars[$j]];
					}
					$api_event['options'] = array();
					
					$event_stats = $game->current_events[$i]->round_voting_stats_all();
					$total_vote_sum = $event_stats[0];
					$max_vote_sum = $event_stats[1];
					$ranked_stats = $event_stats[2];
					$option_id_to_rank = $event_stats[3];
					$confirmed_votes = $event_stats[4];
					$unconfirmed_votes = $event_stats[5];
					$effective_destroy_score = $event_stats[10];
					$unconfirmed_effective_destroy_score = $event_stats[11];
					
					$event_effective_coins = ($confirmed_votes+$unconfirmed_votes)*$coins_per_vote + $effective_destroy_score + $unconfirmed_effective_destroy_score;
					
					$qq = "SELECT * FROM options op JOIN events e ON op.event_id=e.event_id LEFT JOIN currencies c ON op.entity_id=c.entity_id WHERE e.event_id=".$game->current_events[$i]->db_event['event_id'].";";
					$rr = $app->run_query($qq);
					
					while ($option = $rr->fetch()) {
						$stat = $ranked_stats[$option_id_to_rank[$option['option_id']]];
						$api_stat = false;
						$api_stat['option_id'] = (int) $option['option_id'];
						$api_stat['option_index'] = (int) $option['option_index'];
						$api_stat['name'] = $stat['name'];
						$api_stat['rank'] = $option_id_to_rank[$option['option_id']]+1;
						$api_stat['confirmed_votes'] = $app->friendly_intval($stat['votes']);
						$api_stat['unconfirmed_votes'] = $app->friendly_intval($stat['unconfirmed_votes']);
						$api_stat['effective_destroy_score'] = $app->friendly_intval($stat['effective_destroy_score']);
						$api_stat['unconfirmed_effective_destroy_score'] = $app->friendly_intval($stat['unconfirmed_effective_destroy_score']);
						
						$option_effective_coins = ($api_stat['confirmed_votes'] + $api_stat['unconfirmed_votes'])*$coins_per_vote + $api_stat['effective_destroy_score'] + $api_stat['unconfirmed_effective_destroy_score'];
						if ($event_effective_coins == 0) $option_event_frac = 0;
						else $option_event_frac = $option_effective_coins/$event_effective_coins;
						$api_stat['fraction_of_votes'] = $option_event_frac;
						
						if (!empty($game->current_events[$i]->db_event['option_block_rule'])) $api_stat['option_block_score'] = (int) $option['option_block_score'];
						
						if ($game->db_game['module'] == "CoinBattles") {
							if ($option['currency_id'] == $btc_currency['currency_id']) $final_performance = 0;
							else {
								$from_block = $game->blockchain->fetch_block_by_id($game->current_events[$i]->db_event['event_starting_block']);
								$initial_price = $app->currency_price_after_time($option['currency_id'], $btc_currency['currency_id'], $from_block['time_mined']);
								$final_price = $app->currency_price_at_time($option['currency_id'], $btc_currency['currency_id'], time());
								if ($initial_price['price'] > 0) $final_performance = round(pow(10,8)*$final_price['price']/$initial_price['price'])/pow(10,8) - 1;
								else $final_performance = 0;
							}
						}
						$api_stat['price_performance'] = $final_performance;
						array_push($api_event['options'], $api_stat);
					}
					array_push($current_events, $api_event);
				}
				$output_game['current_events'] = $current_events;
				
				$api_output = array('status_code'=>1, 'status_message'=>"Successful", 'game'=>$output_game, 'user_info'=>$api_user_info);
			}
			else {
				$api_output = array('status_code'=>0, 'status_message'=>'Error, URL not recognized');
			}
		}
		else {
			$api_output = array('status_code'=>0, 'status_message'=>'Error: Invalid game ID');
		}
		echo json_encode($api_output, JSON_PRETTY_PRINT);
	}
	else if ($uri == "/api/") {
		header("Location: /api/about");
		die();
	}
	else {
		echo json_encode(array('status_code'=>0, 'status_message'=>"You've reached an invalid URL."));
	}
}
?>