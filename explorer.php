<?php
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
		
		if ($blockchain->db_blockchain['p2p_mode'] == "rpc") {
			$coin_rpc = new jsonRPCClient('http://'.$blockchain->db_blockchain['rpc_username'].':'.$blockchain->db_blockchain['rpc_password'].'@127.0.0.1:'.$blockchain->db_blockchain['rpc_port'].'/');
		}
		
		if ($thisuser) {
			$qq = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
			$rr = $app->run_query($qq);
			
			if ($rr->rowCount() > 0) {
				$user_game = $rr->fetch();
			}
		}
		
		if ($blockchain->db_blockchain['p2p_mode'] == "none" && !$user_game && $game->db_game['creator_id'] > 0) $game = false;
	}
}
else if ($uri_parts[2] == "blockchains") {
	$blockchain_identifier = $game_identifier;
	$blockchain_q = "SELECT * FROM blockchains WHERE url_identifier='".$blockchain_identifier."';";
	$blockchain_r = $app->run_query($blockchain_q);
	
	if ($blockchain_r->rowCount() > 0) {
		$db_blockchain = $blockchain_r->fetch();
		
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		
		if (rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer/blockchains/".$db_blockchain['url_identifier']) {
			header("Location: /explorer/blockchains/".$db_blockchain['url_identifier']."/blocks/");
			die();
		}
	}
}

