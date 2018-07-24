<?php
ini_set('memory_limit', '1024M');
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if (empty($uri_parts[4])) $explore_mode = "";
else $explore_mode = $uri_parts[4];
if ($explore_mode == "tx") $explore_mode = "transactions";

if (empty($uri_parts[3])) $game_identifier = "";
else $game_identifier = $uri_parts[3];

$game = false;
$blockchain = false;
$user_game = false;

if ($uri_parts[2] == "games") {
	$game_q = "SELECT * FROM games WHERE url_identifier=".$app->quote_escape($game_identifier)." AND (game_status IN ('running','completed','published'));";
	$game_r = $app->run_query($game_q);

	if ($game_r->rowCount() == 1) {
		$db_game = $game_r->fetch();
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		if ($thisuser) {
			$qq = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
			$rr = $app->run_query($qq);
			
			if ($rr->rowCount() > 0) {
				$user_game = $rr->fetch();
			}
		}
	}
}
else if ($uri_parts[2] == "blockchains") {
	$blockchain_identifier = $game_identifier;
	$db_blockchain = $app->fetch_blockchain_by_identifier($blockchain_identifier);
	
	if ($db_blockchain) {
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		
		if (rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer/blockchains/".$db_blockchain['url_identifier']) {
			header("Location: /explorer/blockchains/".$db_blockchain['url_identifier']."/blocks/");
			die();
		}
	}
}

if (!empty($blockchain) && $blockchain->db_blockchain['p2p_mode'] == "rpc") {
	$coin_rpc = new jsonRPCClient('http://'.$blockchain->db_blockchain['rpc_username'].':'.$blockchain->db_blockchain['rpc_password'].'@127.0.0.1:'.$blockchain->db_blockchain['rpc_port'].'/');
}

