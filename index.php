<?php
include("includes/connect.php");
include("includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

$pagetitle = "EmpireCoin - Vote for your empire in the very first decentralized blockchain voting game.";
$nav_tab_selected = "home";
include('includes/html_start.php');

if ($thisuser) { ?>
	<div class="container" style="max-width: 1000px; padding: 10px 0px;">
		<?php
		$account_value = account_coin_value($game, $thisuser);
		include("includes/wallet_status.php");
		?>
	</div>
	<?php
}

$q = "SELECT * FROM games WHERE game_id='".get_site_constant('primary_game_id')."';";
$r = run_query($q);
$game = mysql_fetch_array($r);

?>
<div class="container-fluid nopadding">
	<div class="top_banner" id="home_carousel">
		<div class="carouselText">EmpireCoin</div>
	</div>
</div>
<div class="container" style="max-width: 1000px; padding-top: 10px;">
	<div class="row">
		<div class="col-sm-2 text-center">
			<img alt="EmpireCoin Logo" id="home_logo" src="/img/logo/icon-150x150.png" />
		</div>
		<div class="col-sm-10">
			<div class="paragraph">
				EmpireCoin is a game where you can win a tiny number of free coins by casting your votes correctly.  Votes build up over time in proportion to the number of empirecoins that you hold. When you vote for an empire, your votes are used up but your empirecoins are retained. With a great coin staking strategy, you can accumulate empirecoins faster than inflation.  EmpireCoin is equally playable by humans and algorithms.  With the EmpireCoin APIs it's easy to write a custom staking strategy which makes smart, real-time decisions about how to stake your coins.
			</div>
			<div class="paragraph">
				Do you love gambling, sports betting or speculating on currencies and stocks?  Stop paying fees and start playing EmpireCoin: the first blockchain-based coin staking game on the planet. To get started in this massively multiplayer online game of chance, please read the rules below and then <a href="/wallet/">sign up</a> for a beta account.  Or download the <a href="/EmpireCoin.pdf">EmpireCoin Whitepaper</a>.
			</div>
			<div class="paragraph">
				<a href="/wallet/" class="btn btn-success" style="margin: 5px 0px;">Log In or Sign Up</a>
				<a href="/explorer/<?php echo $game['url_identifier']; ?>/rounds/" class="btn btn-primary" style="margin: 5px 0px;">Blockchain Explorer</a>
			</div>
			<div class="paragraph">
				<?php
				echo "EmpireCoin is a cryptocurrency which generates ";
				$blocks_per_hour = 3600/$game['seconds_per_block'];
				$round_reward = ($game['pos_reward']+$game['pow_reward']*$game['round_length'])/pow(10,8);
				$rounds_per_hour = 3600/($game['seconds_per_block']*$game['round_length']);
				$coins_per_hour = $round_reward*$rounds_per_hour;
				$seconds_per_round = $game['seconds_per_block']*$game['round_length'];
				$miner_pct = 100*($game['pow_reward']*$game['round_length'])/($round_reward*pow(10,8));
				
				echo number_format($coins_per_hour)." coins every hour. ";
				echo format_bignum($round_reward)." coins are given out per ".rtrim(format_seconds($seconds_per_round), 's')." voting round. ";
				echo format_bignum($miner_pct);
				?>% of the currency is given to proof of work miners to secure the network and the remaining <?php
				echo format_bignum(100-$miner_pct);
				?>% is given out to stakeholders for casting winning votes.
			</div>
			<?php
			if ($thisuser) { ?>
				<div class="row">
					<div class="col-md-6">
						<div id="my_current_votes">
							<?php
							echo my_votes_table($thisuser['game_id'], $current_round, $thisuser);
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
			$last_block_id = last_block_id($game['game_id']);
			$current_round = block_to_round($game, $last_block_id+1);
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
	<div class="paragraph">
		<h1>Strategy & Gameplay</h1>
		Because coin rewards are divided up and given out proportionally to the winning team, winning as part of a small group yields the highest rewards.  The <?php echo format_bignum(100*$game['max_voting_fraction']); ?>% voting cap is instituted to avoid voting centralization and to encourage high rewards for winning voters.  Players may benefit by forming voting pools and voting together to influence the winning empire.
	</div>
	<div class="paragraph">
		As in other cryptocurrencies like Bitcoin, miners have veto authority over any transactions included in their block.  Because miners have some influence on the winning empire it is possible that your votes may not be counted.
	</div>
	<!--
	<div class="paragraph">
		<h1>Gamified Proof of Stake</h1>
		EmpireCoin is fundamentally a proof of work cryptocurrency, with SHA256 miners earning <?php echo format_bignum($game['pow_reward']/pow(10,8)); ?> empirecoins per block.  Unlike Bitcoin, EmpireCoin avoids block-reward halving in favor of a fixed linear inflation.
	</div>-->
	<div class="paragraph">
		<h1>Voting Pools</h1>
		EmpireCoin's unique gameplay encourages stakeholders to cooperate and vote together against competing factions.  Groups can create voting pools by coding up an API endpoint which incorporates their custom voting logic.  Or you can join an existing voting pool by entering a voting pool's URL which will control your voting decisions.
	</div>
	<div class="paragraph">
		<h1>Get Started</h1>
		We're still developing EmpireCoin and it's not yet ready to download.  But you can try out a simulation of the EmpireCoin game for free by signing up for an <?php echo $GLOBALS['site_name']; ?> web wallet. 
		<?php if ($game['giveaway_status'] == "on") { ?>We'll give you <?php echo format_bignum($game['giveaway_amount']/pow(10,8)); ?> beta empirecoins just for signing up.
		<?php } ?>
		Since EmpireCoin is in beta, please be aware that you may lose your coins at any time.<br/>
		<a href="/wallet/" style="margin: 5px 0px;" class="btn btn-success">Sign Up</a>
		<a href="/explorer/<?php echo $game['url_identifier']; ?>/rounds/" style="margin: 5px 0px;" class="btn btn-primary">Blockchain Explorer</a>
	</div>
	<div class="paragraph">
		<h1>EmpireCoin API</h1>
		Automated & algorithmic voting strategies are encouraged in EmpireCoin.  After signing up for a web wallet, you can choose from one of several automated voting strategies and then tweak parameters to optimize your votes.  Or you can choose the "Vote by API" voting strategy and then write code to fully customize your voting strategy.  For more information, please visit the <a href="/api/about/">EmpireCoin API page</a>.
	</div>
	<div class="paragraph">
		<h1>Proof of Burn Betting</h1>
		In addition to EmpireCoin's gamified inflation, the EmpireCoin protocol also enables decentralized betting through a unique proof-of-burn protocol.  By sending coins to an address like "china_wins_round_777", anyone can place a bet against other bettors.  If correct, new coins will be created and sent to the winner.
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