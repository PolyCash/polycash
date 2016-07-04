<?php
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

$pagetitle = $game['name']." - Play now and win coins in this blockchain voting game.";
$nav_tab_selected = "home";
include('includes/html_start.php');

$last_block_id = last_block_id($game['game_id']);
$current_round = block_to_round($game, $last_block_id+1);

if ($thisuser) { ?>
	<div class="container" style="max-width: 1000px; padding: 10px 0px;">
		<?php
		$account_value = account_coin_value($game, $thisuser);
		include("includes/wallet_status.php");
		?>
	</div>
	<?php
}

?>
<div class="container-fluid nopadding">
	<div class="top_banner" id="home_carousel">
		<div class="carouselText"><h1><?php echo $game['name']; ?></h1></div>
	</div>
</div>
<div class="container" style="max-width: 1000px; padding-top: 10px;">
	<div class="row">
		<div class="col-sm-2 text-center">
			<img alt="EmpireCoin Logo" id="home_logo" src="/img/logo/icon-150x150.png" />
		</div>
		<div class="col-sm-10">
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
				<a href="/">&larr; All EmpireCoin Games</a>
			</div>
			<div class="paragraph">
				<?php
				if ($game['inflation'] == "linear") {
					$rounds_per_hour = 3600/($game['seconds_per_block']*$game['round_length']);
					$coins_per_hour = $round_reward*$rounds_per_hour;
					echo $game['name']." is a cryptocurrency which generates ";
					echo number_format($coins_per_hour)." coins every hour. ";
					echo format_bignum($round_reward)." coins are given out per ".rtrim(format_seconds($seconds_per_round), 's')." voting round. ";
					echo format_bignum($miner_pct);
					?>% of the currency is given to proof of work miners for securing the network and the remaining <?php
					echo format_bignum(100-$miner_pct);
					?>% is given out to stakeholders for casting winning votes.<?php
				}
				else {
					echo $game['name']." is a cryptocurrency with ".(100*$game['exponential_inflation_rate'])."% inflation every ".format_seconds($seconds_per_round).". ";
					echo format_bignum($miner_pct);
					?>% of the currency is given to proof of work miners for securing the network and the remaining <?php
					echo format_bignum(100-$miner_pct);
					?>% is given out to stakeholders for casting winning votes.<?php
				}
				?>
			</div>
			<div class="paragraph">
				In this game you can win free coins by casting your votes correctly.  Votes build up over time based on the number of coins that you hold, and when you vote for an empire, your votes are used up but your coins are retained. By collaborating with your teammates against competing groups, you can make real money by accumulating coins faster than inflation.  Through the EmpireCoin APIs you can even code up a custom strategy which makes smart, real-time decisions about how to cast your votes.
			</div>
			<div class="paragraph">
				<a href="/wallet/<?php echo $game['url_identifier']; ?>/" class="btn btn-success">Log In or Sign Up</a>
				<a href="/explorer/<?php echo $game['url_identifier']; ?>/rounds/" class="btn btn-primary">Blockchain Explorer</a>
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
	</div>
	<div class="paragraph">
		<h1>Rules of the Game</h1>
		
		<ol class="rules_list">
			<li>Coin holders can stake their coins for one of these <?php echo $game['num_voting_options']; ?> empires every <?php echo format_seconds($seconds_per_round); ?> by submitting a voting transaction.</li>
			<?php
			$block_within_round = $last_block_id%$game['round_length']+1;
			$score_sums = total_score_in_round($game, $current_round, true);
			
			$round_stats = round_voting_stats_all($game, $current_round);
			$nation_id2rank = $round_stats[3];
			?>
			<div id="current_round_table" style="margin-bottom: 10px;">
				<?php
				echo current_round_table($game, $current_round, $thisuser, false);
				?>
			</div>
			
			<li>Voting transactions are only counted if they are confirmed in a voting block. All blocks are voting blocks except for the final transaction of each round.</li>
			<li>Blocks are mined approximately every <?php echo format_seconds($game['seconds_per_block']); ?> by the SHA256 algorithm. Miners receive <?php echo format_bignum($game['pow_reward']/pow(10,8)); ?> empirecoins per block.</li>
			<li>Blocks are grouped into voting rounds.  Blocks 1 through <?php echo $game['round_length']; ?> make up the first round, and every subsequent <?php echo $game['round_length']; ?> blocks are grouped into a round.</li>
			<li>A voting round will have a winning empire if at least one empire receives votes but is not disqualified.</li>
			<li>Any empire with more than <?php echo format_bignum(100*$game['max_voting_fraction']); ?>% of the votes is disqualified from winning the round.</li>
			<li>The eligible empire with the most votes wins the round.</li>
			<li>In case of a tie, the empire with the lowest ID number wins.</li>
			<li>When a round ends <?php echo format_bignum($game['pos_reward']/pow(10,8)); ?> empirecoins are divided up and given out to the winning voters in proportion to the amounts of their votes.</li>
		</ol>
	</div>

	<div class="paragraph text-center">
		<?php echo $GLOBALS['site_name'].", ".date("Y"); ?>
	</div>

	<div class="paragraph">
		<div id="vote_popups"><?php
		echo initialize_vote_nation_details($game, $nation_id2rank, $score_sums['sum'], $thisuser['user_id']);
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
var last_game_loop_index_applied = -1;
var min_bet_round = <?php
	$bet_round_range = bet_round_range($game);
	echo $bet_round_range[0];
?>;
var nation_has_votingaddr = [];
for (var i=1; i<=16; i++) { nation_has_votingaddr[i] = false; }
var votingaddr_count = 0;

var refresh_page = "home";
var refresh_in_progress = false;
var last_refresh_time = 0;
var selected_nation_id = false;
var user_logged_in = <?php if ($thisuser) echo 'true'; else echo 'false'; ?>;

var homeCarousel;

$(document).ready(function() {
	homeCarousel = new ImageCarousel('home_carousel');
	homeCarousel.initialize();
	nation_selected(0);
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