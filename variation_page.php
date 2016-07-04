<?php
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

$pagetitle = $game_variation['type_name']." - ".$game_variation['variation_name']." - ".$GLOBALS['coin_brand_name'];
$nav_tab_selected = "game_homepage";
include('includes/html_start.php');

$user_game = false;
if ($game_variation) {
	$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.user_id='".$thisuser['user_id']."' AND g.variation_id='".$game_variation['variation_id']."';";
	$r = run_query($q);
	if (mysql_numrows($r) == 1) {
		$user_game = mysql_fetch_array($r);
	}
}
else die("Error: you've reach an invalid URL.");

if ($_REQUEST['action'] == "join") { ?>
	<script type="text/javascript">
	$(document).ready(function() {
		join_game_variation(<?php echo $game_variation['variation_id']; ?>);
	});
	</script>
	<?php
}
?>
<div class="container" style="max-width: 1000px; padding-top: 10px;">
	<div class="paragraph">
		<a href="/">&larr; All <?php echo $GLOBALS['coin_brand_name']; ?> Games</a>
	</div>
	<div class="paragraph">
		<div class="row">
			<div class="col-md-6">
				<h2><?php echo $game_variation['variation_name']; ?></h2>
				<?php
				$blocks_per_hour = 3600/$game_variation['seconds_per_block'];
				$seconds_per_round = $game_variation['seconds_per_block']*$game_variation['round_length'];
				$round_reward = (coins_created_in_round($game_variation, $current_round))/pow(10,8);

				if ($game_variation['inflation'] == "linear") {
					$miner_pct = 100*($game_variation['pow_reward']*$game_variation['round_length'])/($round_reward*pow(10,8));
				}
				else $miner_pct = 100*$game_variation['exponential_inflation_minershare'];
				?>
				<div class="paragraph">
					<?php
					if ($game_variation['inflation'] == "linear") {
						$rounds_per_hour = 3600/($game_variation['seconds_per_block']*$game_variation['round_length']);
						$coins_per_hour = $round_reward*$rounds_per_hour;
						echo "In this game, ";
						echo number_format($coins_per_hour)." ".$game_variation['coin_name_plural']." are generated every hour. ";
						echo format_bignum($round_reward)." ".$game_variation['coin_name_plural']." are given out per ".rtrim(format_seconds($seconds_per_round), 's')." voting round. ";
					}
					else {
						echo "In this game, ".$game_variation['coin_name_plural']." experience an inflation of ".(100*$game_variation['exponential_inflation_rate'])."% every ".format_seconds($seconds_per_round).". ";
					}
					
					echo format_bignum($miner_pct);
					?>% of the currency is given to proof of work miners for securing the network and the remaining <?php
					echo format_bignum(100-$miner_pct);
					?>% is given out to the players for winning votes.<?php
					?>
				</div>
				<?php
				if ($game_variation['giveaway_status'] == "public_pay" || $game_variation['giveaway_status'] == "invite_pay") {
					$q = "SELECT * FROM currencies WHERE currency_id='".$game_variation['invite_currency']."';";
					$r = run_query($q);
					if (mysql_numrows($r) > 0) {
						$invite_currency = mysql_fetch_array($r);
						echo '<div class="paragraph">';
						
						$receive_disp = format_bignum($game_variation['giveaway_amount']/pow(10,8));
						if ($game_variation['giveaway_status'] == "invite_pay" || $game_variation['giveaway_status'] == "invite_free") echo "You need an invitation to join this game. After receiving an invitation you can join";
						else echo 'You can join this game';
						echo ' by buying '.$receive_disp.' ';
						if ($receive_disp == '1') echo $game_variation['coin_name'];
						else echo $game_variation['coin_name_plural'];
						
						$buyin_disp = format_bignum($game_variation['invite_cost']);
						echo ' for '.$buyin_disp.' ';
						echo $invite_currency['short_name'];
						if ($buyin_disp != '1') echo "s";
						echo ". ";

						if ($game_variation['game_status'] == "running") {
							echo "This game started ".format_seconds(time()-$game_variation['start_time'])." ago; ".format_bignum(coins_in_existence($game_variation, false)/pow(10,8))." ".$game_variation['coin_name_plural']."  are already in circulation. ";
						}
						else {
							if ($game_variation['start_condition'] == "fixed_time") {
								$unix_starttime = strtotime($game_variation['start_datetime']);
								echo "This game starts in ".format_seconds($unix_starttime-time())." at ".date("M j, Y g:ia", $unix_starttime).". ";
							}
							else {
								$current_players = paid_players_in_game($game_variation);
								echo "This game will start when ".$game_variation['start_condition_players']." player";
								if ($game_variation['start_condition_players'] == 1) echo " joins";
								else echo "s have joined";
								echo ". ".($game_variation['start_condition_players']-$current_players)." player";
								if ($game_variation['start_condition_players']-$current_players == 1) echo " is";
								else echo "s are";
								echo " needed, ".$current_players;
								if ($current_players == 1) echo " has";
								else echo " have";
								echo " already joined. ";
							}
						}
						echo '</div>';
					}
				}
				?>
				<div class="paragraph">
					In this game you can win <?php echo $game_variation['coin_name_plural']; ?> by casting your votes correctly.  Votes build up over time based on the number of <?php echo $game_variation['coin_name_plural']; ?> that you hold.  When you vote for <?php echo prepend_a_or_an($game_variation['option_name']); ?>, your votes are used up but your <?php echo $game_variation['coin_name_plural']; ?> are retained. By collaborating with your teammates against competing groups, you can make money by accumulating <?php echo $game_variation['coin_name_plural']; ?> faster than the other players.  Through the <?php echo $GLOBALS['coin_brand_name']; ?> API you can code up a custom strategy which makes smart, real-time decisions about how to cast your votes.
				</div>
				<div class="paragraph">
					<button class="btn btn-success" onclick="join_game_variation(<?php echo $game_variation['variation_id']; ?>);">Play Now</button>
				</div>
			</div>
			<div class="col-md-6">
				<h2><?php echo $game_variation['type_name']; ?></h2>
				<div style="border: 1px solid #ccc; padding: 10px;">
					<?php echo game_info_table($game_variation); ?>
				</div>
			</div>
		</div>
	</div>
	
	<div class="paragraph">
		<h2>Rules of the Game</h2>
		
		<ol class="rules_list">
			<li>Players can vote on these <?php echo $game_variation['num_voting_options']." ".$game_variation['option_name_plural']." every ".format_seconds($seconds_per_round); ?> by submitting a voting transaction.</li>
			
			<div style="margin-bottom: 10px;">
				<div class="row">
					<?php
					$q = "SELECT * FROM voting_options vo LEFT JOIN images i ON vo.default_image_id=i.image_id WHERE vo.option_group_id='".$game_variation['option_group_id']."';";
					$r = run_query($q);
					$i = 0;
					while ($voting_option = mysql_fetch_array($r)) {
						echo '
						<div class="col-md-3">
							<div class="vote_option_box">
								<table style="width: 100%">
									<tr>';
						if ($voting_option['image_id'] > 0) echo '
										<td>
											<div class="vote_option_image" style="background-image: url(\'/img/custom/'.$voting_option['image_id'].'_'.$voting_option['access_key'].'.'.$voting_option['extension'].'\');"></div>
										</td>';
						echo '
										<td style="width: 100%;">
											<span style="float: left;">
												<div class="vote_option_label">'.($i+1).'. '.$voting_option['name'].'</div>
											</span>
											<span style="float: right; padding-right: 5px;">0%
											</span>
										</td>
									</tr>
								</table>
							</div>
						</div>';
						
						if ($i%4 == 1) $html .= '</div><div class="row">';
						
						$i++;
					}
					?>
				</div>
			</div>
			
			<li>Voting transactions are only counted if they are confirmed in a voting block. All blocks are voting blocks except for the final block of each round.</li>
			<li>Blocks are mined approximately every <?php echo format_seconds($game_variation['seconds_per_block']); ?> by the SHA256 algorithm. 
			<?php if ($game_variation['inflation'] == "linear") { ?>Miners receive <?php echo format_bignum($game_variation['pow_reward']/pow(10,8))." ".$game_variation['coin_name_plural']; ?> per block.<?php } ?></li>
			<li>Blocks are grouped into voting rounds.  Blocks 1 through <?php echo $game_variation['round_length']; ?> make up the first round, and every subsequent <?php echo $game_variation['round_length']; ?> blocks are grouped into a round.</li>
			<li>A voting round will have a winning <?php echo $game_variation['option_name']; ?> if at least one <?php echo $game_variation['option_name']; ?> receives votes but is not disqualified.</li>
			<li>Any <?php echo $game_variation['option_name']; ?> with more than <?php echo format_bignum(100*$game_variation['max_voting_fraction']); ?>% of the votes is disqualified from winning the round.</li>
			<li>The eligible <?php echo $game_variation['option_name']; ?> with the most votes wins the round.</li>
			<li>In case of a tie, the <?php echo $game_variation['option_name']; ?> with the lowest ID number wins.</li>
			<li>When a round ends <?php
			if ($game_variation['inflation'] == "linear") {
				echo format_bignum($game_variation['pos_reward']/pow(10,8))." ".$game_variation['coin_name_plural']." are divided up";
			}
			else echo format_bignum(100*$game_variation['exponential_inflation_rate']).'% is added to the currency supply';
			?> and given to the winning voters in proportion to their votes.</li>
		</ol>
	</div>

	<div class="paragraph text-center">
		<?php echo $GLOBALS['site_name'].", ".date("Y"); ?>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
$(".navbar-toggle").click(function(event) {
	$(".navbar-collapse").toggle('in');
});
//]]>
</script>
<?php
include('includes/html_stop.php');
?>