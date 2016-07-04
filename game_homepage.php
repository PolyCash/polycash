<?php
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

$pagetitle = $game['name']." - Play now and win coins in this blockchain voting game.";
$nav_tab_selected = "game_homepage";
include('includes/html_start.php');

$last_block_id = last_block_id($game['game_id']);
$current_round = block_to_round($game, $last_block_id+1);
?>
<div class="container" style="max-width: 1000px; padding-top: 10px;">
	<div class="paragraph">
		<a href="/">&larr; All <?php echo $GLOBALS['coin_brand_name']; ?> Games</a>
	</div>
	<div class="paragraph">
		<h1 style="margin-bottom: 2px;"><?php echo $game['name']; ?></h1>
		<div class="row">
			<div class="col-md-6">
				<h4><?php echo $game['variation_name']; ?></h4>
				<?php
				$blocks_per_hour = 3600/$game['seconds_per_block'];
				$seconds_per_round = $game['seconds_per_block']*$game['round_length'];
				$round_reward = (coins_created_in_round($game, $current_round))/pow(10,8);

				if ($game['inflation'] == "linear") {
					$miner_pct = 100*($game['pow_reward']*$game['round_length'])/($round_reward*pow(10,8));
				}
				else $miner_pct = 100*$game['exponential_inflation_minershare'];
				?>
				<div class="paragraph">
					<?php
					if ($game['inflation'] == "linear") {
						$rounds_per_hour = 3600/($game['seconds_per_block']*$game['round_length']);
						$coins_per_hour = $round_reward*$rounds_per_hour;
						echo "In this game, ";
						echo number_format($coins_per_hour)." ".$game['coin_name_plural']." are generated every hour. ";
						echo format_bignum($round_reward)." ".$game['coin_name_plural']." are given out per ".rtrim(format_seconds($seconds_per_round), 's')." voting round. ";
					}
					else {
						echo "In this game, ".$game['coin_name_plural']." experience an inflation of ".(100*$game['exponential_inflation_rate'])."% every ".format_seconds($seconds_per_round).". ";
					}
					
					echo format_bignum($miner_pct);
					?>% of the currency is given to proof of work miners for securing the network and the remaining <?php
					echo format_bignum(100-$miner_pct);
					?>% is given out to the players for winning votes.<?php
					?>
				</div>
				<?php
				if ($game['giveaway_status'] == "public_pay" || $game['giveaway_status'] == "invite_pay") {
					$q = "SELECT * FROM currencies WHERE currency_id='".$game['invite_currency']."';";
					$r = run_query($q);
					if (mysql_numrows($r) > 0) {
						$invite_currency = mysql_fetch_array($r);
						echo '<div class="paragraph">';
						
						$receive_disp = format_bignum($game['giveaway_amount']/pow(10,8));
						echo 'You can join this game by buying '.$receive_disp.' ';
						if ($receive_disp == '1') echo $game['coin_name'];
						else echo $game['coin_name_plural'];
						
						$buyin_disp = format_bignum($game['invite_cost']);
						echo ' for '.$buyin_disp.' ';
						echo $invite_currency['short_name'];
						if ($buyin_disp != '1') echo "s";
						echo ". ";

						if ($game['game_status'] == "running") {
							echo "This game started ".format_seconds(time()-$game['start_time'])." ago; ".format_bignum(coins_in_existence($game, false)/pow(10,8))." ".$game['coin_name_plural']."  are already in circulation. ";
						}
						else {
							if ($game['start_condition'] == "fixed_time") {
								$unix_starttime = strtotime($game['start_datetime']);
								echo "This game starts in ".format_seconds($unix_starttime-time())." at ".date("M j, Y g:ia", $unix_starttime).". ";
							}
							else {
								$current_players = paid_players_in_game($game);
								echo "This game will start when ".$game['start_condition_players']." player";
								if ($game['start_condition_players'] == 1) echo " joins";
								else echo "s have joined";
								echo ". ".($game['start_condition_players']-$current_players)." player";
								if ($game['start_condition_players']-$current_players == 1) echo " is";
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
					In this game you can win <?php echo $game['coin_name_plural']; ?> by casting your votes correctly.  Votes build up over time based on the number of <?php echo $game['coin_name_plural']; ?> that you hold, and when you vote for <?php echo prepend_a_or_an($game['option_name']); ?>, your votes are used up but your <?php echo $game['coin_name_plural']; ?> are retained. By collaborating with your teammates against competing groups, you can make real money by accumulating money faster than the other players.  Through the <?php echo $GLOBALS['coin_brand_name']; ?> APIs you can even code up a custom strategy which makes smart, real-time decisions about how to cast your votes.
				</div>
				<div class="paragraph">
					<a href="/wallet/<?php echo $game['url_identifier']; ?>/" class="btn btn-success">Play Now</a>
					<a href="/explorer/<?php echo $game['url_identifier']; ?>/rounds/" class="btn btn-primary">Blockchain Explorer</a>
				</div>
			</div>
			<div class="col-md-6">
				<h4><?php echo $game['type_name']; ?></h4>
				<div style="border: 1px solid #ccc; padding: 10px;">
					<?php echo game_info_table($game); ?>
				</div>
			</div>
		</div>

		<?php
		if ($thisuser) { ?>
			<div class="row">
				<div class="col-md-6">
					<div id="my_current_votes">
						<?php
						echo my_votes_table($game, $current_round, $thisuser);
						?>
					</div>
				</div>
			</div>
			<?php
		}
		?>
	</div>
	<div class="paragraph">
		<h1>Rules of the Game</h1>
		
		<ol class="rules_list">
			<li>Players can vote on these <?php echo $game['num_voting_options']." ".$game['option_name_plural']." every ".format_seconds($seconds_per_round); ?> by submitting a voting transaction.</li>
			<?php
			$block_within_round = $last_block_id%$game['round_length']+1;
			$score_sums = total_score_in_round($game, $current_round, true);
			
			$round_stats = round_voting_stats_all($game, $current_round);
			$option_id2rank = $round_stats[3];
			?>
			<div id="current_round_table" style="margin-bottom: 10px;">
				<?php
				echo current_round_table($game, $current_round, $thisuser, false);
				?>
			</div>
			
			<li>Voting transactions are only counted if they are confirmed in a voting block. All blocks are voting blocks except for the final transaction of each round.</li>
			<li>Blocks are mined approximately every <?php echo format_seconds($game['seconds_per_block']); ?> by the SHA256 algorithm. 
			<?php if ($game['inflation'] == "linear") { ?>Miners receive <?php echo format_bignum($game['pow_reward']/pow(10,8))." ".$game['coin_name_plural']; ?> per block.<?php } ?></li>
			<li>Blocks are grouped into voting rounds.  Blocks 1 through <?php echo $game['round_length']; ?> make up the first round, and every subsequent <?php echo $game['round_length']; ?> blocks are grouped into a round.</li>
			<li>A voting round will have a winning <?php echo $game['option_name']; ?> if at least one <?php echo $game['option_name']; ?> receives votes but is not disqualified.</li>
			<li>Any <?php echo $game['option_name']; ?> with more than <?php echo format_bignum(100*$game['max_voting_fraction']); ?>% of the votes is disqualified from winning the round.</li>
			<li>The eligible <?php echo $game['option_name']; ?> with the most votes wins the round.</li>
			<li>In case of a tie, the <?php echo $game['option_name']; ?> with the lowest ID number wins.</li>
			<li>When a round ends <?php
			if ($game['inflation'] == "linear") {
				echo format_bignum($game['pos_reward']/pow(10,8))." ".$game['coin_name_plural']." are divided up";
			}
			else echo format_bignum(100*$game['exponential_inflation_rate']).'% is added to the currency supply';
			?> and given to the winning voters in proportion to their votes.</li>
		</ol>
	</div>

	<div class="paragraph text-center">
		<?php echo $GLOBALS['site_name'].", ".date("Y"); ?>
	</div>

	<div class="paragraph">
		<div id="vote_popups"><?php
		echo initialize_vote_option_details($game, $option_id2rank, $score_sums['sum'], $thisuser['user_id']);
		?></div>
		
		<?php
		if ($thisuser) {
			$account_value = account_coin_value($game, $thisuser);
			$immature_balance = immature_balance($game, $thisuser);
			$mature_balance = mature_balance($game, $thisuser);
		}
		else $mature_balance = 0;
		?>
		<div style="display: none;" id="vote_details_general">
			<?php echo vote_details_general($mature_balance); ?>
		</div>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
var last_block_id = <?php echo $last_block_id; ?>;
var last_transaction_id = <?php echo last_transaction_id($game['game_id']); ?>;
var my_last_transaction_id = <?php echo my_last_transaction_id($thisuser['user_id'], $thisuser['game_id']); ?>;
var mature_io_ids_csv = '<?php echo mature_io_ids_csv($thisuser['user_id'], $game); ?>';
var game_round_length = <?php echo $game['round_length']; ?>;
var game_id = <?php echo $game['game_id']; ?>;
var game_loop_index = 1;
var num_voting_options = <?php echo $game['num_voting_options']; ?>;

var coin_name = '<?php echo $game['coin_name']; ?>';
var coin_name_plural = '<?php echo $game['coin_name_plural']; ?>';

var last_game_loop_index_applied = -1;
var min_bet_round = <?php
	$bet_round_range = bet_round_range($game);
	echo $bet_round_range[0];
?>;
var option_has_votingaddr = [];
for (var i=1; i<=num_voting_options; i++) { option_has_votingaddr[i] = false; }
var votingaddr_count = 0;

var refresh_page = "home";
var refresh_in_progress = false;
var last_refresh_time = 0;
var selected_option_id = false;
var user_logged_in = <?php if ($thisuser) echo 'true'; else echo 'false'; ?>;

var homeCarousel;

$(document).ready(function() {
	homeCarousel = new ImageCarousel('home_carousel');
	homeCarousel.initialize();
	option_selected(0);
	game_loop_event();
});

$(".navbar-toggle").click(function(event) {
	$(".navbar-collapse").toggle('in');
});
//]]>
</script>
<?php
include('includes/html_stop.php');
?>