if (rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer") $explore_mode = "explorer_home";
else if ($game && rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer/games/".$game->db_game['url_identifier']) $explore_mode = "game_home";
else if (!$game && $blockchain && rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']) $explore_mode = "blockchain_home";

if ($explore_mode == "explorer_home" || ($blockchain && !$game && in_array($explore_mode, array('blockchain_home','blocks','addresses','transactions','utxos'))) || ($game && in_array($explore_mode, array('game_home','events','blocks','addresses','transactions','utxos')))) {
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
	if ($explore_mode == "game_home") {
		$mode_error = false;
		$pagetitle = $game->db_game['name']." Block Explorer";
	}
	if ($explore_mode == "blockchain_home") {
		$mode_error = false;
		$pagetitle = $blockchain->db_blockchain['blockchain_name']." Block Explorer";
	}
	if ($explore_mode == "events") {
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
						$last_block_id = $game->last_block_id();
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
	if ($explore_mode == "addresses") {
		$address_text = $uri_parts[5];
		
		$q = "SELECT * FROM addresses WHERE address=".$app->quote_escape($address_text).";";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$address = $r->fetch();
			$mode_error = false;
			$pagetitle = $blockchain->db_blockchain['blockchain_name']." Address: ".$address['address'];
		}
	}
	if ($explore_mode == "blocks") {
		if ($game && rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer/".$uri_parts[2]."/".$game->db_game['url_identifier']."/blocks") {
			$mode_error = false;
			$pagetitle = $game->db_game['name']." - List of blocks";
		}
		else if (rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer/".$uri_parts[2]."/".$blockchain->db_blockchain['url_identifier']."/blocks") {
			$mode_error = false;
			$pagetitle = $blockchain->db_blockchain['blockchain_name']." - List of blocks";
		}
		else {
			$block_id = intval($uri_parts[5]);
			$q = "SELECT * FROM blocks WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND block_id='".$block_id."';";
			$r = $app->run_query($q);
			if ($r->rowCount() == 1) {
				$block = $r->fetch();
				$mode_error = false;
				$pagetitle = $blockchain->db_blockchain['blockchain_name']." Block #".$block['block_id'];
			}
			else {
				$q = "SELECT * FROM blocks WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND block_hash=".$app->quote_escape($uri_parts[5]).";";
				$r = $app->run_query($q);
				
				if ($r->rowCount() == 1) {
					$block = $r->fetch();
					$mode_error = false;
					$pagetitle = $game->db_game['name']." Block #".$block['block_id'];
				}
			}
		}
	}
	if ($explore_mode == "transactions") {
		if ($uri_parts[5] == "unconfirmed") {
			$explore_mode = "unconfirmed";
			$mode_error = false;
			$pagetitle = $blockchain->db_blockchain['blockchain_name']." - Unconfirmed Transactions";
		}
		else {
			if (strlen($uri_parts[5]) < 20) {
				$tx_id = intval($uri_parts[5]);
				$q = "SELECT * FROM transactions WHERE transaction_id='".$tx_id."';";
			}
			else {
				$tx_hash = $uri_parts[5];
				$q = "SELECT * FROM transactions WHERE tx_hash=".$app->quote_escape($tx_hash).";";
			}
			$r = $app->run_query($q);
			
			if ($r->rowCount() == 1) {
				$transaction = $r->fetch();
				$mode_error = false;
				$pagetitle = $blockchain->db_blockchain['blockchain_name']." Transaction: ".$transaction['tx_hash'];
			}
		}
	}
	if ($explore_mode == "utxos") {
		if ($game) $pagetitle = $game->db_game['name']." - List of UTXOs";
		else $pagetitle = $blockchain->db_blockchain['blockchain_name']." - List of UTXOs";
		$mode_error = false;
	}
	
	if ($mode_error) $pagetitle = $GLOBALS['coin_brand_name']." - Blockchain Explorer";
	$nav_tab_selected = "explorer";
	include('includes/html_start.php');
	
	if ($thisuser && $game) { ?>
		<div class="container" style="max-width: 1000px; padding-top: 10px;">
			<?php
			$account_value = $thisuser->account_coin_value($game);
			include("includes/wallet_status.php");
			?>
		</div>
		<?php
	}
	?>
	<div class="container" style="max-width: 1000px;">
		<br/>
		<?php
		if ($mode_error) {
			echo "Error, you've reached an invalid page.";
		}
		else {
			if ($game) {
				if (empty($thisuser)) $my_last_transaction_id = false;
				else $my_last_transaction_id = $thisuser->my_last_transaction_id($game->db_game['game_id']);
				?>
				<script type="text/javascript">
				var games = new Array();
				games.push(new Game(<?php
					echo $game->db_game['game_id'];
					echo ', '.$game->blockchain->last_block_id();
					echo ', '.$game->blockchain->last_transaction_id().', ';
					if ($my_last_transaction_id) echo $my_last_transaction_id;
					else echo 'false';
					echo ', "';
					if (empty($thisuser)) echo "";
					else echo $game->mature_io_ids_csv($thisuser->db_user['user_id']);
					echo '", "'.$game->db_game['payout_weight'].'"';
					echo ', '.$game->db_game['round_length'];
					$bet_round_range = $game->bet_round_range();
					$min_bet_round = $bet_round_range[0];
					echo ', '.$min_bet_round;
					echo ', 0';
					echo ', "'.$game->db_game['url_identifier'].'"';
					echo ', "'.$game->db_game['coin_name'].'"';
					echo ', "'.$game->db_game['coin_name_plural'].'"';
					echo ', "explorer", "'.$game->event_ids().'"';
				?>));
				</script>
				<?php
			}
			
			if ($blockchain || $game) {
				?>
				<div class="row">
					<div class="col-sm-7 ">
						<ul class="list-inline explorer_nav" id="explorer_nav">
							<li><a<?php if ($explore_mode == 'blocks') echo ' class="selected"'; ?> href="/explorer/<?php echo $uri_parts[2]; ?>/<?php
							if ($game) echo $game->db_game['url_identifier'];
							else echo $blockchain->db_blockchain['url_identifier'];
							?>/blocks/">Blocks</a></li>
							<?php if ($game) { ?>
							<li><a<?php if ($explore_mode == 'events') echo ' class="selected"'; ?> href="/explorer/<?php echo $uri_parts[2]; ?>/<?php
							if ($game) echo $game->db_game['url_identifier'];
							else echo $blockchain->db_blockchain['url_identifier'];
							?>/events/">Events</a></li>
							<?php } ?>
							<li><a<?php if ($explore_mode == 'utxos') echo ' class="selected"'; ?> href="/explorer/<?php echo $uri_parts[2]; ?>/<?php
							if ($game) echo $game->db_game['url_identifier'];
							else echo $blockchain->db_blockchain['url_identifier'];
							?>/utxos/">UTXOs</a></li>
							<?php if ($game && $game->db_game['escrow_address'] != "") { ?>
							<li><a<?php if ($explore_mode == 'addresses' && $address['address'] == $game->db_game['escrow_address']) echo ' class="selected"'; ?> href="/explorer/<?php echo $uri_parts[2]; ?>/<?php echo $game->db_game['url_identifier']; ?>/addresses/<?php echo $game->db_game['escrow_address']; ?>">Escrow</a></li>
							<?php } ?>
							<li><a<?php if ($explore_mode == 'unconfirmed') echo ' class="selected"'; ?> href="/explorer/<?php echo $uri_parts[2]; ?>/<?php
							if ($game) echo $game->db_game['url_identifier'];
							else echo $blockchain->db_blockchain['url_identifier'];
							?>/transactions/unconfirmed/">Unconfirmed Transactions</a></li>
							<?php if ($thisuser && $game) { ?><li><a href="/wallet/<?php echo $game->db_game['url_identifier']; ?>/">My Wallet</a></li><?php } ?>
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
			}
			
			if ($explore_mode == "events") {
				if (!empty($db_event) || $event_status == "current") {
					if ($event_status == "current") {
						$last_block_id = $game->last_block_id();
						$this_round = $game->block_to_round($last_block_id+1);
						$current_round = $this_round;
					}
					else $this_round = $db_event['round_id'];
					
					if ($event_status == "current") {
						$rankings = $event->round_voting_stats_all($current_round);
						$round_sum_votes = $rankings[0];
						$max_votes = $rankings[1];
						$stats_all = $rankings[2];
						$option_id_to_rank = $rankings[3];
						$confirmed_votes = $rankings[4];
						$unconfirmed_votes = $rankings[5];
						
						echo "<h1>".$event->db_event['event_name']." is currently running</h1>\n";
					}
					else {
						if ($db_event['winning_option_id'] > 0) echo "<h1>".$db_event['name']."</h1>\n";
						else echo "<h1>".$event->db_event['event_name'].": No winner</h1>\n";
						
						$max_votes = floor($event->event_outcome['sum_votes']*$event->db_event['max_voting_fraction']);
						$round_sum_votes = $event->event_outcome['sum_votes'];
					}
					
					echo "<h3>".$event->db_event['event_name']."</h3>";
					?>
					<div class="row">
						<div class="col-md-6">
							<div class="row">
								<div class="col-sm-4">Total votes cast:</div>
								<div class="col-sm-8"><?php echo $app->format_bignum($round_sum_votes/pow(10,8)); ?> votes</div>
							</div>
						</div>
					</div>
					<?php
					if ($thisuser && !empty($db_event)) {
						$my_votes = false;
						echo $event->user_winnings_description($thisuser->db_user['user_id'], $this_round, $event_status, $db_event['winning_option_id'], $db_event['winning_votes'], $db_event['name'], $my_votes)."<br/>";
					}
					
					if (!empty($db_event) && $db_event['payout_transaction_id'] > 0) {
						$payout_disp = $app->format_bignum($db_event['amount']/pow(10,8));
						echo '<font class="greentext">'.$payout_disp."</font> ";
						if ($payout_disp == '1') echo $game->db_game['coin_name']." was";
						else echo $game->db_game['coin_name_plural']." were";
						echo " paid out to the winners.<br/>\n";
					}
					$round_fee_q = "SELECT SUM(fee_amount) FROM transactions WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND block_id > ".(($this_round-1)*$game->db_game['round_length'])." AND block_id < ".($this_round*$game->db_game['round_length']).";";
					$round_fee_r = $app->run_query($round_fee_q);
					$round_fees = $round_fee_r->fetch(PDO::FETCH_NUM);
					$round_fees = intval($round_fees[0]);
					
					$fee_disp = $app->format_bignum($round_fees/pow(10,8));
					echo '<font class="redtext">'.$fee_disp."</font> ";
					if ($fee_disp == '1') echo $game->db_game['coin_name']." was";
					else echo $game->db_game['coin_name_plural']." were";
					echo " paid in fees during this round.<br/>\n";
					
					$from_block_id = $db_event['event_starting_block'];
					$to_block_id = $db_event['event_final_block'];
					
					$q = "SELECT * FROM game_blocks WHERE game_id='".$game->db_game['game_id']."' AND block_id >= '".$from_block_id."' AND block_id <= ".$to_block_id." ORDER BY block_id ASC;";
					$r = $app->run_query($q);
					echo "Blocks in this round: ";
					while ($round_block = $r->fetch()) {
						echo "<a href=\"/explorer/games/".$game->db_game['url_identifier']."/blocks/".$round_block['block_id']."\">".$round_block['block_id']."</a> ";
					}
					?>
					<br/>
					<?php
					$event_next_prev_links = $game->event_next_prev_links($event);
					echo $event_next_prev_links;
					?>
					<br/>
					<a href="/explorer/<?php echo $game->db_game['url_identifier']; ?>/events/">See all events</a><br/>
					
					<h2>Rankings</h2>
					
					<div class="row" style="font-weight: bold;">
					<div class="col-md-3"><?php echo ucwords($event->db_event['option_name']); ?></div>
					<div class="col-md-1" style="text-align: center;">Percent</div>
					<div class="col-md-3" style="text-align: center;">Votes</div>
					<?php if ($thisuser) { ?><div class="col-md-3" style="text-align: center;">Your Votes</div><?php } ?>
					</div>
					<?php
					$winner_displayed = FALSE;
					
					if ($event) {
						$q = "SELECT op.* FROM options op JOIN event_outcome_options eoo ON op.option_id=eoo.option_id WHERE op.event_id='".$event->db_event['event_id']."' ORDER BY eoo.rank ASC;";
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
							if (!empty($event)) {
								$option_votes = $event->option_votes_in_round($ranked_option['option_id'], $this_round);
								$option_votes = $option_votes['sum'];
							}
							else {
								$ranked_option = $stats_all[$rank-1];
								$option_votes = $ranked_option['votes']+$ranked_option['unconfirmed_votes'];
							}
							echo '<div class="row';
							if ($option_votes > $max_votes) echo ' redtext';
							else if (!$winner_displayed && $option_votes > 0) { echo ' greentext'; $winner_displayed = TRUE; }
							echo '">';
							echo '<div class="col-md-3">'.$rank.'. '.$ranked_option['name'].'</div>';
							echo '<div class="col-md-1" style="text-align: center;">'.($round_sum_votes>0? round(100*$option_votes/$round_sum_votes, 2) : 0).'%</div>';
							echo '<div class="col-md-3" style="text-align: center;">'.$app->format_bignum($option_votes/pow(10,8)).' votes</div>';
							if ($thisuser) {
								echo '<div class="col-md-3" style="text-align: center;">';
								
								if (!empty($my_votes[$ranked_option['option_id']]['votes'])) $vote_qty = $my_votes[$ranked_option['option_id']]['votes'];
								else $vote_qty = 0;
								
								$vote_disp = $app->format_bignum($vote_qty/pow(10,8));
								echo $vote_disp." ";
								echo " vote";
								if ($vote_disp != '1') echo "s";
								
								echo ' ('.($option_votes>0? round(100*$vote_qty/$option_votes, 3) : 0).'%)</div>';
							}
							echo '</div>'."\n";
						}
					}
					?>
					<br/>
					<h2>Transactions</h2>
					<div class="transaction_table">
					<?php
					for ($i=$from_block_id; $i<=$to_block_id; $i++) {
						echo "<a href=\"/explorer/".$game->db_game['url_identifier']."/blocks/".$i."\">Block #".$i."</a>";
						if ($event->db_event['vote_effectiveness_function'] != "constant") {
							echo ", vote effectiveness: ".$event->block_id_to_effectiveness_factor($i);
						}
						echo "<br/>\n";
						
						$q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.spend_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE t.blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND t.block_id='".$i."' AND gio.game_id='".$game->db_game['game_id']."' AND t.amount > 0 GROUP BY t.transaction_id ORDER BY transaction_id ASC;";
						$r = $app->run_query($q);
						
						while ($transaction = $r->fetch()) {
							echo $game->render_transaction($transaction, FALSE);
						}
					}
					echo '</div>';
					
					echo "<br/>\n";
					echo $event_next_prev_links;
				}
				else {
					?>
					<h1><?php echo $game->db_game['name']; ?> Results</h1>
					<div style="border-bottom: 1px solid #bbb; margin-bottom: 5px;" id="rounds_complete">
						<div id="rounds_complete_0">
							<?php
							$rounds_complete = $game->rounds_complete_html($current_round, 20);
							$last_round_shown = $rounds_complete[0];
							echo $rounds_complete[1];
							?>
						</div>
					</div>
					<center>
						<a href="" onclick="show_more_rounds_complete(); return false;" id="show_more_link">Show More</a>
					</center>
					
					<script type="text/javascript">
					$(document).ready(function() {
						last_round_shown = <?php echo $last_round_shown; ?>;
					});
					</script>
					<?php
				}
				echo "<br/>\n";
			}
			else if ($explore_mode == "blocks" || $explore_mode == "unconfirmed") {
				if ($block || $explore_mode == "unconfirmed") {
					if ($block) {
						if ($game) {
							$round_id = $game->block_to_round($block['block_id']);
							$block_index = $game->block_id_to_round_index($block['block_id']);
							list($num_trans, $block_sum) = $game->block_stats($block);
						}
						else {
							list($num_trans, $block_sum) = $blockchain->block_stats($block);
						}
						
						if ($game) echo "<h1>".$game->db_game['name']." block #".$block['block_id']."</h1>";
						else echo "<h1>".$blockchain->db_blockchain['blockchain_name']." block #".$block['block_id']."</h1>";
						
						if (!$game && !empty($block['num_transactions']) && $num_trans != $block['num_transactions']) {
							echo "Loaded ".number_format($num_trans)." / ".number_format($block['num_transactions'])." transactions (".number_format(100*$num_trans/$block['num_transactions'], 2)."%).<br/>\n";
						}
						echo "This block contains ".number_format($num_trans)." transactions totaling ".number_format($block_sum/pow(10,8), 2)." ";
						if ($game) echo $game->db_game['coin_name_plural'];
						else echo $blockchain->db_blockchain['coin_name_plural'];
						echo ".<br/>\n";
						
						if ($block['locally_saved'] == 1) {
							echo $GLOBALS['coin_brand_name']." took ".number_format($block['load_time'], 2)." seconds to load this block.<br/>\n";
						}
						else {
							$q = "SELECT SUM(load_time) FROM transactions WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND block_id='".$block['block_id']."';";
							$load_time = $app->run_query($q)->fetch()['SUM(load_time)'];
							echo "Still loading... ".number_format($load_time, 2)." seconds elapsed.<br/>\n";
						}
						
						if ($game) echo '<p><a href="/explorer/games/'.$game->db_game['url_identifier'].'/blocks/">&larr; All Blocks</a></p>';
						else echo '<p><a href="/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/blocks/">&larr; All Blocks</a></p>';
						
						if ($game) {
							$events = $game->events_by_block($block['block_id']);
							echo "<p>This block is referenced in ".count($events)." events<br/>\n";
							for ($i=0; $i<count($events); $i++) {
								echo "<a href=\"/explorer/".$game->db_game['url_identifier']."/events/".$events[$i]->db_event['event_index']."\">".$events[$i]->db_event['event_name']."</a><br/>\n";
							}
							echo '</p>';
						}
					}
					else {
						$q = "SELECT COUNT(*), SUM(amount) FROM transactions WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND block_id IS NULL;";// AND amount > 0;";
						$r = $app->run_query($q);
						$r = $r->fetch(PDO::FETCH_NUM);
						$num_trans = $r[0];
						$block_sum = $r[1];
						
						$expected_block_id = $blockchain->last_block_id()+1;
						if ($game) {
							$expected_round_id = $game->block_to_round($expected_block_id);
							$expected_block_index = $game->block_id_to_round_index($expected_block_id);
						}
						echo "<h1>Unconfirmed Transactions</h1>\n";
						if ($game) echo "<h3>".$game->db_game['name']."</h3>";
						echo $num_trans." known transaction";
						if ($num_trans == 1) echo " is";
						else echo "s are";
						echo " awaiting confirmation with a sum of ".number_format($block_sum/pow(10,8), 2)." coins.<br/>\n";
						echo "Block #".$expected_block_id." is currently being mined.<br/>\n";
					}
					
					$next_prev_links = $blockchain->block_next_prev_links($block, $explore_mode);
					echo $next_prev_links;
					
					echo '<div style="margin-top: 10px; border-bottom: 1px solid #bbb;">';
					
					$q = "SELECT * FROM transactions t";
					if ($game) $q .= " JOIN transaction_ios io ON t.transaction_id=io.spend_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id";
					$q .= " WHERE t.blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND t.block_id";
					if ($explore_mode == "unconfirmed") $q .= " IS NULL";
					else $q .= "='".$block['block_id']."'";
					if ($game) $q .= " AND gio.game_id='".$game->db_game['game_id']."'";
					$q .= " AND t.amount > 0";
					if ($game) $q .= " GROUP BY t.transaction_id";
					$q .= " ORDER BY transaction_id ASC;";
					$r = $app->run_query($q);
					
					while ($transaction = $r->fetch()) {
						if ($game) echo $game->render_transaction($transaction, FALSE);
						else echo $blockchain->render_transaction($transaction, FALSE);
					}
					echo '</div>';
					echo "<br/>\n";
					
					echo $next_prev_links;
					?>
					<br/><br/>
					<a href="" onclick="$('#block_info').toggle('fast'); return false;">See block details</a><br/>
					<pre id="block_info" style="display: none;"><?php
					print_r($block);
					
					if ($coin_rpc) {
						$rpc_block = $coin_rpc->getblock($block['block_hash']);
						if ($rpc_block) echo print_r($rpc_block);
					}
					?>
					</pre>
					<br/>
					<?php
				}
				else {
					$blocks_per_section = 40;
					$to_block_id = $blockchain->last_block_id();
					$from_block_id = $to_block_id-$blocks_per_section+1;
					if ($from_block_id < 0) $from_block_id = 0;
					?>
					<script type="text/javascript">
					var explorer_blocks_per_section = <?php echo $blocks_per_section; ?>;
					var explorer_block_list_sections = 1;
					var explorer_block_list_from_block = <?php echo $from_block_id; ?>;
					var blockchain_id = <?php echo $blockchain->db_blockchain['blockchain_id']; ?>;
					
					function explorer_block_list_show_more() {
						explorer_block_list_from_block = explorer_block_list_from_block-explorer_blocks_per_section;
						explorer_block_list_sections++;
						var section = explorer_block_list_sections;
						$('#explorer_block_list').append('<div id="explorer_block_list_'+section+'">Loading...</div>');
						
						var block_list_url = "/ajax/explorer_block_list.php?blockchain_id="+blockchain_id;
						<?php if ($game) echo 'block_list_url += "&game_id='.$game->db_game['game_id'].'";'."\n"; ?>
						block_list_url += "&from_block="+explorer_block_list_from_block+"&blocks_per_section="+explorer_blocks_per_section;
						
						$.get(block_list_url, function(html) {
							$('#explorer_block_list_'+section).html(html);
						});
					}
					</script>
					<?php
					if ($game) {
						echo "<h1>".$game->db_game['name']." - Blocks</h1>";
						
						echo '<div id="explorer_block_list" style="margin-bottom: 15px;">';
						echo '<div id="explorer_block_list_0">';
						echo $game->explorer_block_list($from_block_id, $to_block_id);
						echo '</div>';
						echo '</div>';
						
						echo '<a href="" onclick="explorer_block_list_show_more(); return false;">Show More</a>';
						
						echo "<br/>\n";
					}
					else {
						echo "<h1>".$blockchain->db_blockchain['blockchain_name']." - Blocks</h1>";
						
						echo '<div id="explorer_block_list" style="margin-bottom: 15px;">';
						echo '<div id="explorer_block_list_0">';
						$ref_game = false;
						echo $blockchain->explorer_block_list($from_block_id, $to_block_id, $ref_game);
						echo '</div>';
						echo '</div>';
						
						echo '<a href="" onclick="explorer_block_list_show_more(); return false;">Show More</a>';
						
						echo "<br/>\n";
					}
				}
			}
			else if ($explore_mode == "addresses") {
				echo "<h3>";
				if ($game) echo $game->db_game['name'];
				else echo $blockchain->db_blockchain['blockchain_name'];
				echo " Address: ".$address['address']."</h3>\n";
				
				$q = "SELECT * FROM transactions t, transaction_ios i WHERE i.address_id='".$address['address_id']."' AND (t.transaction_id=i.create_transaction_id OR t.transaction_id=i.spend_transaction_id) GROUP BY t.transaction_id ORDER BY t.transaction_id ASC;";
				$r = $app->run_query($q);
				
				echo "This address has been used in ".$r->rowCount()." transactions.<br/>\n";
				if ($address['is_mine'] == 1) echo "This is one of your addresses.<br/>\n";
				echo ucwords($blockchain->db_blockchain['coin_name'])." balance: ".($blockchain->address_balance_at_block($address, false)/pow(10,8))." ".$blockchain->db_blockchain['coin_name_plural']."<br/>\n";
				if ($game) echo ucwords($game->db_game['coin_name'])." balance: ".$app->format_bignum($game->address_balance_at_block($address, false)/pow(10,8))." ".$game->db_game['coin_name_plural']."<br/>\n";
				
				echo '<div style="border-bottom: 1px solid #bbb;">';
				while ($transaction_io = $r->fetch()) {
					if ($game) echo $game->render_transaction($transaction_io, $address['address_id']);
					else echo $blockchain->render_transaction($transaction_io, $address['address_id']);
				}
				echo "</div>\n";
				
				echo "<br/>\n";
			}
			else if ($explore_mode == "initial") {
				$q = "SELECT * FROM transactions WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND block_id=0 AND amount > 0 ORDER BY transaction_id ASC;";
				$r = $app->run_query($q);
				
				echo '<div style="border-bottom: 1px solid #bbb;">';
				while ($transaction = $r->fetch()) {
					echo $blockchain->render_transaction($transaction, FALSE);
				}
				echo '</div>';
			}
			else if ($explore_mode == "transactions") {
				$rpc_transaction = false;
				$rpc_raw_transaction = false;
				
				if ($game && $game->blockchain->db_blockchain['p2p_mode'] == "rpc") {
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
				
				echo "<h3>".$blockchain->db_blockchain['blockchain_name']." Transaction: ".$transaction['tx_hash']."</h3>\n";
				if ($game && $transaction['block_id'] > 0) {
					$block_index = $game->block_id_to_round_index($transaction['block_id']);
					$round_id = $game->block_to_round($transaction['block_id']);
				}
				else {
					$block_index = false;
					$round_id = false;
				}
				echo "This transaction has ".(int) $transaction['num_inputs']." inputs and ".(int) $transaction['num_outputs']." outputs totalling ".$app->format_bignum($transaction['amount']/pow(10,8))." ".$blockchain->db_blockchain['coin_name_plural'].". ";
				echo "Loaded in ".number_format($transaction['load_time'], 2)." seconds.";
				echo "<br/>\n";
				
				echo '<div style="margin-top: 10px; border-bottom: 1px solid #bbb;">';
				if ($game) echo $game->render_transaction($transaction, false);
				else echo $blockchain->render_transaction($transaction, false);
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
				
				echo "<br/>\n";
			}
			else if ($explore_mode == "explorer_home") {
				$q = "SELECT * FROM blockchains ORDER BY blockchain_name ASC;";
				$r = $app->run_query($q);
				
				echo '<h2>Blockchains ('.$r->rowCount().')</h2>';
				
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
					if ($section != $this_section) echo "<h2>".ucwords($this_section)." Games (".$r->rowCount().")</h2>\n";
					$section = $this_section;
					echo '<a href="/explorer/games/'.$db_game['url_identifier'].'/events/">'.$db_game['name']."</a><br/>\n";
					$game_id_csv .= $db_game['game_id'].",";
				}
				if ($game_id_csv != "") $game_id_csv = substr($game_id_csv, 0, strlen($game_id_csv)-1);
				
				if ($thisuser) {
					$q = "SELECT * FROM games g JOIN user_games ug ON g.game_id=ug.game_id AND ug.user_id='".$thisuser->db_user['user_id']."'";
					$q .= " AND g.game_id NOT IN (".$game_id_csv.")";
					$q .= ";";
					$r = $app->run_query($q);
					while ($db_game = $r->fetch()) {
						echo '<a href="/explorer/games/'.$db_game['url_identifier'].'/">'.$db_game['name']."</a><br/>\n";
					}
				}
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
					
					echo "<h1>UTXOs - ".$game->db_game['name']."</h1>\n";
					
					$utxo_q = "SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON a.address_id=io.address_id WHERE gio.game_id='".$game->db_game['game_id']."' AND io.spend_status IN ('unspent','unconfirmed');";
					$utxo_r = $app->run_query($utxo_q);
					
					while ($utxo = $utxo_r->fetch()) {
						if ($game->db_game['payout_weight'] == "coin") $votes = $utxo['colored_amount'];
						else if ($game->db_game['payout_weight'] == "coin_block") $votes = $utxo['colored_amount']*($mining_block_id-$utxo['create_block_id']);
						else if ($game->db_game['payout_weight'] == "coin_round") $votes = $utxo['colored_amount']*($mining_round-$utxo['create_round_id']);
						else $votes = 0;
						
						echo '<div class="row">';
						echo '<div class="col-sm-3">'.$app->format_bignum($utxo['colored_amount']/pow(10,8)).' '.$game->db_game['coin_name_plural'].'</div>';
						echo '<div class="col-sm-2">'.$app->format_bignum($votes/pow(10,8)).' votes</div>';
						echo '<div class="col-sm-4"><a href="/explorer/games/'.$game->db_game['url_identifier'].'/addresses/'.$utxo['address'].'">'.$utxo['address']."</a></div>\n";
						echo '</div>';
					}
				}
			}
		}
		
		if ($game) { ?>
			<br/>
			<a href="/wallet/<?php echo $game->db_game['url_identifier']; ?>/" class="btn btn-default">Join this game</a>
			<br/>
			<?php
		}
		?>
		<br/>
	</div>
	<?php
	include('includes/html_stop.php');
}
else {
	$pagetitle = $GLOBALS['coin_brand_name']." - Blockchain Explorer";
	include('includes/html_start.php');
	?>
	<div class="container" style="max-width: 1000px;">
		Error, you've reached an invalid page.
	</div>
	<?php
	include('includes/html_stop.php');
}
?>
