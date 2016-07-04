<?php
include('includes/connect.php');
include('includes/get_session.php');
$viewer_id = insert_pageview($thisuser);

$explore_mode = $uri_parts[2];

if (in_array($explore_mode, array('rounds','blocks','addresses'))) {
	$last_block_id = last_block_id('beta');
	$current_round = block_to_round($last_block_id+1);
	
	$round = false;
	$block = false;
	$address = false;
	
	$mode_error = true;
	
	if ($explore_mode == "rounds") {
		$round_id = intval($uri_parts[3]);
		$q = "SELECT * FROM cached_rounds r LEFT JOIN nations n ON r.winning_nation_id=n.nation_id WHERE r.round_id='".$round_id."';";
		$r = run_query($q);
		if (mysql_numrows($r) == 1) {
			$round = mysql_fetch_array($r);
			$mode_error = false;
			$pagetitle = "EmpireCoin - Results of round #".$round['round_id'];
		}
	}
	if ($explore_mode == "addresses") {
		$address_text = $uri_parts[3];
		$q = "SELECT * FROM addresses WHERE address='".mysql_real_escape_string($address_text)."';";
		$r = run_query($q);
		if (mysql_numrows($r) == 1) {
			$address = mysql_fetch_array($r);
			$mode_error = false;
			$pagetitle = "EmpireCoin - Address: ".$address['address'];
		}
	}
	if ($explore_mode == "blocks") {
		$block_id = intval($uri_parts[3]);
		$q = "SELECT * FROM blocks WHERE block_id='".$block_id."';";
		$r = do_query($q);
		if (mysql_numrows($r) == 1) {
			$block = mysql_fetch_array($r);
			$mode_error = false;
			$pagetitle = "EmpireCoin - Block #".$block['block_id'];
		}
	}
	
	if ($mode_error) $pagetitle = "EmpireCoin - Blockchain Explorer";
	
	include('includes/html_start.php');
	
	if ($thisuser) { ?>
		<div class="container" style="max-width: 1000px; padding-top: 10px;">
			<?php
			$account_value = account_coin_value($thisuser);
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
				if ($round['winning_nation_id'] > 0) echo "<h1>".$round['name']." wins round #".$round['round_id']."</h1>\n";
				else echo "<h1>Round #".$round['round_id'].": No winner</h1>\n";
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
				
				echo "<h2>Rankings</h2>";
				$winner_displayed = FALSE;
				for ($rank=1; $rank<=16; $rank++) {
					$q = "SELECT * FROM nations WHERE nation_id='".$round['position_'.$rank]."';";
					$r = run_query($q);
					if (mysql_numrows($r) == 1) {
						$ranked_nation = mysql_fetch_array($r);
						$nation_score = nation_score_in_round($ranked_nation['nation_id'], $round['round_id']);
						echo '<div class="row';
						if ($nation_score > $max_vote_sum) echo ' redtext';
						else if (!$winner_displayed && $nation_score > 0) { echo ' greentext'; $winner_displayed = TRUE; }
						echo '">';
						echo '<div class="col-md-3">'.$rank.'. '.$ranked_nation['name'].'</div>';
						echo '<div class="col-md-1" style="text-align: center;">'.round(100*$nation_score/$round['total_vote_sum'], 2).'%</div>';
						echo '<div class="col-md-3" style="text-align: right;">'.number_format(round($nation_score/pow(10,8))).' empirecoins</div>';
						echo '</div>'."\n";
					}
				}
				
				echo "<br/>\n";
				
				if ($round['round_id'] > 0) { ?>
					<a href="/explorer/rounds/<?php echo $round['round_id']-1; ?>" style="display: inline-block; margin-right: 30px;">&larr; Previous Round</a>
					<?php
				}
				if ($round['round_id'] < $current_round-1) { ?>
					<a href="/explorer/rounds/<?php echo $round['round_id']+1; ?>">Next Round &rarr;</a>
					<?php
				}
			}
		}
		?>
	</div>
	<?php

	include('includes/html_stop.php');
}
?>