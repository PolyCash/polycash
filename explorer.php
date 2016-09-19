<?php
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$explore_mode = $uri_parts[3];
if ($explore_mode == "tx") $explore_mode = "transactions";
$game_identifier = $uri_parts[2];

$user_game = false;

$game_q = "SELECT * FROM games WHERE url_identifier=".$app->quote_escape($game_identifier)." AND (game_status IN ('running','completed','published'));";
$game_r = $app->run_query($game_q);

if ($game_r->rowCount() == 1) {
	$db_game = $game_r->fetch();
	$game = new Game($app, $db_game['game_id']);
	
	if ($game->db_game['p2p_mode'] == "rpc") {
		$coin_rpc = new jsonRPCClient('http://'.$game->db_game['rpc_username'].':'.$game->db_game['rpc_password'].'@127.0.0.1:'.$game->db_game['rpc_port'].'/');
	}
	
	if ($thisuser) {
		$qq = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
		$rr = $app->run_query($qq);
		
		if ($rr->rowCount() > 0) {
			$user_game = $rr->fetch();
		}
	}
	
	if ($game->db_game['p2p_mode'] == "none" && !$user_game && $game->db_game['creator_id'] > 0) $game = false;
}

if (rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer") $explore_mode = "games";
else if (rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer/".$game->db_game['url_identifier']) $explore_mode = "index";

if ($explore_mode == "games" || ($game && in_array($explore_mode, array('index','events','blocks','addresses','transactions')))) {
	if ($game) {
		$last_block_id = $game->last_block_id();
		$current_round = $game->block_to_round($last_block_id+1);
	}
	
	$round = false;
	$block = false;
	$address = false;
	
	$mode_error = true;
	
	if ($explore_mode == "games") {
		$mode_error = false;
		$pagetitle = $GLOBALS['coin_brand_name']." - Please select a game";
	}
	if ($explore_mode == "index") {
		$mode_error = false;
		$pagetitle = $game->db_game['name']." Block Explorer";
	}
	if ($explore_mode == "events") {
		$event_status = "";
		$event_id = $uri_parts[4];
		
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
		$address_text = $uri_parts[4];
		$q = "SELECT * FROM addresses WHERE address=".$app->quote_escape($address_text).";";
		$r = $app->run_query($q);
		if ($r->rowCount() == 1) {
			$address = $r->fetch();
			$mode_error = false;
			$pagetitle = $game->db_game['name']." Address: ".$address['address'];
		}
	}
	if ($explore_mode == "blocks") {
		if ($_SERVER['REQUEST_URI'] == "/explorer/".$game->db_game['url_identifier']."/blocks" || $_SERVER['REQUEST_URI'] == "/explorer/".$game->db_game['url_identifier']."/blocks/") {
			$mode_error = false;
			$pagetitle = $game->db_game['name']." - List of blocks";
		}
		else {
			$block_id = intval($uri_parts[4]);
			$q = "SELECT * FROM blocks WHERE game_id='".$game->db_game['game_id']."' AND block_id='".$block_id."';";
			$r = $app->run_query($q);
			if ($r->rowCount() == 1) {
				$block = $r->fetch();
				$mode_error = false;
				$pagetitle = $game->db_game['name']." Block #".$block['block_id'];
			}
			else {
				$q = "SELECT * FROM blocks WHERE game_id='".$game->db_game['game_id']."' AND block_hash=".$app->quote_escape($uri_parts[4]).";";
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
		if ($uri_parts[4] == "unconfirmed") {
			$explore_mode = "unconfirmed";
			$mode_error = false;
			$pagetitle = $game->db_game['name']." - Unconfirmed Transactions";
		}
		else {
			if (strlen($uri_parts[4]) < 20) {
				$tx_id = intval($uri_parts[4]);
				$q = "SELECT * FROM transactions WHERE transaction_id='".$tx_id."';";
			}
			else {
				$tx_hash = $uri_parts[4];
				$q = "SELECT * FROM transactions WHERE tx_hash=".$app->quote_escape($tx_hash).";";
			}
			$r = $app->run_query($q);
			
			if ($r->rowCount() == 1) {
				$transaction = $r->fetch();
				$mode_error = false;
				$pagetitle = $game->db_game['name']." Transaction: ".$transaction['tx_hash'];
			}
		}
	}
	
	if ($mode_error) $pagetitle = $GLOBALS['coin_brand_name']." - Blockchain Explorer";
	$nav_tab_selected = "explorer";
	include('includes/html_start.php');
	
	if ($thisuser) { ?>
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
					echo ', '.$game->last_block_id();
					echo ', '.$game->last_transaction_id().', ';
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
			
				<div class="row">
					<div class="col-sm-7 ">
						<ul class="list-inline explorer_nav" id="explorer_nav">
							<li><a<?php if ($explore_mode == 'blocks') echo ' class="selected"'; ?> href="/explorer/<?php echo $game->db_game['url_identifier']; ?>/blocks/">Blocks</a></li>
							<li><a<?php if ($explore_mode == 'events') echo ' class="selected"'; ?> href="/explorer/<?php echo $game->db_game['url_identifier']; ?>/events/">Events</a></li>
							<li><a<?php if ($explore_mode == 'unconfirmed') echo ' class="selected"'; ?> href="/explorer/<?php echo $game->db_game['url_identifier']; ?>/transactions/unconfirmed/">Unconfirmed Transactions</a></li>
							<?php if ($thisuser) { ?><li><a href="/wallet/<?php echo $game->db_game['url_identifier']; ?>/">My Wallet</a></li><?php } ?>
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
					$round_fee_q = "SELECT SUM(fee_amount) FROM transactions WHERE game_id='".$game->db_game['game_id']."' AND block_id > ".(($this_round-1)*$game->db_game['round_length'])." AND block_id < ".($this_round*$game->db_game['round_length']).";";
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
					
					$q = "SELECT * FROM blocks WHERE game_id='".$game->db_game['game_id']."' AND block_id >= '".$from_block_id."' AND block_id <= ".$to_block_id." ORDER BY block_id ASC;";
					$r = $app->run_query($q);
					echo "Blocks in this round: ";
					while ($round_block = $r->fetch()) {
						echo "<a href=\"/explorer/".$game->db_game['url_identifier']."/blocks/".$round_block['block_id']."\">".$round_block['block_id']."</a> ";
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
						$q = "SELECT * FROM transactions WHERE game_id='".$game->db_game['game_id']."' AND block_id='".$i."' AND amount > 0 ORDER BY transaction_id ASC;";
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
						$round_id = $game->block_to_round($block['block_id']);
						$block_index = $game->block_id_to_round_index($block['block_id']);
						
						list($num_trans, $block_sum) = $game->block_stats($block);
						
						echo "<h1>Block #".$block['block_id']."</h1>";
						echo "<h3>".$game->db_game['name']."</h3>";
						if (!empty($block['num_transactions']) && $num_trans != $block['num_transactions']) {
							echo "Loaded ".number_format($num_trans)." / ".number_format($block['num_transactions'])." transactions (".number_format(100*$num_trans/$block['num_transactions'], 2)."%).<br/>\n";
						}
						echo "This block contains ".number_format($num_trans)." transactions totaling ".number_format($block_sum/pow(10,8), 2)." coins.<br/>";
						if ($block['locally_saved'] == 1) {
							echo $GLOBALS['coin_brand_name']." took ".number_format($block['load_time'], 2)." seconds to load this block.<br/>\n";
						}
						else {
							$q = "SELECT SUM(load_time) FROM transactions WHERE game_id='".$game->db_game['game_id']."' AND block_id='".$block['block_id']."';";
							$load_time = $app->run_query($q)->fetch()['SUM(load_time)'];
							echo "Still loading... ".number_format($load_time, 2)." seconds elapsed.<br/>\n";
						}
						$events = $game->events_by_block($block['block_id']);
						echo "This block is referenced in ".count($events)." events<br/>\n";
						for ($i=0; $i<count($events); $i++) {
							echo "<a href=\"/explorer/".$game->db_game['url_identifier']."/events/".$events[$i]->db_event['event_index']."\">".$events[$i]->db_event['event_name']."</a><br/>\n";
						}
						echo "<br/>\n";
					}
					else {
						$q = "SELECT COUNT(*), SUM(amount) FROM transactions WHERE game_id='".$game->db_game['game_id']."' AND block_id IS NULL;";// AND amount > 0;";
						$r = $app->run_query($q);
						$r = $r->fetch(PDO::FETCH_NUM);
						$num_trans = $r[0];
						$block_sum = $r[1];
						
						$expected_block_id = $game->last_block_id()+1;
						$expected_round_id = $game->block_to_round($expected_block_id);
						$expected_block_index = $game->block_id_to_round_index($expected_block_id);
						
						echo "<h1>Unconfirmed Transactions</h1>\n";
						echo "<h3>".$game->db_game['name']."</h3>";
						echo $num_trans." known transaction";
						if ($num_trans == 1) echo " is";
						else echo "s are";
						echo " awaiting confirmation with a sum of ".number_format($block_sum/pow(10,8), 2)." coins.<br/>\n";
						echo "Block #".$expected_block_id." is currently being mined.<br/>\n";
					}
					
					$next_prev_links = $game->block_next_prev_links($block);
					echo $next_prev_links;
					
					echo '<div style="margin-top: 10px; border-bottom: 1px solid #bbb;">';
					
					$q = "SELECT * FROM transactions WHERE game_id='".$game->db_game['game_id']."' AND block_id";
					if ($explore_mode == "unconfirmed") $q .= " IS NULL";
					else $q .= "='".$block['block_id']."'";
					$q .= " AND amount > 0 ORDER BY transaction_id ASC;";
					$r = $app->run_query($q);
					
					while ($transaction = $r->fetch()) {
						echo $game->render_transaction($transaction, FALSE);
					}
					echo '</div>';
					echo "<br/>\n";
					
					echo $next_prev_links;
					?>
					<br/><br/>
					<a href="" onclick="$('#block_info').toggle('fast'); return false;">See block details</a><br/>
					<pre id="block_info" style="display: none;"><?php
					print_r($block);
					
					if ($game->db_game['p2p_mode'] == "rpc") {
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
					$to_block_id = $game->last_block_id();
					$from_block_id = $to_block_id-$blocks_per_section+1;
					if ($from_block_id < 0) $from_block_id = 0;
					?>
					<script type="text/javascript">
					var explorer_blocks_per_section = <?php echo $blocks_per_section; ?>;
					var explorer_block_list_sections = 1;
					var explorer_block_list_from_block = <?php echo $from_block_id; ?>;
					
					function explorer_block_list_show_more() {
						explorer_block_list_from_block = explorer_block_list_from_block-explorer_blocks_per_section;
						explorer_block_list_sections++;
						var section = explorer_block_list_sections;
						$('#explorer_block_list').append('<div id="explorer_block_list_'+section+'">Loading...</div>');
						
						$.get("/ajax/explorer_block_list.php?game_id="+games[0].game_id+"&from_block="+explorer_block_list_from_block+"&blocks_per_section="+explorer_blocks_per_section, function(html) {
							$('#explorer_block_list_'+section).html(html);
							console.log(result);
						});
					}
					</script>
					<?php
					echo "<h1>".$game->db_game['name']." - Blocks</h1>";
					
					echo '<div id="explorer_block_list" style="margin-bottom: 15px;">';
					echo '<div id="explorer_block_list_0">';
					echo $game->explorer_block_list($from_block_id, $to_block_id);
					echo '</div>';
					echo '</div>';
					
					echo '<a href="" onclick="explorer_block_list_show_more(); return false;">Show More</a>';
					
					echo "<br/>\n";
				}
			}
			else if ($explore_mode == "addresses") {
				echo "<h3>".$game->db_game['name']." Address: ".$address['address']."</h3>\n";
				
				$q = "SELECT * FROM transactions t, transaction_ios i WHERE i.address_id='".$address['address_id']."' AND (t.transaction_id=i.create_transaction_id OR t.transaction_id=i.spend_transaction_id) GROUP BY t.transaction_id ORDER BY t.transaction_id ASC;";
				$r = $app->run_query($q);
				
				echo "This address has been used in ".$r->rowCount()." transactions.<br/>\n";
				if ($address['is_mine'] == 1) echo "This is one of your addresses.<br/>\n";
				
				echo '<div style="border-bottom: 1px solid #bbb;">';
				while ($transaction_io = $r->fetch()) {
					echo $game->render_transaction($transaction_io, $address['address_id']);
				}
				echo "</div>\n";
				
				echo "<br/>\n";
			}
			else if ($explore_mode == "initial") {
				$q = "SELECT * FROM transactions WHERE game_id='".$game->db_game['game_id']."' AND round_id=0 AND amount > 0 ORDER BY transaction_id ASC;";
				$r = $app->run_query($q);
				
				echo '<div style="border-bottom: 1px solid #bbb;">';
				while ($transaction = $r->fetch()) {
					echo $game->render_transaction($transaction, FALSE);
				}
				echo '</div>';
			}
			else if ($explore_mode == "transactions") {
				$rpc_transaction = false;
				$rpc_raw_transaction = false;
				
				if ($game->db_game['p2p_mode'] == "rpc") {
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
				
				echo "<h3>".$game->db_game['name']." Transaction: ".$transaction['tx_hash']."</h3>\n";
				if ($transaction['block_id'] > 0) {
					$block_index = $game->block_id_to_round_index($transaction['block_id']);
					$round_id = $game->block_to_round($transaction['block_id']);
				}
				else {
					$block_index = false;
					$round_id = false;
				}
				echo "This transaction has ".(int) $transaction['num_inputs']." inputs and ".(int) $transaction['num_outputs']." outputs totalling ".$app->format_bignum($transaction['amount']/pow(10,8))." ".$game->db_game['coin_name_plural'].". ";
				echo "Loaded in ".number_format($transaction['load_time'], 2)." seconds.";
				echo "<br/>\n";
				
				echo '<div style="margin-top: 10px; border-bottom: 1px solid #bbb;">';
				echo $game->render_transaction($transaction, false);
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
			else if ($explore_mode == "games") {
				$q = "SELECT * FROM games WHERE game_status IN ('running','published','completed') AND featured=1 ORDER BY game_status ASC;";
				$r = $app->run_query($q);
				$game_id_csv = "";
				$section = "";
				while ($db_game = $r->fetch()) {
					if ($db_game['game_status'] == "completed") $this_section = "completed";
					else $this_section = "running";
					if ($section != $this_section) echo "<h2>".ucwords($this_section)." Games</h2>\n";
					$section = $this_section;
					echo '<a href="/explorer/'.$db_game['url_identifier'].'/events/">'.$db_game['name']."</a><br/>\n";
					$game_id_csv .= $db_game['game_id'].",";
				}
				if ($game_id_csv != "") $game_id_csv = substr($game_id_csv, 0, strlen($game_id_csv)-1);
				
				if ($thisuser) {
					$q = "SELECT * FROM games g JOIN user_games ug ON g.game_id=ug.game_id AND ug.user_id='".$thisuser->db_user['user_id']."'";
					$q .= " AND g.game_id NOT IN (".$game_id_csv.")";
					$q .= ";";
					$r = $app->run_query($q);
					while ($db_game = $r->fetch()) {
						echo '<a href="/explorer/'.$db_game['url_identifier'].'">'.$db_game['name']."</a><br/>\n";
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
