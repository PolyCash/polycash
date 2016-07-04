<?php
include('includes/connect.php');
include('includes/get_session.php');
include('includes/jsonRPCClient.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

$explore_mode = $uri_parts[3];
$game_identifier = $uri_parts[2];

$show_join_game = false;
$user_game = false;

$game_q = "SELECT * FROM games WHERE url_identifier='".mysql_real_escape_string($game_identifier)."';";
$game_r = run_query($game_q);

if (mysql_numrows($game_r) == 1) {
	$game = mysql_fetch_array($game_r);
	
	if ($thisuser) {
		$qq = "SELECT * FROM user_games WHERE user_id='".$thisuser['user_id']."' AND game_id='".$game['game_id']."';";
		$rr = run_query($qq);
		
		if (mysql_numrows($rr) > 0) {
			$user_game = mysql_fetch_array($rr);
			
			if ($game['game_id'] != $thisuser['game_id']) {
				$qq = "UPDATE users SET game_id='".$game['game_id']."' WHERE user_id='".$thisuser['user_id']."';";
				$rr = run_query($qq);
			}
		}
	}
	
	if ($user_game) {}
	else {
		if ($game['creator_id'] > 0) {
			$game = false;
		}
		else if ($thisuser) $show_join_game = true;
	}
}

if (rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer") $explore_mode = "games";
else if (rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer/".$game['url_identifier']) $explore_mode = "index";

if ($explore_mode == "games" || ($game && in_array($explore_mode, array('index','rounds','blocks','addresses','transactions')))) {
	$last_block_id = last_block_id($game['game_id']);
	$current_round = block_to_round($game, $last_block_id+1);
	
	$round = false;
	$block = false;
	$address = false;
	
	$mode_error = true;
	
	if ($explore_mode == "games") {
		$mode_error = false;
		$pagetitle = "EmpireCoin - Please select a game";
	}
	if ($explore_mode == "index") {
		$mode_error = false;
		$pagetitle = $game['name']." Block Explorer";
	}
	if ($explore_mode == "rounds") {
		if ($uri_parts[4] == "current") {
			$round_status = "current";
			$mode_error = false;
			$pagetitle = $game['name']." - Current Scores";
		}
		else {
			$round_id = intval($uri_parts[4]);
			
			if ($round_id == 0) {
				$mode_error = false;
				$pagetitle = "Round Results - ".$game['name'];
			}
			else {
				$q = "SELECT r.*, n.*, t.amount FROM cached_rounds r LEFT JOIN nations n ON r.winning_nation_id=n.nation_id LEFT JOIN transactions t ON r.payout_transaction_id=t.transaction_id WHERE r.game_id=".$game['game_id']." AND r.round_id='".$round_id."';";
				$r = run_query($q);
				if (mysql_numrows($r) == 1) {
					$round = mysql_fetch_array($r);
					$mode_error = false;
					$pagetitle = $game['name']." - Results of round #".$round['round_id'];
					$round_status = "completed";
				}
				else {
					$last_block_id = last_block_id($game['game_id']);
					$current_round = block_to_round($game, $last_block_id+1);
					
					if ($current_round == $round_id) {
						$round_status = "current";
						$mode_error = false;
						$pagetitle = $game['name']." - Current Scores";
					}
				}
			}
		}
	}
	if ($explore_mode == "addresses") {
		$address_text = $uri_parts[4];
		$q = "SELECT * FROM addresses WHERE address='".mysql_real_escape_string($address_text)."';";
		$r = run_query($q);
		if (mysql_numrows($r) == 1) {
			$address = mysql_fetch_array($r);
			$mode_error = false;
			$pagetitle = $game['name']." Address: ".$address['address'];
		}
	}
	if ($explore_mode == "blocks") {
		if ($_SERVER['REQUEST_URI'] == "/explorer/".$game['url_identifier']."/blocks" || $_SERVER['REQUEST_URI'] == "/explorer/".$game['url_identifier']."/blocks/") {
			$mode_error = false;
			$pagetitle = $game['name']." - List of blocks";
		}
		else {
			$block_id = intval($uri_parts[4]);
			$q = "SELECT * FROM blocks WHERE game_id='".$game['game_id']."' AND block_id='".$block_id."';";
			$r = run_query($q);
			if (mysql_numrows($r) == 1) {
				$block = mysql_fetch_array($r);
				$mode_error = false;
				$pagetitle = $game['name']." Block #".$block['block_id'];
			}
		}
	}
	if ($explore_mode == "transactions") {
		if ($uri_parts[4] == "unconfirmed") {
			$explore_mode = "unconfirmed";
			$mode_error = false;
			$pagetitle = $game['name']." - Unconfirmed Transactions";
		}
		else {
			if (strlen($uri_parts[4]) < 20) {
				$tx_id = intval($uri_parts[4]);
				$q = "SELECT * FROM transactions WHERE transaction_id='".$tx_id."';";
			}
			else {
				$tx_hash = $uri_parts[4];
				$q = "SELECT * FROM transactions WHERE tx_hash='".mysql_real_escape_string($tx_hash)."';";
			}
			$r = run_query($q);
			
			if (mysql_numrows($r) == 1) {
				$transaction = mysql_fetch_array($r);
				$mode_error = false;
				$pagetitle = $game['name']." Transaction: ".$transaction['tx_hash'];
			}
		}
	}
	
	if ($mode_error) $pagetitle = "EmpireCoin - Blockchain Explorer";
	$nav_tab_selected = "explorer";
	include('includes/html_start.php');
	
	if ($thisuser) { ?>
		<div class="container" style="max-width: 1000px; padding-top: 10px;">
			<?php
			$account_value = account_coin_value($game, $thisuser);
			include("includes/wallet_status.php");
			?>
		</div>
		<?php
	}
	?>
	<div class="container" style="max-width: 1000px;">
		<?php
		if ($show_join_game) {
			echo '<br/><button id="switch_game_btn" type="button" class="btn btn-primary" onclick="switch_to_game('.$game['game_id'].', \'switch\');">Join this game</button>';
		}
		
		if ($mode_error) {
			echo "Error, you've reached an invalid page.";
		}
		else {
			if ($explore_mode == "rounds") {
				if ($round || $round_status == "current") {
					if ($round_status == "current") {
						$last_block_id = last_block_id($game['game_id']);
						$this_round = block_to_round($game, $last_block_id+1);
						$current_round = $this_round;
					}
					else $this_round = $round['round_id'];
					
					if ($this_round > 1 || $round['round_id'] < $current_round) echo "<br/>";
					if ($this_round > 1) { ?>
						<a href="/explorer/<?php echo $game['url_identifier']; ?>/rounds/<?php echo $this_round-1; ?>" style="display: inline-block; margin-right: 30px;">&larr; Previous Round</a>
						<?php
					}
					if ($this_round < $current_round) { ?>
						<a href="/explorer/<?php echo $game['url_identifier']; ?>/rounds/<?php echo $this_round+1; ?>">Next Round &rarr;</a>
						<?php
					}
					if ($this_round > 1 || $this_round < $current_round) echo "<br/>";
					
					if ($round_status == "current") {
						$rankings = round_voting_stats_all($game, $current_round);
						$round_score_sum = $rankings[0];
						$max_score = $rankings[1];
						$stats_all = $rankings[2];
						$nation_id_to_rank = $rankings[3];
						$confirmed_score = $rankings[4];
						$unconfirmed_score = $rankings[5];
						
						echo "<h1>Round #".$current_round." is currently running</h1>\n";
					}
					else {
						if ($round['winning_nation_id'] > 0) echo "<h1>".$round['name']." wins round #".$round['round_id']."</h1>\n";
						else echo "<h1>Round #".$round['round_id'].": No winner</h1>\n";
						
						$max_score = floor($round['score_sum']*$game['max_voting_fraction']);
						$round_score_sum = $round['score_sum'];
					}
					
					echo "<h3>".$game['name']."</h3>";
					?>
					<div class="row">
						<div class="col-md-6">
							<div class="row">
								<div class="col-sm-4">Total votes cast:</div>
								<div class="col-sm-8"><?php echo format_bignum($round_score_sum/pow(10,8)); ?> votes</div>
							</div>
						</div>
					</div>
					<?php
					if ($thisuser) {
						$returnvals = my_votes_in_round($game, $this_round, $thisuser['user_id']);
						$my_votes = $returnvals[0];
						$coins_voted = $returnvals[1];
					}
					else $my_votes = false;
					
					if ($my_votes[$round['winning_nation_id']] > 0) {
						$payout_amt = (floor(100*pos_reward_in_round($game, $this_round)/pow(10,8)*$my_votes[$round['winning_nation_id']]['coins']/$round['winning_score'])/100);
						
						$payout_disp = format_bignum($payout_amt);
						echo "You won <font class=\"greentext\">+".$payout_disp." ";
						if ($payout_disp == '1') echo $game['coin_name'];
						else echo $game['coin_name_plural'];
						
						$vote_disp = format_bignum($my_votes[$round['winning_nation_id']]['coins']/pow(10,8));
						echo "</font> by voting ".$vote_disp." ";
						if ($vote_disp == '1') echo $game['coin_name'];
						else echo $game['coin_name_plural'];
						
						if ($game['payout_weight'] != "coin") {
							$vote_disp = format_bignum($my_votes[$round['winning_nation_id']][$game['payout_weight'].'s']/pow(10,8));
							echo " (".$vote_disp;
							echo " vote";
							if ($vote_disp != '1') echo 's';
							echo ")";
						}
						
						echo " for ".$round['name']."</font><br/>\n";
					}
					
					if ($round['payout_transaction_id'] > 0) {
						$payout_disp = format_bignum($round['amount']/pow(10,8));
						echo '<font class="greentext">'.$payout_disp."</font> ";
						if ($payout_disp == '1') echo $game['coin_name']." was";
						else echo $game['coin_name_plural']." were";
						echo " paid out to the winners.<br/>\n";
					}
					$round_fee_q = "SELECT SUM(fee_amount) FROM transactions WHERE game_id='".$game['game_id']."' AND block_id > ".(($this_round-1)*$game['round_length'])." AND block_id < ".($this_round*$game['round_length']).";";
					$round_fee_r = run_query($round_fee_q);
					$round_fees = mysql_fetch_row($round_fee_r);
					$round_fees = intval($round_fees[0]);
					
					$fee_disp = format_bignum($round_fees/pow(10,8));
					echo '<font class="redtext">'.$fee_disp."</font> ";
					if ($fee_disp == '1') echo $game['coin_name']." was";
					else echo $game['coin_name_plural']." were";
					echo " paid in fees during this round.<br/>\n";
					
					$from_block_id = (($this_round-1)*$game['round_length'])+1;
					$to_block_id = ($this_round*$game['round_length']);
					
					$q = "SELECT * FROM blocks WHERE game_id='".$game['game_id']."' AND block_id >= '".$from_block_id."' AND block_id <= ".$to_block_id." ORDER BY block_id ASC;";
					$r = run_query($q);
					echo "Blocks in this round: ";
					while ($round_block = mysql_fetch_array($r)) {
						echo "<a href=\"/explorer/".$game['url_identifier']."/blocks/".$round_block['block_id']."\">".$round_block['block_id']."</a> ";
					}
					?>
					<br/>
					
					<a href="/explorer/<?php echo $game['url_identifier']; ?>/rounds/">See all rounds</a><br/>
					
					<h2>Rankings</h2>
					
					<div class="row" style="font-weight: bold;">
					<div class="col-md-3">Empire</div>
					<div class="col-md-1" style="text-align: center;">Percent</div>
					<div class="col-md-3" style="text-align: center;">Coin Votes</div>
					<?php if ($thisuser) { ?><div class="col-md-3" style="text-align: center;">Your Votes</div><?php } ?>
					</div>
					<?php
					$winner_displayed = FALSE;
					for ($rank=1; $rank<=$game['num_voting_options']; $rank++) {
						if ($round) {
							$q = "SELECT * FROM nations WHERE nation_id='".$round['position_'.$rank]."';";
							$r = run_query($q);
						}
						
						if (!$round || mysql_numrows($r) == 1) {
							if ($round) {
								$ranked_nation = mysql_fetch_array($r);
								$nation_scores = nation_score_in_round($game, $ranked_nation['nation_id'], $round['round_id']);
								$nation_score = $nation_scores['sum'];
							}
							else {
								$ranked_nation = $stats_all[$rank-1];
								$nation_score = $ranked_nation[$game['payout_weight'].'_score']+$ranked_nation['unconfirmed_'.$game['payout_weight'].'_score'];
							}
							echo '<div class="row';
							if ($nation_score > $max_score) echo ' redtext';
							else if (!$winner_displayed && $nation_score > 0) { echo ' greentext'; $winner_displayed = TRUE; }
							echo '">';
							echo '<div class="col-md-3">'.$rank.'. '.$ranked_nation['name'].'</div>';
							echo '<div class="col-md-1" style="text-align: center;">'.round(100*$nation_score/$round_score_sum, 2).'%</div>';
							echo '<div class="col-md-3" style="text-align: center;">'.format_bignum($nation_score/pow(10,8)).' votes</div>';
							if ($thisuser) {
								echo '<div class="col-md-3" style="text-align: center;">';
								
								$score_qty = $my_votes[$ranked_nation['nation_id']][$game['payout_weight'].'s'];
								
								$score_disp = format_bignum($score_qty/pow(10,8));
								echo $score_disp." ";
								if ($game['payout_weight'] == "coin") {
									if ($score_disp == '1') echo $game['coin_name'];
									else echo $game['coin_name_plural'];
								}
								else {
									echo " vote";
									if ($score_disp != '1') echo "s";
								}
								
								echo ' ('.round(100*$score_qty/$nation_score, 3).'%)</div>';
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
						echo "Block #".$i."<br/>\n";
						$q = "SELECT * FROM transactions WHERE game_id='".$game['game_id']."' AND block_id='".$i."' AND amount > 0 ORDER BY transaction_id ASC;";
						$r = run_query($q);
						while ($transaction = mysql_fetch_array($r)) {
							echo render_transaction($game, $transaction, FALSE, "", $game['url_identifier']);
						}
					}
					echo '</div>';
					
					echo "<br/>\n";
					echo "<br/>\n";
					
					if ($round['round_id'] > 1) { ?>
						<a href="/explorer/<?php echo $game['url_identifier']; ?>/rounds/<?php echo $round['round_id']-1; ?>" style="display: inline-block; margin-right: 30px;">&larr; Previous Round</a>
						<?php
					}
					
					if ($round['round_id'] < $current_round) { ?>
						<a href="/explorer/<?php echo $game['url_identifier']; ?>/rounds/<?php echo $round['round_id']+1; ?>">Next Round &rarr;</a>
						<?php
					}
				}
				else {
					?>
					<h1><?php echo $game['name']; ?> Round Results</h1>
					<div style="border-bottom: 1px solid #bbb; margin-bottom: 5px;" id="rounds_complete">
						<div id="rounds_complete_0">
							<?php
							$rounds_complete = rounds_complete_html($game, $current_round, 20);
							$last_round_shown = $rounds_complete[0];
							echo $rounds_complete[1];
							?>
						</div>
					</div>
					<center>
						<a href="" onclick="show_more_rounds_complete(); return false;" id="show_more_link">Show More</a>
					</center>
					<br/>
					<script type="text/javascript">
					$(document).ready(function() {
						last_round_shown = <?php echo $last_round_shown; ?>;
					});
					</script>
					<?php
				}
				echo "<br/><br/>\n";
			}
			else if ($explore_mode == "blocks" || $explore_mode == "unconfirmed") {
				if ($block || $explore_mode == "unconfirmed") {
					if ($block) {
						$round_id = block_to_round($game, $block['block_id']);
						$block_index = block_id_to_round_index($game, $block['block_id']);
						
						$q = "SELECT COUNT(*), SUM(amount) FROM transactions WHERE game_id='".$game['game_id']."' AND block_id='".$block['block_id']."' AND amount > 0;";
						$r = run_query($q);
						$r = mysql_fetch_row($r);
						$num_trans = $r[0];
						$block_sum = $r[1];
						
						echo "<h1>Block #".$block['block_id']."</h1>";
						echo "<h3>".$game['name']."</h3>";
						echo "This block contains $num_trans transactions totaling ".number_format($block_sum/pow(10,8), 2)." coins.<br/>\n";
						echo "This is block ".$block_index." of <a href=\"/explorer/".$game['url_identifier']."/rounds/".$round_id."\">round #".$round_id."</a><br/><br/>\n";
					}
					else {
						$q = "SELECT COUNT(*), SUM(amount) FROM transactions WHERE game_id='".$game['game_id']."' AND block_id IS NULL;";// AND amount > 0;";
						$r = run_query($q);
						$r = mysql_fetch_row($r);
						$num_trans = $r[0];
						$block_sum = $r[1];
						
						$expected_block_id = last_block_id($game['game_id'])+1;
						$expected_round_id = block_to_round($game, $expected_block_id);
						$expected_block_index = block_id_to_round_index($game, $expected_block_id);
						
						echo "<h1>Unconfirmed Transactions</h1>\n";
						echo "<h3>".$game['name']."</h3>";
						echo "$num_trans known transactions are awaiting confirmation with a sum of ".number_format($block_sum/pow(10,8), 2)." coins.<br/>\n";
						echo "Block #".$expected_block_id." is currently being mined.  It will be block $expected_block_index of ";
						echo "<a href=\"/explorer/".$game['url_identifier']."/rounds/".$expected_round_id."\">round #".$expected_round_id."</a><br/><br/>\n";
					}
					
					echo '<div style="border-bottom: 1px solid #bbb;">';
					
					$q = "SELECT * FROM transactions WHERE game_id='".$game['game_id']."' AND block_id";
					if ($explore_mode == "unconfirmed") $q .= " IS NULL";
					else $q .= "='".$block['block_id']."'";
					$q .= " AND amount > 0 ORDER BY transaction_id ASC;";
					$r = run_query($q);
					
					while ($transaction = mysql_fetch_array($r)) {
						echo render_transaction($game, $transaction, FALSE, "", $game['url_identifier']);
					}
					echo '</div>';
					echo "<br/>\n";
					
					$prev_link_target = false;
					if ($explore_mode == "unconfirmed") $prev_link_target = "blocks/".last_block_id($game['game_id']);
					else if ($block['block_id'] > 1) $prev_link_target = "blocks/".($block['block_id']-1);
					if ($prev_link_target) echo '<a href="/explorer/'.$game['url_identifier'].'/'.$prev_link_target.'" style="margin-right: 30px;">&larr; Previous Block</a>';
					
					$next_link_target = false;
					if ($explore_mode == "unconfirmed") {}
					else if ($block['block_id'] == last_block_id($game['game_id'])) $next_link_target = "transactions/unconfirmed";
					else if ($block['block_id'] < last_block_id($game['game_id'])) $next_link_target = "blocks/".($block['block_id']+1);
					if ($next_link_target) echo '<a href="/explorer/'.$game['url_identifier'].'/'.$next_link_target.'">Next Block &rarr;</a>';
					
					if ($explore_mode == "blocks") {
						echo "<br/><a href=\"/explorer/".$game['url_identifier']."/transactions/unconfirmed\">Unconfirmed transactions</a>\n";
					}
					
					echo "<br/><br/>\n";
				}
				else {
					$q = "SELECT * FROM blocks WHERE game_id='".$game['game_id']."' ORDER BY block_id ASC;";
					$r = run_query($q);
					
					echo "<h1>EmpireCoin - List of Blocks</h1>\n";
					echo "<h3>".$game['name']."</h3>";
					echo "<ul>\n";
					while ($block = mysql_fetch_array($r)) {
						echo "<li><a href=\"/explorer/".$game['url_identifier']."/blocks/".$block['block_id']."\">Block #".$block['block_id']."</a></li>\n";
					}
					echo "</ul>\n";
					
					echo "<br/><br/>\n";
				}
			}
			else if ($explore_mode == "addresses") {
				echo "<h3>EmpireCoin Address: ".$address['address']."</h3>\n";
				
				$q = "SELECT * FROM transactions t, transaction_IOs i WHERE i.address_id='".$address['address_id']."' AND (t.transaction_id=i.create_transaction_id OR t.transaction_id=i.spend_transaction_id) GROUP BY t.transaction_id ORDER BY t.transaction_id ASC;";
				$r = run_query($q);
				
				echo "This address has been used in ".mysql_numrows($r)." transactions.<br/>\n";
				if ($address['is_mine'] == 1) echo "This is one of your addresses.<br/>\n";
				
				echo '<div style="border-bottom: 1px solid #bbb;">';
				while ($transaction_io = mysql_fetch_array($r)) {
					$block_index = block_id_to_round_index($game, $transaction_io['block_id']);
					$round_id = block_to_round($game, $transaction_io['block_id']);
					echo render_transaction($game, $transaction_io, $address['address_id'], "Confirmed in the <a href=\"/explorer/".$game['url_identifier']."/blocks/".$transaction_io['block_id']."\">".date("jS", strtotime("1/".$block_index."/2015"))." block</a> of <a href=\"/explorer/".$game['url_identifier']."/rounds/".$round_id."\">round ".$round_id."</a>". $game['url_identifier']);
				}
				echo "</div>\n";
				
				echo "<br/><br/>\n";
			}
			else if ($explore_mode == "transactions") {
				$rpc_transaction = false;
				$rpc_raw_transaction = false;
				
				if ($game['game_type'] == "real") {
					$empirecoin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');
					try {
						$rpc_transaction = $empirecoin_rpc->gettransaction($transaction['tx_hash']);
					}
					catch (Exception $e) {}
					
					try {
						$rpc_raw_transaction = $empirecoin_rpc->getrawtransaction($transaction['tx_hash']);
						$rpc_raw_transaction = $empirecoin_rpc->decoderawtransaction($rpc_raw_transaction);
					}
					catch (Exception $e) {}
				}
				
				echo "<h3>EmpireCoin Transaction: ".$transaction['tx_hash']."</h3>\n";
				if ($transaction['block_id'] > 0) {
					$block_index = block_id_to_round_index($game, $transaction['block_id']);
					$round_id = block_to_round($game, $transaction['block_id']);
					$label_txt = "Confirmed in the <a href=\"/explorer/".$game['url_identifier']."/blocks/".$transaction['block_id']."\">".date("jS", strtotime("1/".$block_index."/2015"))." block</a> of <a href=\"/explorer/".$game['url_identifier']."/rounds/".$round_id."\">round ".$round_id."</a>";
				}
				else {
					$block_index = false;
					$round_id = false;
					$label_txt = "This transaction is <a href=\"/explorer/".$game['url_identifier']."/transactions/unconfirmed\">not yet confirmed</a>.";
				}
				echo '<div style="border-bottom: 1px solid #bbb;">';
				echo render_transaction($game, $transaction, false, $label_txt, $game['url_identifier']);
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
				
				echo "<br/><br/>\n";
			}
			else if ($explore_mode == "index") {
				?>
				<h3><?php echo $game['name']; ?> Block Explorer</h3>
				<ul>
					<li><a href="/explorer/<?php echo $game['url_identifier']; ?>/blocks/">Blocks</a></li>
					<li><a href="/explorer/<?php echo $game['url_identifier']; ?>/rounds/">Rounds</a></li>
					<li><a href="/explorer/<?php echo $game['url_identifier']; ?>/transactions/unconfirmed/">Unconfirmed Transactions</a></li>
					<?php if ($thisuser) { ?><li><a href="/wallet/">My Wallet</a></li><?php } ?>
				</ul>
				<?php
			}
			else if ($explore_mode == "games") {
				?>
				<h3>EmpireCoin - Please choose a game</h3>
				<?php
				$q = "SELECT * FROM games WHERE creator_id IS NULL;";
				$r = run_query($q);
				$game_id_csv = "";
				while ($game = mysql_fetch_array($r)) {
					echo '<a href="/explorer/'.$game['url_identifier'].'">'.$game['name']."</a><br/>\n";
					$game_id_csv .= $game['game_id'].",";
				}
				if ($game_id_csv != "") $game_id_csv = substr($game_id_csv, 0, strlen($game_id_csv)-1);
				
				$q = "SELECT * FROM games g JOIN user_games ug ON g.game_id=ug.game_id AND ug.user_id='".$thisuser['user_id']."'";
				$q .= " AND g.game_id NOT IN (".$game_id_csv.")";
				$q .= ";";
				$r = run_query($q);
				while ($game = mysql_fetch_array($r)) {
					echo '<a href="/explorer/'.$game['url_identifier'].'">'.$game['name']."</a><br/>\n";
				}
			}
		}
		?>
	</div>
	<?php
	include('includes/html_stop.php');
}
else {
	$pagetitle = "EmpireCoin - Blockchain Explorer";
	include('includes/html_start.php');
	?>
	<div class="container" style="max-width: 1000px;">
		Error, you've reached an invalid page.
	</div>
	<?php
	include('includes/html_stop.php');
}
?>