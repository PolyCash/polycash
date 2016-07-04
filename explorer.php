<?php
include('includes/connect.php');
include('includes/get_session.php');
$viewer_id = insert_pageview($thisuser);

$explore_mode = $uri_parts[2];

if (in_array($explore_mode, array('rounds','blocks','addresses'))) {
	if ($thisuser) $game_id = $thisuser['game_id'];
	else $game_id = get_site_constant('primary_game_id');
	
	$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
	$r = run_query($q);
	$this_game = mysql_fetch_array($r);
	
	$last_block_id = last_block_id($game_id);
	$current_round = block_to_round($last_block_id+1);
	
	$round = false;
	$block = false;
	$address = false;
	
	$mode_error = true;
	
	if ($explore_mode == "rounds") {
		$round_id = intval($uri_parts[3]);
		if ($round_id == 0) {
			$mode_error = false;
			$pagetitle = "Round Results - ".$this_game['name'];
		}
		else {
			$q = "SELECT * FROM cached_rounds r LEFT JOIN nations n ON r.winning_nation_id=n.nation_id WHERE r.game_id=".$game_id." AND r.round_id='".$round_id."';";
			$r = run_query($q);
			if (mysql_numrows($r) == 1) {
				$round = mysql_fetch_array($r);
				$mode_error = false;
				$pagetitle = $this_game['name']." - Results of round #".$round['round_id'];
			}
		}
	}
	if ($explore_mode == "addresses") {
		$address_text = $uri_parts[3];
		$q = "SELECT * FROM addresses WHERE address='".mysql_real_escape_string($address_text)."';";
		$r = run_query($q);
		if (mysql_numrows($r) == 1) {
			$address = mysql_fetch_array($r);
			$mode_error = false;
			$pagetitle = $this_game['name']." Address: ".$address['address'];
		}
	}
	if ($explore_mode == "blocks") {
		if ($_SERVER['REQUEST_URI'] == "/explorer/blocks" || $_SERVER['REQUEST_URI'] == "/explorer/blocks/") {
			$mode_error = false;
			$pagetitle = $this_game['name']." - List of blocks";
		}
		else {
			$block_id = intval($uri_parts[3]);
			$q = "SELECT * FROM blocks WHERE game_id='".$game_id."' AND block_id='".$block_id."';";
			$r = run_query($q);
			if (mysql_numrows($r) == 1) {
				$block = mysql_fetch_array($r);
				$mode_error = false;
				$pagetitle = $this_game['name']." Block #".$block['block_id'];
			}
		}
	}
	
	if ($mode_error) $pagetitle = "EmpireCoin - Blockchain Explorer";
	
	include('includes/html_start.php');
	
	if ($thisuser) { ?>
		<div class="container" style="max-width: 1000px; padding-top: 10px;">
			<?php
			$account_value = account_coin_value($game_id, $thisuser);
			include("includes/wallet_status.php");
			?>
		</div>
		<?php
	}
	?>
	<div class="container" style="max-width: 1000px;">
		<?php
		if ($mode_error) {
			echo "Error, you've reached an invalid page.";
		}
		else {
			if ($explore_mode == "rounds") {
				if (!$round) {
					$q = "SELECT * FROM cached_rounds r, nations n WHERE r.game_id='".$game_id."' AND r.winning_nation_id=n.nation_id ORDER BY r.round_id ASC;";
					$r = run_query($q);
					echo "<h1>".$this_game['name'].". ".mysql_numrows($r)." Rounds Completed</h1>";
					echo "<div style=\"border-bottom: 1px solid #bbb;\">";
					while ($cached_round = mysql_fetch_array($r)) {
						echo "<div class=\"row bordered_row\">";
						echo "<div class=\"col-sm-2\"><a href=\"/explorer/rounds/".$cached_round['round_id']."\">Round #".$cached_round['round_id']."</a></div>";
						echo "<div class=\"col-sm-7\">".$cached_round['name']." wins with ".number_format($cached_round['winning_vote_sum']/pow(10,8), 2)." votes (".round(100*$cached_round['winning_vote_sum']/$cached_round['total_vote_sum'], 2)."%)</div>";
						echo "<div class=\"col-sm-3\">".number_format($cached_round['total_vote_sum']/pow(10,8), 2)." votes cast</div>";
						echo "</div>\n";
					}
					echo "</div>\n";
				}
				else {
					if ($round['winning_nation_id'] > 0) echo "<h1>".$round['name']." wins round #".$round['round_id']."</h1>\n";
					else echo "<h1>Round #".$round['round_id'].": No winner</h1>\n";
					
					echo "<h3>".$this_game['name']."</h3>";
					?>
					<div class="row">
						<div class="col-md-6">
							<div class="row">
								<div class="col-sm-4">Total coins voted:</div>
								<div class="col-sm-8"><?php echo number_format(round($round['total_vote_sum']/pow(10,8))); ?> empirecoins</div>
							</div>
						</div>
					</div>
					<?php
					$max_vote_sum = floor($round['total_vote_sum']*get_site_constant('max_voting_fraction'));
					
					if ($thisuser) {
						$returnvals = my_votes_in_round($game_id, $round['round_id'], $thisuser['user_id']);
						$my_votes = $returnvals[0];
						$coins_voted = $returnvals[1];
					}
					else $my_votes = false;
					
					if ($my_votes[$round['winning_nation_id']] > 0) {
						echo "You won <font class=\"greentext\">+".(floor(100*750*$my_votes[$round['winning_nation_id']]/$round['winning_score'])/100)." EMP</font> by voting ".round($my_votes[$round['winning_nation_id']]/pow(10,8), 2)." coins for ".$round['name']."</font><br/>\n";
					}
					
					$q = "SELECT * FROM blocks WHERE game_id='".$game_id."' AND block_id > '".(($round['round_id']-1)*10)."' AND block_id <= ".($round['round_id']*10)." ORDER BY block_id ASC;";
					$r = run_query($q);
					echo "Blocks in this round: ";
					while ($round_block = mysql_fetch_array($r)) {
						echo "<a href=\"/explorer/blocks/".$round_block['block_id']."\">".$round_block['block_id']."</a> ";
					}
					echo "<br/>\n";
					
					echo "<a href=\"/explorer/rounds/\">See all rounds</a><br/>";
					
					echo "<h2>Rankings</h2>";
					
					echo '<div class="row" style="font-weight: bold;">';
					echo '<div class="col-md-3">Empire</div>';
					echo '<div class="col-md-1" style="text-align: center;">Percent</div>';
					echo '<div class="col-md-3" style="text-align: center;">Coin Votes</div>';
					echo '<div class="col-md-3" style="text-align: center;">Your Votes</div>';
					echo '</div>'."\n";
					
					$winner_displayed = FALSE;
					for ($rank=1; $rank<=16; $rank++) {
						$q = "SELECT * FROM nations WHERE nation_id='".$round['position_'.$rank]."';";
						$r = run_query($q);
						if (mysql_numrows($r) == 1) {
							$ranked_nation = mysql_fetch_array($r);
							$nation_score = nation_score_in_round($game_id, $ranked_nation['nation_id'], $round['round_id']);
							
							echo '<div class="row';
							if ($nation_score > $max_vote_sum) echo ' redtext';
							else if (!$winner_displayed && $nation_score > 0) { echo ' greentext'; $winner_displayed = TRUE; }
							echo '">';
							echo '<div class="col-md-3">'.$rank.'. '.$ranked_nation['name'].'</div>';
							echo '<div class="col-md-1" style="text-align: center;">'.round(100*$nation_score/$round['total_vote_sum'], 2).'%</div>';
							echo '<div class="col-md-3" style="text-align: center;">'.number_format(round($nation_score/pow(10,8))).' votes</div>';
							if ($my_votes[$ranked_nation['nation_id']] > 0) {
								echo '<div class="col-md-3" style="text-align: center;">';
								echo number_format(floor($my_votes[$ranked_nation['nation_id']]/pow(10,8)*100)/100);
								echo ' votes ('.round(100*$my_votes[$ranked_nation['nation_id']]/$nation_score, 3).'%)</div>';
							}
							echo '</div>'."\n";
						}
					}
					
					echo "<br/>\n";
					
					if ($round['round_id'] > 1) { ?>
						<a href="/explorer/rounds/<?php echo $round['round_id']-1; ?>" style="display: inline-block; margin-right: 30px;">&larr; Previous Round</a>
						<?php
					}
					if ($round['round_id'] < $current_round-1) { ?>
						<a href="/explorer/rounds/<?php echo $round['round_id']+1; ?>">Next Round &rarr;</a>
						<?php
					}
				}
				echo "<br/><br/>\n";
			}
			else if ($explore_mode == "blocks") {
				if ($block) {
					$q = "SELECT COUNT(*), SUM(amount) FROM webwallet_transactions WHERE game_id='".$game_id."' AND block_id='".$block['block_id']."' AND amount > 0;";
					$r = run_query($q);
					$num_trans = mysql_fetch_row($r);
					$block_sum = $num_trans[1];
					$num_trans = $num_trans[0];
					
					$round_id = block_to_round($block['block_id']);
					$block_index = block_id_to_round_index($block['block_id']);
					
					echo "<h1>Block #".$block['block_id']."</h1>";
					echo "<h3>".$game['name']."</h3>";
					echo "This block contains $num_trans transactions totaling ".number_format($block_sum/pow(10,8), 2)." coins.<br/>\n";
					echo "This is block ".$block_index." of <a href=\"/explorer/rounds/".$round_id."\">round #".$round_id."</a><br/><br/>\n";
					
					echo '<div style="border-bottom: 1px solid #bbb;">';
					$q = "SELECT * FROM webwallet_transactions WHERE game_id='".$game_id."' AND block_id='".$block['block_id']."' AND amount > 0 ORDER BY transaction_id ASC;";
					$r = run_query($q);
					while ($transaction = mysql_fetch_array($r)) {
						echo render_transaction($transaction, FALSE, "");
					}
					echo '</div>';
					echo "<br/>\n";
					
					if ($block['block_id'] > 1) echo '<a href="/explorer/blocks/'.($block['block_id']-1).'" style="margin-right: 30px;">&larr; Previous Block</a>';
					echo '<a href="/explorer/blocks/'.($block['block_id']+1).'">Next Block &rarr;</a>';
					
					echo "<br/><br/>\n";
				}
				else {
					$q = "SELECT * FROM blocks WHERE game_id='".$game_id."' ORDER BY block_id ASC;";
					$r = run_query($q);
					
					echo "<h1>EmpireCoin - List of Blocks</h1>\n";
					echo "<h3>".$game['name']."</h3>";
					echo "<ul>\n";
					while ($block = mysql_fetch_array($r)) {
						echo "<li><a href=\"/explorer/blocks/".$block['block_id']."\">Block #".$block['block_id']."</a></li>\n";
					}
					echo "</ul>\n";
					
					echo "<br/><br/>\n";
				}
			}
			else if ($explore_mode == "addresses") {
				echo "<h3>EmpireCoin Address: ".$address['address']."</h3>\n";
				
				$q = "SELECT * FROM webwallet_transactions t, transaction_IOs i WHERE i.address_id='".$address['address_id']."' AND (t.transaction_id=i.create_transaction_id OR t.transaction_id=i.spend_transaction_id) GROUP BY t.transaction_id ORDER BY t.transaction_id ASC;";
				$r = run_query($q);
				
				echo "This address has been used in ".mysql_numrows($r)." transactions.<br/>\n";
				
				echo '<div style="border-bottom: 1px solid #bbb;">';
				while ($transaction_io = mysql_fetch_array($r)) {
					$block_index = block_id_to_round_index($transaction_io['block_id']);
					$round_id = block_to_round($transaction_io['block_id']);
					echo render_transaction($transaction_io, $address['address_id'], "Confirmed in the <a href=\"/explorer/blocks/".$transaction_io['block_id']."\">".date("jS", strtotime("1/".$block_index."/2015"))." block</a> of <a href=\"/explorer/rounds/".$round_id."\">round ".$round_id."</a>");
				}
				echo "</div>\n";
				
				echo "<br/><br/>\n";
			}
		}
		?>
	</div>
	<?php

	include('includes/html_stop.php');
}
?>