if (rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer") $explore_mode = "explorer_home";
else if ($game && rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer/games/".$game->db_game['url_identifier']) $explore_mode = "game_home";
else if (!$game && $blockchain && rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']) $explore_mode = "blockchain_home";

if ($explore_mode == "explorer_home" || ($blockchain && !$game && in_array($explore_mode, array('blockchain_home','blocks','addresses','transactions','utxos','utxo'))) || ($game && in_array($explore_mode, array('game_home','events','blocks','addresses','transactions','utxos','utxo','my_bets','definition')))) {
	if ($game) {
		$last_block_id = $blockchain->last_block_id();
		$current_round = $game->block_to_round($last_block_id+1);
	}
	
	$round = false;
	$block = false;
	$address = false;
	
	$mode_error = true;
	
	if ($explore_mode == "explorer_home") {
		$mode_error = false;
		$pagetitle = $GLOBALS['coin_brand_name']." - Please select a game";
	}
	else if ($explore_mode == "game_home") {
		$mode_error = false;
		$pagetitle = $game->db_game['name']." Block Explorer";
	}
	else if ($explore_mode == "blockchain_home") {
		$mode_error = false;
		$pagetitle = $blockchain->db_blockchain['blockchain_name']." Block Explorer";
	}
	else if ($explore_mode == "my_bets") {
		$mode_error = false;
		$pagetitle = "My bets in ".$game->db_game['name'];
	}
	else if ($explore_mode == "events") {
		$event_status = "";
		$event_id = $uri_parts[5];
		
		if ($event_id == '0') {
			$mode_error = false;
			$pagetitle = $game->db_game['name']." - Initial Distribution";
			$explore_mode = "initial";
		}
		else {
			if ($event_id === "") {
				$mode_error = false;
				$pagetitle = "Results - ".$game->db_game['name'];
			}
			else {
				$event_index = (int) $event_id-1;
				$q = "SELECT * FROM events WHERE event_index='".$event_index."' AND game_id='".$game->db_game['game_id']."';";
				$r = $app->run_query($q);
				
				if ($r->rowCount() == 1) {
					$db_event = $r->fetch();
					$event = new Event($game, false, $db_event['event_id']);
					
					$q = "SELECT e.event_starting_block, e.event_final_block, o.*, op.*, t.amount FROM event_outcomes o JOIN events e ON o.event_id=e.event_id LEFT JOIN options op ON o.winning_option_id=op.option_id LEFT JOIN transactions t ON o.payout_transaction_id=t.transaction_id WHERE e.event_id=".$db_event['event_id'].";";
					$r = $app->run_query($q);
					
					$pagetitle = "Results: ".$event->db_event['event_name'];
					
					if ($r->rowCount() > 0) {
						$db_event = $r->fetch();
						$mode_error = false;
						$event_status = "completed";
					}
					else {
						$last_block_id = $game->blockchain->last_block_id();
						$current_round = $game->block_to_round($last_block_id+1);
						$mode_error = false;
						$event_status = "current";
					}
				}
				else {
					$mode_error = true;
					$pagetitle = $game->db_game['name']." - Failed to load event";
				}
			}
		}
	}
	else if ($explore_mode == "addresses") {
		$address_text = $uri_parts[5];
		
		$q = "SELECT * FROM addresses WHERE address=".$app->quote_escape($address_text).";";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$address = $r->fetch();
			$mode_error = false;
			if ($game) $pagetitle = $game->db_game['name'];
			else $pagetitle = $blockchain->db_blockchain['blockchain_name'];
			$pagetitle .= " Address: ".$address['address'];
		}
	}
	else if ($explore_mode == "blocks") {
		$block_id_str = $uri_parts[5];
		if ($block_id_str !== "0" && (empty($block_id_str) || strpos($block_id_str, '?') !== false)) {
			$mode_error = false;
			if ($game) $pagetitle = $game->db_game['name']." - List of blocks";
			else $pagetitle = $blockchain->db_blockchain['blockchain_name']." - List of blocks";
		}
		else {
			$block_id = (int) $block_id_str;
			$q = "SELECT * FROM blocks WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND block_id='".$block_id."';";
			$r = $app->run_query($q);
			
			if ($r->rowCount() == 1) {
				$block = $r->fetch();
				$mode_error = false;
				
				if ($game) $pagetitle = $game->db_game['name'];
				else $pagetitle = $blockchain->db_blockchain['blockchain_name'];
				$pagetitle .= " Block #".$block['block_id'];
			}
			else {
				$q = "SELECT * FROM blocks WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND block_hash=".$app->quote_escape($uri_parts[5]).";";
				$r = $app->run_query($q);
				
				if ($r->rowCount() == 1) {
					$block = $r->fetch();
					$mode_error = false;
					if ($game) $pagetitle = $game->db_game['name'];
					else $pagetitle = $blockchain->db_blockchain['blockchain_name'];
					$pagetitle .= " Block #".$block['block_id'];
				}
			}
		}
	}
	else if ($explore_mode == "utxo") {
		$io_id = (int) $uri_parts[5];
		
		$io_q = "SELECT * FROM transaction_ios WHERE io_id='".$io_id."';";
		$io_r = $app->run_query($io_q);
		
		if ($io_r->rowCount() > 0) {
			$io = $io_r->fetch();
			$mode_error = false;
			$pagetitle = "UTXO #".$io['io_id'];
			if ($game) $pagetitle .= " - ".$game->db_game['name']." Explorer";
			else $pagetitle .= " - ".$blockchain->db_blockchain['blockchain_name']." Explorer";
		}
		else {
			$io = false;
			$mode_error = true;
		}
	}
	else if ($explore_mode == "transactions") {
		if ($uri_parts[5] == "unconfirmed") {
			$explore_mode = "unconfirmed";
			$mode_error = false;
			$pagetitle = "Unconfirmed Transactions";
			if ($game) $pagetitle .= " - ".$game->db_game['name'];
			else $pagetitle .= " - ".$blockchain->db_blockchain['blockchain_name'];
		}
		else {
			if (strlen($uri_parts[5]) < 15) {
				$tx_id = intval($uri_parts[5]);
				$q = "SELECT * FROM transactions WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND transaction_id='".$tx_id."';";
			}
			else {
				$tx_hash = $uri_parts[5];
				$q = "SELECT * FROM transactions WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND tx_hash=".$app->quote_escape($tx_hash).";";
			}
			$r = $app->run_query($q);
			
			if ($r->rowCount() == 1) {
				$transaction = $r->fetch();
				$mode_error = false;
				
				if ($game) $pagetitle = $game->db_game['name'];
				else $pagetitle = $blockchain->db_blockchain['blockchain_name'];
				$pagetitle = " Transaction: ".$transaction['tx_hash'];
			}
			else if ($r->rowCount() == 0) {
				if (!empty($coin_rpc) && !empty($blockchain) && !empty($tx_hash)) {
					$successful = false;
					$blockchain->add_transaction($coin_rpc, $tx_hash, false, true, $successful, false, false, false);
					
					$q = "SELECT * FROM transactions WHERE tx_hash=".$app->quote_escape($tx_hash).";";
					$r = $app->run_query($q);
					
					if ($r->rowCount() == 1) {
						$transaction = $r->fetch();
						$mode_error = false;
						
						if ($game) $pagetitle = $game->db_game['name'];
						else $pagetitle = $blockchain->db_blockchain['blockchain_name'];
						$pagetitle = " Transaction: ".$transaction['tx_hash'];
					}
				}
			}
		}
	}
	else if ($explore_mode == "utxos") {
		$account = false;
		
		if (!empty($_REQUEST['account_id'])) {
			$account_id = (int) $_REQUEST['account_id'];
			$account_q = "SELECT * FROM currency_accounts WHERE account_id='".$account_id."';";
			$account_r = $app->run_query($account_q);
			
			if ($account_r->rowCount() > 0) {
				$db_account = $account_r->fetch();
				
				if ($thisuser && $thisuser->db_user['user_id'] == $db_account['user_id']) $account = $db_account;
				else echo '<font class="redtext">Error: invalid account ID.</font><br/>';
			}
		}
		
		if ($game) $pagetitle = $game->db_game['name'];
		else $pagetitle = $blockchain->db_blockchain['blockchain_name'];
		
		if ($account) $pagetitle .= " - UTXOs in ".$account['account_name'];
		else $pagetitle .= " - List of UTXOs";
		
		$mode_error = false;
	}
	else if ($explore_mode == "definition") {
		$pagetitle = "Game definition: ".$game->db_game['name'];
		$mode_error = false;
	}
	
	if ($mode_error) $pagetitle = $GLOBALS['coin_brand_name']." - Blockchain Explorer";
	$nav_tab_selected = "explorer";
	include('includes/html_start.php');
	?>
	<div class="container-fluid" style="padding-top: 15px;">
		<?php
		if ($mode_error) {
			echo "1 Error, you've reached an invalid page.";
		}
		else {
			$coins_per_vote = 0;
			
			if ($game) {
				?>
				<script type="text/javascript">
				var games = new Array();
				games.push(new Game(<?php
					echo $game->db_game['game_id'];
					echo ', '.$game->blockchain->last_block_id();
					echo ', '.$game->blockchain->last_transaction_id();
					echo ', "", "'.$game->db_game['payout_weight'].'"';
					echo ', '.$game->db_game['round_length'];
					echo ', 0';
					echo ', "'.$game->db_game['url_identifier'].'"';
					echo ', "'.$game->db_game['coin_name'].'"';
					echo ', "'.$game->db_game['coin_name_plural'].'"';
					echo ', "explorer", "'.$game->event_ids().'"';
				?>));
				</script>
				<?php
				
				if ($game->db_game['inflation'] == "exponential") $coins_per_vote = $game->blockchain->app->coins_per_vote($game->db_game);
			}
			
			if ($blockchain || $game) {
				?>
				<script type="text/javascript">
				var blockchain_id = <?php
				if ($blockchain) echo $blockchain->db_blockchain['blockchain_id'];
				else echo $game->blockchain->db_blockchain['blockchain_id'];
				?>;
				</script>
				<div class="row">
					<div class="col-sm-7 ">
						<ul class="list-inline explorer_nav" id="explorer_nav">
							<?php if ($game) { ?><li><a <?php if ($explore_mode == 'my_bets') echo 'class="selected" '; ?>href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/my_bets/">My Bets</a></li><?php } ?>
							<li><a <?php if ($explore_mode == 'blocks') echo 'class="selected" '; ?>href="/explorer/<?php echo $uri_parts[2]; ?>/<?php
							if ($game) echo $game->db_game['url_identifier'];
							else echo $blockchain->db_blockchain['url_identifier'];
							?>/blocks/">Blocks</a></li>
							<?php if ($game) { ?>
							<li><a <?php if ($explore_mode == 'events') echo 'class="selected" '; ?>href="/explorer/<?php echo $uri_parts[2]; ?>/<?php
							if ($game) echo $game->db_game['url_identifier'];
							else echo $blockchain->db_blockchain['url_identifier'];
							?>/events/">Events</a></li>
							<?php } ?>
							<li><a <?php if ($explore_mode == 'utxos') echo 'class="selected" '; ?>href="/explorer/<?php echo $uri_parts[2]; ?>/<?php
							if ($game) echo $game->db_game['url_identifier'];
							else echo $blockchain->db_blockchain['url_identifier'];
							?>/utxos/">UTXOs</a></li>
							<li><a <?php if ($explore_mode == 'unconfirmed') echo 'class="selected" '; ?>href="/explorer/<?php echo $uri_parts[2]; ?>/<?php
							if ($game) echo $game->db_game['url_identifier'];
							else echo $blockchain->db_blockchain['url_identifier'];
							?>/transactions/unconfirmed/">Unconfirmed TXNs</a></li>
							<?php if ($game && $game->db_game['escrow_address'] != "") { ?>
							<li><a <?php if (($explore_mode == 'addresses' && $address['address'] == $game->db_game['escrow_address']) || ($explore_mode == "transactions" && $transaction['tx_hash'] == $game->db_game['genesis_tx_hash'])) echo 'class="selected" '; ?>href="/explorer/<?php echo $uri_parts[2]; ?>/<?php echo $game->db_game['url_identifier']; ?>/transactions/<?php echo $game->db_game['genesis_tx_hash']; ?>">Genesis</a></li>
							<?php } ?>
							<?php if ($game) { ?>
							<li><a <?php if ($explore_mode == 'definition') echo 'class="selected" '; ?>href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/definition/">Game Definition</a>
							<?php } ?>
						</ul>
					</div>
					<div class="col-sm-4 row-no-padding">
						<input type="text" class="form-control" placeholder="Search..." id="explorer_search" />
					</div>
					<div class="col-sm-1 row-no-padding">
						<button class="btn btn-primary" onclick="explorer_search();">Go</button>
					</div>
				</div>
				<?php
				if ($game) {
					echo "<a class='btn btn-sm btn-primary' href='/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/";
					if (in_array($explore_mode, array('blocks','addresses','transactions','utxos','utxo'))) {
						echo $explore_mode."/";
						if ($explore_mode == "blocks") echo $block['block_id'];
						else if ($explore_mode == "addresses") echo $address['address'];
						else if ($explore_mode == "transactions") echo $transaction['tx_hash'];
						else if ($explore_mode == "utxo") echo $io['io_id'];
						else if ($explore_mode == "utxos") {
							if ($account) echo "?account_id=".$account['account_id'];
						}
						if ($explore_mode != "utxos") echo "/";
					}
					echo "'><i class=\"fas fa-link\"></i> &nbsp; View on ".$game->blockchain->db_blockchain['blockchain_name']."</a>\n";
					?>
					<a href="/wallet/<?php echo $game->db_game['url_identifier']; ?>/" class="btn btn-sm btn-success"><i class="fas fa-play-circle"></i> &nbsp; Play Now</a>
					<?php
				}
			}
			?>
			<div class="panel panel-default" style="margin-top: 15px;">
				<?php
				if ($game) {
					$coins_per_vote = $game->blockchain->app->coins_per_vote($game->db_game);
				}
				else $coins_per_vote = 0;
				
				if ($explore_mode == "events") {
					if (!empty($db_event) || $event_status == "current") {
						if ($event_status == "current") {
							$last_block_id = $game->last_block_id();
							$this_round = $game->block_to_round($last_block_id+1);
							$current_round = $this_round;
						}
						else $this_round = $db_event['round_id'];
						
						echo '<div class="panel-heading"><div class="panel-title">';
						echo $event->db_event['event_name'];
						echo '</div></div>'."\n";
						echo '<div class="panel-body">'."\n";
						
						//if ($event_status == "current") {
							$rankings = $event->round_voting_stats_all();
							$sum_votes = $rankings[0];
							$max_votes = $rankings[1];
							$stats_all = $rankings[2];
							$option_id_to_rank = $rankings[3];
							$confirmed_votes = $rankings[4];
							$unconfirmed_votes = $rankings[5];
							$confirmed_score = $rankings[6];
							$unconfirmed_score = $rankings[7];
							$destroy_score = $rankings[8];
							$unconfirmed_destroy_score = $rankings[9];
							$effective_destroy_score = $rankings[10];
							$unconfirmed_effective_destroy_score = $rankings[11];
							
							$sum_score = $confirmed_score+$unconfirmed_score;
							
							$total_bets = floor($sum_score*$coins_per_vote) + $destroy_score;
							$total_effective_bets = floor($sum_votes*$coins_per_vote) + $effective_destroy_score + $unconfirmed_effective_destroy_score;
						/*}
						else {
							$max_votes = floor($event->event_outcome['sum_votes']*$event->db_event['max_voting_fraction']);
							
							$total_bets = floor($event->event_outcome['sum_score']*$coins_per_vote) + $event->event_outcome['destroy_score'];
							$total_effective_bets = floor($event->event_outcome['sum_votes']*$coins_per_vote) + $event->event_outcome['effective_destroy_score'];
						}*/
						
						if (!empty($game->db_game['module'])) {
							if (method_exists($game->module, "event_index_to_next_event_index")) {
								$next_event_index = $game->module->event_index_to_next_event_index($event->db_event['event_index']);
								
								if ($next_event_index) {
									$next_event_r = $app->run_query("SELECT * FROM events WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$next_event_index."';");
									
									if ($next_event_r->rowCount() > 0) {
										$db_next_event = $next_event_r->fetch();
										echo "<p>The winner ";
										if ($event_status == "current") echo "will advance";
										else echo "has advanced";
										echo " to <a href=\"/explorer/games/".$game->db_game['url_identifier']."/events/".($db_next_event['event_index']+1)."\">".$db_next_event['event_name']."</a></p>\n";
									}
								}
							}
							
							$q = "SELECT * FROM events WHERE game_id='".$game->db_game['game_id']."' AND next_event_index=".$event->db_event['event_index']." ORDER BY event_index ASC;";
							$r = $app->run_query($q);
							
							if ($r->rowCount() > 0) {
								echo "<p>".$r->rowCount()." ";
								if ($r->rowCount() == 1) echo $game->db_game['event_type_name'];
								else echo $game->db_game['event_type_name_plural'];
								echo " contributed to this ".$game->db_game['event_type_name'].".<br/>\n";
								while ($precursor_event = $r->fetch()) {
									echo "<a href=\"/explorer/games/".$game->db_game['url_identifier']."/events/".($precursor_event['event_index']+1)."\">".$precursor_event['event_name']."</a><br/>\n";
								}
								echo "</p>";
							}
						}
						
						if (empty($db_event['winning_option_id'])) {
							echo "<b>No Winner</b><br/>\n";
						}
						else {
							$winner_option = $app->run_query("SELECT * FROM options WHERE option_id='".$db_event['winning_option_id']."';")->fetch();
							echo "<b>Winner: ".$winner_option['name']."</b><br/>\n";
						}
						?>
						<div class="row">
							<div class="col-md-6">
								<div class="row">
									<div class="col-sm-4">Total bets:</div>
									<div class="col-sm-8"><font class="greentext"><?php echo $app->format_bignum($total_bets/pow(10,$game->db_game['decimal_places']))."</font> ".$game->db_game['coin_name_plural']; ?></div>
								</div>
							</div>
						</div>
						<?php
						if ($thisuser && !empty($db_event) && !empty($db_event['winning_option_id'])) {
							$my_votes = false;
							$user_winnings_description = $event->user_winnings_description($thisuser->db_user['user_id'], $this_round, $event_status, $db_event['winning_option_id'], $db_event['winning_votes'], $db_event['name'], $my_votes);
							if (!empty($user_winnings_description)) echo $user_winnings_description."<br/>\n";
						}
						
						if (!empty($db_event)) {
							$q = "SELECT SUM(colored_amount) FROM transaction_game_ios WHERE event_id='".$event->db_event['event_id']."' AND is_coinbase=1;";
							$r = $app->run_query($q);
							$payout_amount = $r->fetch();
							$payout_amount = $payout_amount['SUM(colored_amount)'];
							$payout_disp = $app->format_bignum($payout_amount/pow(10,$game->db_game['decimal_places']));
							echo '<font class="greentext">'.$payout_disp."</font> ";
							if ($payout_disp == '1') echo $game->db_game['coin_name']." was";
							else echo $game->db_game['coin_name_plural']." were";
							echo " paid out to the winners.<br/>\n";
						}
						
						$from_block_id = $db_event['event_starting_block'];
						$to_block_id = $db_event['event_final_block'];
						
						$q = "SELECT * FROM game_blocks WHERE game_id='".$game->db_game['game_id']."' AND block_id >= '".$from_block_id."' AND block_id <= ".$to_block_id." ORDER BY block_id ASC;";
						$r = $app->run_query($q);
						echo "Blocks in this event: ";
						echo "<a href=\"/explorer/games/".$game->db_game['url_identifier']."/blocks/".$from_block_id."\">".$from_block_id."</a> ... <a href=\"/explorer/games/".$game->db_game['url_identifier']."/blocks/".$to_block_id."\">".$to_block_id."</a>";
						?>
						<br/>
						<?php
						$event_next_prev_links = $game->event_next_prev_links($event);
						echo $event_next_prev_links;
						?>
						<br/>
						<a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/events/">See all events</a><br/>
						<br/>
						
						<?php
						if ($game->db_game['module'] == "CoinBattles") {
							if ($event_status == "current") {
								$chart_starting_block = 1+$game->db_game['round_length']*($current_round-1);
								$chart_final_block = false;
							}
							else {
								$chart_starting_block = $event->db_event['event_starting_block'];
								$chart_final_block = $event->db_event['event_final_block'];
							}
							list($html, $js) = $game->module->currency_chart($game, $chart_starting_block, $chart_final_block);
							echo '<div style="margin: 20px 0px;" id="game0_chart_html">'.$html."</div>\n";
							echo '<div id="game0_chart_js"><script type="text/javascript">'.$js.'</script></div>'."\n";
						}
						
						$round_table_html = $event->current_round_table($thisuser, false, false, 0, 0);
						echo $round_table_html;
						/*
						?>
						<h2>Rankings</h2>
						
						<div class="row" style="font-weight: bold;">
						<div class="col-md-3"><?php echo ucwords($event->db_event['option_name']); ?></div>
						<div class="col-md-1" style="text-align: center;">Percent</div>
						</div>
						<?php
						if ($event) {
							$q = "SELECT op.name, eoo.*, en.* FROM options op JOIN event_outcome_options eoo ON op.option_id=eoo.option_id LEFT JOIN entities en ON op.entity_id=en.entity_id WHERE op.event_id='".$event->db_event['event_id']."' ORDER BY eoo.rank ASC;";
							$r = $app->run_query($q);
						}
						else $r = false;
						
						for ($rank=1; $rank<=$event->db_event['num_voting_options']; $rank++) {
							if ($event_status == "current") {
								$ranked_option = $stats_all[$rank-1];
							}
							else {
								$ranked_option = $r->fetch();
							}
							
							if (empty($event) || $ranked_option) {
								if ($event_status == "current") {
									$option_votes = $ranked_option['votes']+$ranked_option['unconfirmed_votes'];
									$effective_bets = $option_votes*$coins_per_vote + $ranked_option['destroy_score'] + $ranked_option['unconfirmed_destroy_score'];
								}
								else {
									$effective_bets = $ranked_option['votes']*$coins_per_vote + $ranked_option['effective_destroy_sum'];
								}
								echo '<div class="row';
								if (!empty($db_event) && $db_event['winning_option_id'] == $ranked_option['option_id']) echo ' greentext';
								echo '">';
								echo '<div class="col-md-3">';
								
								if (!empty($ranked_option['image_url'])) {
									?>
									<div style="display: none;" class="modal fade" id="option_image_modal<?php echo $ranked_option['option_id']; ?>">
										<div class="modal-dialog">
											<div class="modal-content">
												<div class="modal-body">
													<img src="<?php echo $ranked_option['image_url']; ?>" style="max-width: 100%;" />
												</div>
											</div>
										</div>
									</div>
									<a href="" onclick="$('#option_image_modal<?php echo $ranked_option['option_id']; ?>').modal('toggle'); return false;"><i class="fas fa-image"></i></a> &nbsp; 
									<?php
								}
								
								echo $rank.'. '.$ranked_option['name'].'</div>';
								echo '<div class="col-md-1" style="text-align: center;">'.round(100*$effective_bets/$total_effective_bets, 2).'%</div>';
								echo '</div>'."\n";
							}
						}
						*/
						
						if ($event->db_event['option_block_rule'] == "football_match") {
							echo "<br/><h2>Match Summary</h2>\n";
							
							$q = "SELECT * FROM option_blocks ob JOIN options o ON ob.option_id=o.option_id JOIN entities e ON o.entity_id=e.entity_id WHERE o.event_id='".$event->db_event['event_id']."' AND ob.score > 0 ORDER BY ob.option_block_id ASC;";
							$r = $app->run_query($q);
							$scores_by_entity_id = array();
							$entities_by_id = array();
							
							if ($r->rowCount() > 0) {
								while ($option_block = $r->fetch()) {
									if (empty($scores_by_entity_id[$option_block['entity_id']])) {
										$scores_by_entity_id[$option_block['entity_id']] = $option_block['score'];
										$entities_by_id[$option_block['entity_id']] = $option_block;
									}
									else $scores_by_entity_id[$option_block['entity_id']] += $option_block['score'];
									
									echo $option_block['entity_name']." scored in block #".$option_block['block_height']."<br/>\n";
								}
							}
							else echo "No one has scored.<br/>\n";
							
							if (!empty($scores_by_entity_id)) {
								echo "<br/><b>Final Score:</b><br/>\n";
								$winning_entity_id = false;
								foreach ($scores_by_entity_id as $entity_id => $score) {
									echo $entities_by_id[$entity_id]['entity_name'].": ".$score."<br/>\n";
								}
							}
							
							$q = "SELECT *, SUM(ob.score) AS score FROM option_blocks ob JOIN options o ON ob.option_id=o.option_id LEFT JOIN entities e ON o.entity_id=e.entity_id WHERE o.event_id='".$event->db_event['event_id']."' GROUP BY o.option_id ORDER BY o.option_index ASC;";
							$r = $app->run_query($q);
							
							if ($r->rowCount() > 0) {
								$first_option = $r->fetch();
								$second_option = $r->fetch();
								$winning_option = false;
								
								if ($first_option['score'] == $second_option['score']) {
									$tiebreaker = $game->module->break_tie($game, $event->db_event, $first_option, $second_option);
									
									if ($tiebreaker) {
										list($winning_option, $pk_shootout_data) = $tiebreaker;
										
										for ($i=0; $i<count($pk_shootout_data); $i++) {
											echo "<br/><b>PK Shootout #".($i+1)."</b><br/>\n";
											echo $first_option['entity_name'].": ".$pk_shootout_data[$i][0]."<br/>\n";
											echo $second_option['entity_name'].": ".$pk_shootout_data[$i][1]."<br/>\n";
										}
									}
								}
							}
						}
						?>
						<br/>
						<h2>Transactions</h2>
						<div class="transaction_table">
						<?php
						if ($event) {
							$q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE t.blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND t.block_id IS NULL AND gio.event_id='".$event->db_event['event_id']."' GROUP BY t.transaction_id ORDER BY transaction_id ASC;";
							$r = $app->run_query($q);
							
							if ($r->rowCount() > 0) {
								echo "<a href=\"/explorer/games/".$game->db_game['url_identifier']."/transactions/unconfirmed\">Unconfirmed</a>";
								if ($event->db_event['vote_effectiveness_function'] != "constant") {
									echo ", vote effectiveness: ".$event->block_id_to_effectiveness_factor($last_block_id+1);
								}
								echo "<br/>\n";
								
								while ($transaction = $r->fetch()) {
									echo $game->render_transaction($transaction, false, false, $coins_per_vote);
								}
							}
						}
						
						for ($i=$from_block_id; $i<=$to_block_id; $i++) {
							$q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE t.blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND t.block_id='".$i."'";
							if ($event) $q .= " AND gio.event_id='".$event->db_event['event_id']."'";
							else $q .= " AND gio.game_id='".$game->db_game['game_id']."'";
							$q .= " GROUP BY t.transaction_id ORDER BY transaction_id ASC;";
							$r = $app->run_query($q);
							
							if ($r->rowCount() > 0) {
								echo "<a href=\"/explorer/games/".$game->db_game['url_identifier']."/blocks/".$i."\">Block #".$i."</a>";
								if ($event->db_event['vote_effectiveness_function'] != "constant") {
									echo ", vote effectiveness: ".$event->block_id_to_effectiveness_factor($i);
								}
								echo "<br/>\n";
								
								while ($transaction = $r->fetch()) {
									echo $game->render_transaction($transaction, false, false, $coins_per_vote);
								}
							}
						}
						echo '</div>';
						
						echo "<br/>\n";
						echo $event_next_prev_links;
						
						echo "</div>\n";
					}
					else {
						echo '<div class="panel-heading"><div class="panel-title">';
						echo $game->db_game['name'].' Results';
						echo '</div></div>'."\n";
						?>
						<div class="panel-body">
							<div style="border-bottom: 1px solid #bbb; margin-bottom: 5px;" id="render_event_outcomes">
								<div id="event_outcomes_0">
									<?php
									$events_to_block_id = $game->db_game['events_until_block'];
									if ($events_to_block_id > $game->blockchain->last_block_id()) $events_to_block_id = $game->blockchain->last_block_id();
									
									$q = "SELECT * FROM events WHERE game_id='".$game->db_game['game_id']."' AND event_starting_block<=".((int)$events_to_block_id)." ORDER BY event_index DESC LIMIT 1;";
									$r = $app->run_query($q);
									
									if ($r->rowCount() > 0) {
										$db_event = $r->fetch();
										$to_event_index = $db_event['event_index'];
										$from_event_index = max(0, $to_event_index-20);
										$event_outcomes = $game->event_outcomes_html($from_event_index, $to_event_index, $thisuser);
										echo $event_outcomes[1];
									}
									?>
								</div>
							</div>
							<center>
								<a href="" onclick="show_more_event_outcomes(<?php echo $game->db_game['game_id']; ?>); return false;" id="show_more_link">Show More</a>
							</center>
						</div>
						
						<script type="text/javascript">
						$(document).ready(function() {
							last_event_index_shown = <?php echo $from_event_index; ?>;
						});
						</script>
						
						<div style="display: none;" class="modal fade" id="set_event_outcome_modal">
							<div class="modal-dialog">
								<div class="modal-content" id="set_event_outcome_modal_content">
								</div>
							</div>
						</div>
						<?php
					}
				}
				else if ($explore_mode == "blocks" || $explore_mode == "unconfirmed") {
					if ($block || $explore_mode == "unconfirmed") {
						if ($block) {
							if ($game) {
								$round_id = $game->block_to_round($block['block_id']);
								$block_index = $game->block_id_to_round_index($block['block_id']);
								list($num_trans, $block_sum) = $game->block_stats($block);
								$block_sum_disp = $block_sum/pow(10,$game->db_game['decimal_places']);
								
								$game_block_r = $app->run_query("SELECT * FROM game_blocks WHERE game_id='".$game->db_game['game_id']."' AND block_id='".$block['block_id']."';");
								if ($game_block_r->rowCount() > 0) $game_block = $game_block_r->fetch();
								else $game_block = false;
							}
							else {
								list($num_trans, $block_sum) = $blockchain->block_stats($block);
								$block_sum_disp = $block_sum/pow(10,$blockchain->db_blockchain['decimal_places']);
								
								$game_block = false;
							}
							
							echo '<div class="panel-heading"><div class="panel-title">';
							if ($game) echo $game->db_game['name']." block #".$block['block_id'];
							else echo $blockchain->db_blockchain['blockchain_name']." block #".$block['block_id'];
							echo '</div></div>'."\n";
							
							echo '<div class="panel-body">';
							echo "Mined at ".date("Y-m-d g:ia", $block['time_mined'])." (".$app->format_seconds(time()-$block['time_mined'])." ago)<br/>\n";
							
							if (!$game && !empty($block['num_transactions']) && $num_trans != $block['num_transactions']) {
								echo "Loaded ".number_format($num_trans)." / ".number_format($block['num_transactions'])." transactions (".number_format(100*$num_trans/$block['num_transactions'], 2)."%).<br/>\n";
							}
							echo "This block contains ".number_format($num_trans)." transactions totaling ".number_format($block_sum_disp, 2)." ";
							if ($game) echo $game->db_game['coin_name_plural'];
							else echo $blockchain->db_blockchain['coin_name_plural'];
							echo ".<br/>\n";
							
							if ($block['locally_saved'] == 1) {
								$load_time = $block['load_time'];
								if ($game_block) $load_time += $game_block['load_time'];
								
								echo $GLOBALS['coin_brand_name']." took ".number_format($load_time, 2)." seconds to load this block.<br/>\n";
							}
							else {
								$q = "SELECT SUM(load_time) FROM transactions WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND block_id='".$block['block_id']."';";
								$load_time = $app->run_query($q)->fetch()['SUM(load_time)'];
								echo "Still loading... ".number_format($load_time, 2)." seconds elapsed.<br/>\n";
							}
							
							if (empty($game)) {
								$associated_games = $blockchain->associated_games(array("running"));
								if (count($associated_games) > 0) {
									echo "<p>";
									for ($i=0; $i<count($associated_games); $i++) {
										echo "See block #".$block['block_id']." on <a href=\"/explorer/games/".$associated_games[$i]->db_game['url_identifier']."/blocks/".$block['block_id']."\">".$associated_games[$i]->db_game['name']."</a><br/>\n";
									}
									echo "</p>\n";
								}
							}
							
							if ($game) echo '<p><a href="/explorer/games/'.$game->db_game['url_identifier'].'/blocks/">&larr; All Blocks</a></p>';
							else echo '<p><a href="/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/blocks/">&larr; All Blocks</a></p>';
							
							if ($game) {
								$filter_arr = false;
								$events = $game->events_by_block($block['block_id'], $filter_arr);
								echo "<p>This block is referenced in ".count($events)." events<br/>\n";
								for ($i=0; $i<count($events); $i++) {
									echo "<a href=\"/explorer/games/".$game->db_game['url_identifier']."/events/".($events[$i]->db_event['event_index']+1)."\">".$events[$i]->db_event['event_name']."</a><br/>\n";
								}
								echo '</p>';
							}
						}
						else {
							if ($game) {
								$q = "SELECT SUM(gio.colored_amount) FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE t.blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND t.block_id IS NULL;";
								$r = $app->run_query($q);
								$r = $r->fetch(PDO::FETCH_NUM);
								$block_sum = $r[0];
								$block_sum_disp = $block_sum/pow(10,$game->db_game['decimal_places']);
								
								$q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE t.blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND t.block_id IS NULL GROUP BY t.transaction_id;";
								$r = $app->run_query($q);
								$num_trans = $r->rowCount();
							}
							else {
								$q = "SELECT COUNT(*), SUM(amount) FROM transactions WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND block_id IS NULL;";
								$r = $app->run_query($q);
								$r = $r->fetch(PDO::FETCH_NUM);
								$num_trans = $r[0];
								$block_sum = $r[1];
								$block_sum_disp = $block_sum/pow(10,$blockchain->db_blockchain['decimal_places']);
							}
							
							$expected_block_id = $blockchain->last_block_id()+1;
							if ($game) {
								$expected_round_id = $game->block_to_round($expected_block_id);
								$expected_block_index = $game->block_id_to_round_index($expected_block_id);
							}
							
							echo '<div class="panel-heading"><div class="panel-title">';
							if ($game) echo $game->db_game['name'];
							else echo $blockchain->db_blockchain['blockchain_name'];
							echo ': Unconfirmed Transactions</div></div>'."\n";
							
							echo '<div class="panel-body">';
							
							echo $num_trans." transaction";
							if ($num_trans == 1) echo " is";
							else echo "s are";
							echo " awaiting confirmation with a sum of ".number_format($block_sum_disp, 2)." ";
							if ($game) echo $game->db_game['coin_name_plural'];
							else echo $blockchain->db_blockchain['coin_name_plural'];
							echo ".<br/>\n";
							echo "Block #".$expected_block_id." is currently being mined.<br/>\n";
						}
						
						if ($game) $next_prev_links = $game->block_next_prev_links($block, $explore_mode);
						else $next_prev_links = $blockchain->block_next_prev_links($block, $explore_mode);
						
						echo $next_prev_links;
						
						echo '<div style="margin-top: 10px; border-bottom: 1px solid #bbb;">';
						
						$q = "SELECT * FROM transactions t";
						if ($game) $q .= " JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id";
						$q .= " WHERE t.blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND t.block_id";
						if ($explore_mode == "unconfirmed") $q .= " IS NULL";
						else $q .= "='".$block['block_id']."'";
						if ($game) $q .= " AND gio.game_id='".$game->db_game['game_id']."'";
						$q .= " AND t.amount > 0";
						if ($game) $q .= " GROUP BY t.transaction_id";
						$q .= " ORDER BY t.position_in_block ASC;";
						$r = $app->run_query($q);
						
						while ($transaction = $r->fetch()) {
							if ($game) echo $game->render_transaction($transaction, false, false, $coins_per_vote);
							else echo $blockchain->render_transaction($transaction, false, false);
						}
						echo '</div>';
						echo "<br/>\n";
						
						echo $next_prev_links;
						?>
						<br/><br/>
						<a href="" onclick="$('#block_info').toggle('fast'); return false;">See block details</a><br/>
						<pre id="block_info" style="display: none;"><?php
						print_r($block);
						
						if (!empty($coin_rpc)) {
							$rpc_block = $coin_rpc->getblock($block['block_hash']);
							if ($rpc_block) echo print_r($rpc_block);
						}
						?>
						</pre>
						<?php
						echo "</div>\n";
					}
					else {
						$blocks_per_section = 40;
						$last_block_id = $blockchain->last_block_id();
						if ($game) $last_block_id = $game->last_block_id();
						$complete_block_id = $blockchain->last_complete_block_id();
						if ($game) $complete_block_id = $last_block_id;
						
						$filter_complete = false;
						if (!empty($_REQUEST['block_filter']) && $_REQUEST['block_filter'] == "complete") {
							$to_block_id = $complete_block_id+1;
							$filter_complete = true;
						}
						else $to_block_id = $last_block_id;
						if ($to_block_id === false) $to_block_id = 0;
						
						$from_block_id = $to_block_id-$blocks_per_section+1;
						if ($from_block_id < 0) $from_block_id = 0;
						?>
						<script type="text/javascript">
						var explorer_blocks_per_section = <?php echo $blocks_per_section; ?>;
						var explorer_block_list_sections = 1;
						var explorer_block_list_from_block = <?php echo $from_block_id; ?>;
						var filter_complete = <?php if ($filter_complete) echo "1"; else echo "0"; ?>;
						
						function explorer_block_list_show_more() {
							explorer_block_list_from_block = explorer_block_list_from_block-explorer_blocks_per_section;
							explorer_block_list_sections++;
							var section = explorer_block_list_sections;
							$('#explorer_block_list').append('<div id="explorer_block_list_'+section+'">Loading...</div>');
							
							var block_list_url = "/ajax/explorer_block_list.php?blockchain_id="+blockchain_id;
							<?php if ($game) echo 'block_list_url += "&game_id='.$game->db_game['game_id'].'";'."\n"; ?>
							block_list_url += "&from_block="+explorer_block_list_from_block+"&blocks_per_section="+explorer_blocks_per_section+"&filter_complete="+filter_complete;
							
							$.get(block_list_url, function(html) {
								$('#explorer_block_list_'+section).html(html);
							});
						}
						</script>
						<?php
						if ($game) {
							echo '<div class="panel-heading"><div class="panel-title">';
							echo $game->db_game['name']." Blocks";
							echo "</div></div>\n";
							
							echo '<div class="panel-body">';
							
							echo '<div id="explorer_block_list" style="margin-bottom: 15px;">';
							echo '<div id="explorer_block_list_0">';
							echo $game->explorer_block_list($from_block_id, $to_block_id, false);
							echo '</div>';
							echo '</div>';
							
							echo '<a href="" onclick="explorer_block_list_show_more(); return false;">Show More</a>';
							
							echo '</div>';
						}
						else {
							$recent_block = $blockchain->most_recently_loaded_block();
							
							echo '<div class="panel-heading"><div class="panel-title">';
							echo $blockchain->db_blockchain['blockchain_name']." Blocks";
							echo "</div></div>\n";
							
							echo '<div class="panel-body">';
							
							echo "<p>".$blockchain->db_blockchain['blockchain_name']." is synced up to block <a href=\"/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/blocks/".$complete_block_id."\">#".$complete_block_id."</a></p>\n";
							
							if (!empty($recent_block)) {
								echo "<p>Last block loaded was <a href=\"/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/blocks/".$recent_block['block_id']."\">#".$recent_block['block_id']."</a> (loaded ".$app->format_seconds(time()-$recent_block['time_loaded'])." ago)</p>\n";
							}
							
							$pending_blocks_q = "SELECT COUNT(*) FROM blocks WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND locally_saved=0";
							if (!empty($blockchain->db_blockchain['first_required_block'])) $pending_blocks_q .= " AND block_id > ".$blockchain->db_blockchain['first_required_block'];
							$pending_blocks_q .= ";";
							$pending_blocks = $app->run_query($pending_blocks_q)->fetch();
							$pending_blocks = $pending_blocks['COUNT(*)'];
							
							if ($pending_blocks > 0) {
								$loadtime_q = "SELECT COUNT(*), SUM(load_time) FROM blocks WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND locally_saved=1;";
								$loadtime_r = $app->run_query($loadtime_q);
								$loadtime = $loadtime_r->fetch();
								$avg_loadtime = $loadtime['SUM(load_time)']/$loadtime['COUNT(*)'];
								$sec_left = round($avg_loadtime*$pending_blocks);
								echo "<p>".number_format($pending_blocks)." blocks haven't loaded yet (".$app->format_seconds($sec_left)." left)</p>\n";
							}
							
							$associated_games = $blockchain->associated_games(array("running"));
							if (count($associated_games) > 0) {
								echo "<p>";
								echo count($associated_games)." game";
								if (count($associated_games) == 1) echo " is";
								else echo "s are";
								echo " currently running on this blockchain.<br/>\n";
								
								for ($i=0; $i<count($associated_games); $i++) {
									echo "<a href=\"/explorer/games/".$associated_games[$i]->db_game['url_identifier']."/events/\">".$associated_games[$i]->db_game['name']."</a><br/>\n";
								}
								echo "</p>\n";
							}
							?>
							<div class="row">
								<div class="col-sm-6">
									<p>
										<select class="form-control" name="block_filter" onchange="window.location='/<?php echo $uri_parts[1]."/".$uri_parts[2]."/".$uri_parts[3]."/".$uri_parts[4]; ?>/?block_filter='+$(this).val();">
											<option value="">All blocks</option>
											<option <?php if ($filter_complete) echo 'selected="selected" '; ?>value="complete">Fully loaded blocks only</option>
										</select>
									</p>
								</div>
							</div>
							<div id="explorer_block_list" style="margin-bottom: 15px;">
								<div id="explorer_block_list_0">
									<?php
									$ref_game = false;
									echo $blockchain->explorer_block_list($from_block_id, $to_block_id, $ref_game, $filter_complete);
									?>
								</div>
							</div>
							<a href="" onclick="explorer_block_list_show_more(); return false;">Show More</a>
							<br/>
							<?php
							echo '</div>';
						}
					}
				}
				else if ($explore_mode == "addresses") {
					echo '<div class="panel-heading"><div class="panel-title">';
					if ($game) echo $game->db_game['name'];
					else echo $blockchain->db_blockchain['blockchain_name'];
					echo " Address: ".$address['address'];
					echo "</div></div>\n";
					
					echo '<div class="panel-body">';
					
					echo '<img style="margin: 10px;" src="/render_qr_code.php?data='.$address['address'].'" />';
					
					if ($thisuser) {
						$account_q = "SELECT * FROM currency_accounts a JOIN address_keys k ON a.account_id=k.account_id WHERE k.address_id='".$address['address_id']."' AND a.user_id='".$thisuser->db_user['user_id']."';";
						$account_r = $app->run_query($account_q);
						
						if ($account_r->rowCount() > 0) {
							$account = $account_r->fetch();
							
							echo '<p>This address is in your account <a href="/accounts/?account_id='.$account['account_id'].'">'.$account['account_name'].'</a></p>';
						}
					}
					
					if (empty($game)) {
						$address_associated_games = $blockchain->games_by_address($address);
						echo "<p>This address is associated with ".count($address_associated_games)." game";
						if (count($address_associated_games) != 1) echo "s";
						echo "</p>\n";
						
						for ($i=0; $i<count($address_associated_games); $i++) {
							$db_game = $address_associated_games[$i];
							echo '<p><a href="/explorer/games/'.$db_game['url_identifier'].'/addresses/'.$address['address'].'/">'.$db_game['name']."</a></p>\n";
						}
					}
					
					$q = "SELECT * FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.create_transaction_id WHERE t.blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND i.address_id='".$address['address_id']."' GROUP BY t.transaction_id ORDER BY t.transaction_id ASC;";
					$r = $app->run_query($q);
					
					$transaction_ids = array();
					$transaction_ios = array();
					while ($transaction_io = $r->fetch()) {
						array_push($transaction_ids, $transaction_io['transaction_id']);
						array_push($transaction_ios, $transaction_io);
					}
					
					$q = "SELECT * FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.spend_transaction_id WHERE t.blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND i.address_id='".$address['address_id']."' GROUP BY t.transaction_id ORDER BY t.transaction_id ASC;";
					$r = $app->run_query($q);
					
					while ($transaction_io = $r->fetch()) {
						if (!in_array($transaction_io['transaction_id'], $transaction_ids)) {
							array_push($transaction_ids, $transaction_io['transaction_id']);
							array_push($transaction_ios, $transaction_io);
						}
					}
					
					echo "<p>This address has been used in ".count($transaction_ios)." transactions.</p>\n";
					
					echo "<p>".ucwords($blockchain->db_blockchain['coin_name'])." balance: ".($blockchain->address_balance_at_block($address, false)/pow(10,$blockchain->db_blockchain['decimal_places']))." ".$blockchain->db_blockchain['coin_name_plural']."</p>\n";
					
					if ($game) echo "<p>".ucwords($game->db_game['coin_name'])." balance: ".$app->format_bignum($game->address_balance_at_block($address, false)/pow(10,$game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']."</p>\n";
					
					?>
					<div style="border-bottom: 1px solid #bbb;">
						<?php
						for ($i=0; $i<count($transaction_ios); $i++) {
							if ($game) echo $game->render_transaction($transaction_ios[$i], $address['address_id'], false, $coins_per_vote);
							else echo $blockchain->render_transaction($transaction_ios[$i], $address['address_id'], false);
						}
						?>
					</div>
					
					<br/>
					<?php
					$permission_to_claim_address = $app->permission_to_claim_address($thisuser, $address);
					
					if ($permission_to_claim_address) {
						if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "claim") {
							?>
							<script type="text/javascript">
							$(document).ready(function() {
								try_claim_address(<?php
								echo $blockchain->db_blockchain['blockchain_id'].", ";
								if ($game) echo $game->db_game['game_id'];
								else echo "false";
								echo ", ";
								echo $address['address_id'];
								?>);
							});
							</script>
							<?php
						}
						?>
						<button class="btn btn-success btn-sm" onclick="try_claim_address(<?php
						echo $blockchain->db_blockchain['blockchain_id'].", ";
						if ($game) echo $game->db_game['game_id'];
						else echo "false";
						echo ", ";
						echo $address['address_id'];
						?>);">Claim this address</button>
						<?php
					}
					echo "</div>\n";
				}
				else if ($explore_mode == "initial") {
					$q = "SELECT * FROM transactions WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND block_id=0 AND amount > 0 ORDER BY transaction_id ASC;";
					$r = $app->run_query($q);
					
					echo '<div class="panel-body">'."\n";
					echo '<div style="border-bottom: 1px solid #bbb;">'."\n";
					while ($transaction = $r->fetch()) {
						echo $blockchain->render_transaction($transaction, false, false);
					}
					echo '</div>'."\n";
					echo '</div>'."\n";
				}
				else if ($explore_mode == "transactions") {
					$rpc_transaction = false;
					$rpc_raw_transaction = false;
					
					if ($blockchain->db_blockchain['p2p_mode'] == "rpc") {
						try {
							$rpc_transaction = $coin_rpc->gettransaction($transaction['tx_hash']);
						}
						catch (Exception $e) {}
						
						try {
							$rpc_raw_transaction = $coin_rpc->getrawtransaction($transaction['tx_hash']);
							$rpc_raw_transaction = $coin_rpc->decoderawtransaction($rpc_raw_transaction);
						}
						catch (Exception $e) {}
					}
					
					echo '<div class="panel-heading"><div class="panel-title">';
					echo $blockchain->db_blockchain['blockchain_name']." Transaction: ".$transaction['tx_hash'];
					echo "</div></div>\n";
					
					echo '<div class="panel-body">';
					
					if (empty($game)) {
						$tx_associated_games = $blockchain->games_by_transaction($transaction);
						echo "<p>This transaction is associated with ".count($tx_associated_games)." game";
						if (count($tx_associated_games) != 1) echo "s";
						echo "</p>\n";
						
						for ($i=0; $i<count($tx_associated_games); $i++) {
							$db_game = $tx_associated_games[$i];
							echo '<p><a href="/explorer/games/'.$db_game['url_identifier'].'/transactions/'.$transaction['tx_hash'].'/">'.$db_game['name']."</a></p>\n";
						}
					}
					
					if ($game && $transaction['block_id'] > 0) {
						$block_index = $game->block_id_to_round_index($transaction['block_id']);
						$round_id = $game->block_to_round($transaction['block_id']);
					}
					else {
						$block_index = false;
						$round_id = false;
					}
					echo "This transaction has ".(int) $transaction['num_inputs']." inputs and ".(int) $transaction['num_outputs']." outputs totalling ".$app->format_bignum($transaction['amount']/pow(10,$blockchain->db_blockchain['decimal_places']))." ".$blockchain->db_blockchain['coin_name_plural'].". ";
					if ($game) {
						$coins_in = $game->transaction_coins_in($transaction['transaction_id']);
						$coins_out = $game->transaction_coins_out($transaction['transaction_id'], true);
						echo $app->format_bignum($coins_in/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']." in, ".$app->format_bignum($coins_out/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']." out. ";
					}
					echo "Loaded in ".number_format($transaction['load_time'], 2)." seconds.";
					echo "<br/>\n";
					
					echo '<div style="margin-top: 10px; border-bottom: 1px solid #bbb;">';
					if ($game) echo $game->render_transaction($transaction, false, false, $coins_per_vote);
					else echo $blockchain->render_transaction($transaction, false, false);
					echo "</div>\n";
					
					if ($rpc_transaction || $rpc_raw_transaction) {
						?>
						<br/>
						<a href="" onclick="$('#transaction_info').toggle('fast'); return false;">See transaction details</a><br/>
						<pre id="transaction_info" style="display: none;"><?php
						print_r($transaction);
						echo "<br/>\n";
						if ($rpc_transaction) echo print_r($rpc_transaction);
						if ($rpc_raw_transaction) echo print_r($rpc_raw_transaction);
						?></pre>
						<?php
					}
					
					echo "</div>\n";
				}
				else if ($explore_mode == "utxo") {
					$create_q = "SELECT * FROM transactions WHERE transaction_id='".$io['create_transaction_id']."';";
					$create_r = $app->run_query($create_q);
					
					if ($create_r->rowCount() > 0) $create_tx = $create_r->fetch();
					else $create_tx = false;
					
					if (!empty($io['spend_transaction_id'])) {
						$spend_q = "SELECT * FROM transactions WHERE transaction_id='".$io['spend_transaction_id']."';";
						$spend_r = $app->run_query($spend_q);
						
						if ($spend_r->rowCount() > 0) $spend_tx = $spend_r->fetch();
						else $spend_tx = false;
					}
					else $spend_tx = false;
					
					echo '<div class="panel-heading"><div class="panel-title">';
					echo $pagetitle;
					echo "</div></div>\n";
					
					echo '<div class="panel-body">';
					
					if ($thisuser) {
						$account_q = "SELECT * FROM currency_accounts a JOIN address_keys k ON a.account_id=k.account_id WHERE k.address_id='".$io['address_id']."' AND a.user_id='".$thisuser->db_user['user_id']."';";
						$account_r = $app->run_query($account_q);
						
						if ($account_r->rowCount() > 0) {
							$account = $account_r->fetch();
							
							echo '<p>This UTXO is in your account <a href="/explorer/';
							if ($game) echo 'games/'.$game->db_game['url_identifier'];
							else echo 'blockchains/'.$blockchain->db_blockchain['url_identifier'];
							echo '/utxos/?account_id='.$account['account_id'].'">'.$account['account_name'].'</a></p>';
						}
					}
					
					if ($create_tx || $spend_tx) {
						if (empty($game)) {
							$tx_associated_games = $blockchain->games_by_io($io['io_id']);
							echo "<p>This UTXO is associated with ".count($tx_associated_games)." game";
							if (count($tx_associated_games) != 1) echo "s";
							echo "</p>\n";
							
							for ($i=0; $i<count($tx_associated_games); $i++) {
								$db_game = $tx_associated_games[$i];
								echo '<p><a href="/explorer/games/'.$db_game['url_identifier'].'/utxo/'.$io['io_id'].'/">'.$db_game['name']."</a></p>\n";
							}
						}
						
						echo '<div style="margin-top: 10px; border-bottom: 1px solid #bbb;">';
						if ($create_tx) {
							if ($game) echo $game->render_transaction($create_tx, false, $io['io_id'], $coins_per_vote);
							else echo $blockchain->render_transaction($create_tx, false, $io['io_id']);
						}
						if ($spend_tx) {
							if ($game) echo $game->render_transaction($spend_tx, false, $io['io_id'], $coins_per_vote);
							else echo $blockchain->render_transaction($spend_tx, false, $io['io_id']);
						}
						echo "</div>\n";
					}
					
					echo "</div>\n";
				}
				else if ($explore_mode == "explorer_home") {
					$q = "SELECT * FROM blockchains ORDER BY blockchain_name ASC;";
					$r = $app->run_query($q);
					
					echo '<div class="panel-heading"><div class="panel-title">';
					echo 'Blockchains ('.$r->rowCount().')';
					echo "</div></div>\n";
					
					echo '<div class="panel-body">';
					
					while ($blockchain = $r->fetch()) {
						echo '<a href="/explorer/blockchains/'.$blockchain['url_identifier'].'/blocks/">'.$blockchain['blockchain_name'].'</a><br/>'."\n";
					}
					
					$q = "SELECT * FROM games WHERE game_status IN ('running','published','completed') AND featured=1 ORDER BY game_status ASC;";
					$r = $app->run_query($q);
					$game_id_csv = "";
					$section = "";
					while ($db_game = $r->fetch()) {
						if ($db_game['game_status'] == "completed") $this_section = "completed";
						else $this_section = "running";
						if ($section != $this_section) echo "</div></div>\n<div class=\"panel panel-default\">\n<div class=\"panel-heading\"><div class=\"panel-title\">".ucwords($this_section)." Games</div></div>\n<div class=\"panel-body\">\n";
						$section = $this_section;
						echo '<a href="/explorer/games/'.$db_game['url_identifier'].'/events/">'.$db_game['name']."</a><br/>\n";
					}
					echo "</div>\n";
				}
				else if ($explore_mode == "blockchain_home") {
					echo 'blockchain home';
				}
				else if ($explore_mode == "game_home") {
					echo 'game_home';
				}
				else if ($explore_mode == "utxos") {
					if ($game) {
						$mining_block_id = $game->blockchain->last_block_id()+1;
						$mining_round = $game->block_to_round($mining_block_id);
						
						echo '<div class="panel-heading"><div class="panel-title">';
						if ($account) echo "UTXOs in account: ".$account['account_name'];
						else echo "UTXOs in ".$game->db_game['name'];
						echo "</div></div>\n";
						
						echo '<div class="panel-body">';
						
						if ($account) {
							echo '<p><a href="/accounts/?account_id='.$account['account_id'].'">Manage this Account</a></p>';
							
							$utxo_q = "SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON a.address_id=io.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE gio.game_id='".$game->db_game['game_id']."' AND io.spend_status IN ('unspent','unconfirmed') AND k.account_id='".$account['account_id']."' ORDER BY gio.colored_amount DESC;";
						}
						else {
							$utxo_q = "SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON a.address_id=io.address_id WHERE gio.game_id='".$game->db_game['game_id']."' AND io.spend_status IN ('unspent','unconfirmed') ORDER BY gio.colored_amount DESC;";
						}
						$utxo_r = $app->run_query($utxo_q);
						
						while ($utxo = $utxo_r->fetch()) {
							if ($game->db_game['payout_weight'] == "coin") $votes = $utxo['colored_amount'];
							else if ($game->db_game['payout_weight'] == "coin_block") $votes = $utxo['colored_amount']*($mining_block_id-$utxo['create_block_id']);
							else if ($game->db_game['payout_weight'] == "coin_round") $votes = $utxo['colored_amount']*($mining_round-$utxo['create_round_id']);
							else $votes = 0;
							
							echo '<div class="row">';
							echo '<div class="col-sm-3"><a href="/explorer/games/'.$game->db_game['url_identifier'].'/utxo/'.$utxo['io_id'].'/">'.$app->format_bignum($utxo['colored_amount']/pow(10,$game->db_game['decimal_places'])).' '.$game->db_game['coin_name_plural'].'</a></div>';
							echo '<div class="col-sm-3 greentext text-right">';
							
							if ($game->db_game['inflation'] == "exponential") {
								$coin_equiv = $votes*$coins_per_vote;
								echo "+".$app->format_bignum($coin_equiv/pow(10,$game->db_game['decimal_places'])).' '.$game->db_game['coin_abbreviation'];
							}
							else echo $app->format_bignum($votes).' votes';
							
							echo '</div>';
							
							echo '<div class="col-sm-3">'.$utxo['spend_status']."</div>\n";
							
							echo '<div class="col-sm-3"><a href="/explorer/games/'.$game->db_game['url_identifier'].'/addresses/'.$utxo['address'].'">'.$utxo['address']."</a></div>\n";
							echo '</div>';
						}
					}
					else {
						if ($account) {
							$utxo_q = "SELECT * FROM transaction_ios io JOIN addresses a ON a.address_id=io.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE io.blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND io.spend_status IN ('unspent','unconfirmed') AND k.account_id='".$account['account_id']."' ORDER BY io.amount DESC;";
							$utxo_r = $app->run_query($utxo_q);
							
							echo '<div class="panel-heading"><div class="panel-title">';
							echo "Showing all ".$utxo_r->rowCount()." UTXOs for ".$account['account_name'];
							echo "</div></div>\n";
							
							echo '<div class="panel-body">';
							
							echo '<p><a href="/accounts/?account_id='.$account['account_id'].'">Manage this Account</a></p>';
						}
						else {
							$utxo_count_q = "SELECT COUNT(*) FROM transaction_ios WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND spend_status='unspent';";
							$utxo_count_r = $app->run_query($utxo_count_q);
							$utxo_count = $utxo_count_r->fetch();
							
							$utxo_q = "SELECT * FROM transaction_ios io JOIN addresses a ON a.address_id=io.address_id WHERE io.blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND io.spend_status IN ('unspent','unconfirmed') ORDER BY io.amount DESC LIMIT 500;";
							$utxo_r = $app->run_query($utxo_q);
							
							echo '<div class="panel-heading"><div class="panel-title">';
							echo "Showing the ".$utxo_r->rowCount()." largest ".$blockchain->db_blockchain['blockchain_name']." UTXOs";
							echo "</div></div>\n";
							
							echo '<div class="panel-body">';
							
							echo "<p>".$blockchain->db_blockchain['blockchain_name']." currently has ".number_format($utxo_count['COUNT(*)'])." confirmed, unspent transaction outputs.</p>\n";
						}
						
						while ($utxo = $utxo_r->fetch()) {
							echo '<div class="row">';
							echo '<div class="col-sm-3"><a href="/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/utxo/'.$utxo['io_id'].'">'.$app->format_bignum($utxo['amount']/pow(10,$blockchain->db_blockchain['decimal_places'])).' '.$blockchain->db_blockchain['coin_name_plural'].'</a></div>';
							echo '<div class="col-sm-3">'.$utxo['spend_status']."</div>\n";
							echo '<div class="col-sm-3"><a href="/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/addresses/'.$utxo['address'].'">'.$utxo['address']."</a></div>\n";
							echo '</div>';
						}
					}
					echo "</div>\n";
				}
				else if (!empty($game) && $explore_mode == "my_bets") {
					if (!empty($_REQUEST['user_game_id'])) {
						$q = "SELECT * FROM user_games WHERE user_game_id='".((int)$_REQUEST['user_game_id'])."' AND user_id='".$thisuser->db_user['user_id']."';";
						$r = $app->run_query($q);
						if ($r->rowCount() > 0) $user_game = $r->fetch();
						else $user_game = false;
					}
					else $user_game = $thisuser->ensure_user_in_game($game, false);
					
					if (empty($thisuser)) echo "<br/><br/>\n<p>You must be logged in to view this page. <a href=\"/wallet/".$game->db_game['url_identifier']."/\">Log in</a></p>\n";
					else if (!$user_game) echo "<br/><br/>\n<p>Invalid user game selected.</p>\n";
					else {
						$net_delta = 0;
						$net_stake = 0;
						$num_wins = 0;
						$num_losses = 0;
						
						$q = "SELECT gio.*, io.spend_transaction_id, e.entity_name, eo.winning_option_id, eo.sum_score, eo.sum_votes, eoo.effective_destroy_score AS option_effective_destroy_score, eo.destroy_score AS outcome_destroy_score, o.name AS option_name, gio.votes AS votes, ev.event_index, eoo.votes AS option_votes, gio2.colored_amount AS payout_amount FROM addresses a JOIN address_keys ak ON a.address_id=ak.address_id JOIN currency_accounts ca ON ak.account_id=ca.account_id JOIN user_games ug ON ug.account_id=ca.account_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id JOIN options o ON gio.option_id=o.option_id JOIN entities e ON o.entity_id=e.entity_id JOIN events ev ON o.event_id=ev.event_id JOIN event_outcomes eo ON ev.event_id=eo.event_id JOIN event_outcome_options eoo ON eoo.outcome_id=eo.outcome_id AND eoo.option_id=o.option_id LEFT JOIN transaction_game_ios gio2 ON gio.payout_game_io_id=gio2.game_io_id WHERE gio.game_id=".$game->db_game['game_id']." AND ug.user_game_id=".$user_game['user_game_id']." ORDER BY ev.event_index DESC, gio.game_io_id DESC;";
						$r = $app->run_query($q);
						$num_bets = $r->rowCount();
						
						$bet_table_html = '
						<div class="row">
							<div class="col-sm-2 boldtext">Stake</div>
							<div class="col-sm-2 boldtext">Payout</div>
							<div class="col-sm-1 text-center boldtext">Odds</div>
							<div class="col-sm-2 boldtext">Effectiveness Factor</div>
							<div class="col-sm-2 boldtext">Entity</div>
							<div class="col-sm-3 boldtext">Outcome</div>
						</div>';
						
						while ($bet = $r->fetch()) {
							$expected_payout = ($bet['effective_destroy_amount']*$bet['outcome_destroy_score']/$bet['option_effective_destroy_score'] + ($bet['sum_score']*$coins_per_vote*$bet['votes']/$bet['option_votes']))/pow(10,$game->db_game['decimal_places']);
							$my_stake = ($bet['destroy_amount'] + $bet[$game->db_game['payout_weight']."s_destroyed"]*$coins_per_vote)/pow(10,$game->db_game['decimal_places']);
							
							if ($my_stake > 0) $payout_multiplier = $expected_payout/$my_stake;
							else $payout_multiplier = 0;
							
							$net_stake += $my_stake;
							
							$bet_table_html .= '<div class="row">';
							
							$bet_table_html .= '<div class="col-sm-2 text-right">';
							$bet_table_html .= '<a href="/explorer/games/'.$game->db_game['url_identifier'].'/utxo/'.$bet['io_id'].'/">';
							if ($game->db_game['inflation'] == "exponential") {
								$bet_table_html .= $app->format_bignum($my_stake)." ".$game->db_game['coin_abbreviation'];
							}
							else {
								$bet_table_html .= $app->format_bignum($bet['votes']/pow(10,$game->db_game['decimal_places']))." votes";
							}
							$bet_table_html .= "</a></div>\n";
							
							$bet_table_html .= "<div class=\"col-sm-2 text-right\">";
							$bet_table_html .= $app->format_bignum($expected_payout)." ".$game->db_game['coin_abbreviation'];
							$bet_table_html .= "</div>\n";
							
							$bet_table_html .= "<div class=\"col-sm-1 text-center\">x".$app->format_bignum($payout_multiplier)."</div>\n";
							
							$bet_table_html .= "<div class=\"col-sm-2\">";
							$bet_table_html .= round($bet['effectiveness_factor']*100, 2)."%";
							$bet_table_html .= "</div>\n";
							
							$bet_table_html .= "<div class=\"col-sm-2\"><a target=\"_blank\" href=\"/explorer/games/".$game->db_game['url_identifier']."/events/".($bet['event_index']+1)."\">".$bet['entity_name']."</a></div>\n";
							
							$outcome_txt = "";
							if ($bet['winning_option_id'] == $bet['option_id']) {
								$outcome_txt = "Won";
								$delta = $expected_payout - $my_stake;
								$num_wins++;
							}
							else {
								$outcome_txt = "Lost";
								$delta = (-1)*$my_stake;
								$num_losses++;
							}
							$net_delta += $delta;
							
							$bet_table_html .= "<div class=\"col-sm-3";
							if ($delta >= 0) $bet_table_html .= " greentext";
							else $bet_table_html .= " redtext";
							$bet_table_html .= "\">";
							$bet_table_html .= $outcome_txt." &nbsp;&nbsp; ";
							if ($delta >= 0) $bet_table_html .= "+";
							else $bet_table_html .= "-";
							$bet_table_html .= $app->format_bignum(abs($delta));
							$bet_table_html .= " ".$game->db_game['coin_abbreviation']."</div>\n";
							
							$bet_table_html .= "</div>\n";
						}
						
						$win_rate = $num_wins/($num_wins+$num_losses);
						
						echo '<div class="panel-body">';
						?>
						<div id="change_user_game">
							<select id="select_user_game" class="form-control" onchange="explorer_change_user_game();">
								<?php
								$q = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
								$r = $app->run_query($q);
								while ($db_user_game = $r->fetch()) {
									echo "<option ";
									if ($db_user_game['user_game_id'] == $user_game['user_game_id']) echo "selected=\"selected\" ";
									echo "value=\"".$db_user_game['user_game_id']."\">Account #".$db_user_game['account_id']." &nbsp;&nbsp; ".$app->format_bignum($db_user_game['account_value'])." ".$game->db_game['coin_abbreviation']."</option>\n";
								}
								?>
							</select>
						</div>
						<?php
						echo "You have ".$num_bets." resolved bets for this game totalling <font class=\"greentext\">".$app->format_bignum($net_stake)."</font> ".$game->db_game['coin_name_plural']."<br/>\n";
						echo "You won ".round($win_rate*100, 1)."% of your bets for a net ";
						if ($net_delta >= 0) echo "gain";
						else echo "loss";
						echo " of <font class=\"";
						if ($net_delta >= 0) echo "greentext";
						else echo "redtext";
						echo "\">".$app->format_bignum(abs($net_delta))."</font> ".$game->db_game['coin_name_plural']."<br/>\n";
						
						echo "<br/>\n";
						echo $bet_table_html;
						echo "<br/>\n";
						echo "</div>\n";
					}
				}
				else if ($explore_mode == "definition") {
					$definition_mode = "defined";
					if ($_REQUEST['definition_mode'] == "actual") $definition_mode = "actual";
					
					$game_def = $app->fetch_game_definition($game, $definition_mode);
					$game_def_str = $app->game_def_to_text($game_def);
					$game_def_hash = $app->game_def_to_hash($game_def_str);
					?>
					<div class="panel panel-info">
						<div class="panel-heading">
							<div class="panel-title">Game definition for <?php echo $game->db_game['name']; ?></div>
						</div>
						<div class="panel-body">
							<p>
								<a <?php if ($definition_mode == "defined") echo 'class="selected" '; ?>href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/definition/?definition_mode=defined">As Defined</a>
								 &nbsp;&nbsp; 
								<a <?php if ($definition_mode == "actual") echo 'class="selected" '; ?>href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/definition/?definition_mode=actual">Actual Game</a>
							</p>
							<div class="row">
								<div class="col-sm-2"><label class="form-control-static" for="game_definition_hash">Definition hash:</label></div>
								<div class="col-sm-10"><input type="text" class="form-control" id="game_definition_hash" value="<?php echo $game_def_hash; ?>" /></div>
							</div>
							
							<textarea id="game_definition" style="width: 100%; min-height: 400px; background-color: #f5f5f5; border: 1px solid #cccccc; margin-top: 10px;"><?php echo $game_def_str; ?></textarea>
						</div>
					</div>
					<script type="text/javascript">
					$('#game_definition').dblclick(function() {
						console.log("double clicked the game def");
						$('#game_definition').focus().select();
					});
					</script>
					<?php
				}
			}
			?>
		</div>
	</div>
	<?php
	include('includes/html_stop.php');
}
else {
	$pagetitle = $GLOBALS['coin_brand_name']." - Blockchain Explorer";
	include('includes/html_start.php');
	?>
	<div class="container-fluid">
		Error, you've reached an invalid page.
	</div>
	<?php
	include('includes/html_stop.php');
}
?>
