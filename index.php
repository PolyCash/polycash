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
<div class="container" style="max-width: 1000px;">
	<p style="margin-top: 15px;">
		<div class="row">
			<div class="col-sm-2">
				<center>
					<img src="/img/logo/icon-150x150.png" style="width: 100%; max-width: 100px;" />
				</center>
			</div>
			<div class="col-sm-10">
				Welcome to EmpireCoin, the first decentralized voting game on the planet.  In EmpireCoin, you can bet money against players from around the world every thirty minutes in an epic and never-ending struggle for power.  By correctly voting your coins you'll win money, but if you're wrong you won't lose anything.  Do you love gambling, sports betting or speculating on currencies and stocks?  Stop playing rigged games and get in on the first betting game where money is created from thin air and given out to the players. Start building your empire today in this massively multiplayer online game of chance!
				<br/><br/>
				EmpireCoin is currently in beta.  You can <a href="/wallet/">sign up</a> for a beta web wallet to try out the game, and the coin itself will be released soon. For more information, please download the <a href="/EmpireCoin.pdf">EmpireCoin Whitepaper</a>.<br/>
				<a href="/wallet/" class="btn btn-success" style="margin: 5px 0px;">Log In or Sign Up</a>
				<a href="/explorer/rounds/" class="btn btn-primary" style="margin: 5px 0px;">Blockchain Explorer</a>
			</div>
		</div>
		<p>
			<h1>Rules of the Game</h1>
			<ol class="rules_list">
				<li>
					In EmpireCoin, a voting round is concluded approximately thirty minutes, with one of these 16 empires winning each round:
					<div style="max-width: 900px; margin: 8x 0px;">
						<?php
						$nation_q = "SELECT * FROM nations ORDER BY vote_id ASC;";
						$nation_r = run_query($nation_q);
						$n_counter = 1;
						while ($nation = mysql_fetch_array($nation_r)) { ?>
							<div class="nation_box">
								<div class="nation_flag <?php echo strtolower(str_replace(' ', '', $nation['name'])); ?>"></div>
								<div class="nation_flag_label"><?php echo $n_counter.". ".$nation['name']; ?></div>
							</div>
							<?php
							$n_counter++;
						}
						?>
					</div>
				</li>
				<li>Blocks are mined approximately every 3 minutes with the SHA256 algorithm. Miners receive 25 empirecoins per block.</li>
				<li>Blocks are grouped into voting rounds.  Blocks 1 through 10 make up the first round, and every 10 blocks after that are grouped into a round.</li>
				<li>A voting round may have a winning empire.  The winning empire for a round is determined by the votes cast in that round.</li>
				<li>Votes may be cast by creating a voting transaction in which coins are sent to voting addresses.</li>
				<li>A voting address is any address which matches the format for one of the 16 empires.  All other addresses are considered non-voting.</li>
				<li>The winning empire for a round is the eligible empire with the highest number of votes.  Any empire with more than 25% of the votes is ineligible and cannot win the round.</li>
				<li>In case of a tie, the empire with the lowest ID number wins.</li>
				<li>Upon the conclusion of a round, 750 empirecoins are divided up and given out to the winning voters in proportion to the amounts of their votes.</li>
				<li>Votes may only be cast in the first 9 blocks of the round. Transactions can be included in the 10th block of the round but do not count as votes.</li>
			</ol>
		</p>
		<p>
			<h1>Strategy & Gameplay</h1>
			EmpireCoin transactions have a maturity of 8 blocks; this means that coins can be spent every 9 blocks.  Voting rounds consist of 10 blocks but the final block is non-voting, therefore it's only possible to vote each coin once in a voting round. There is no penalty for voting incorrectly except for an optional transaction fee, therefore each stakeholder should vote his or her coins in every round to maximize profits.
		</p>
		<p>
			Because coin rewards are divided up and given out proportionally to the winning team, winning as part of a small group yields the highest rewards.  The 25% voting cap is instituted to avoid voting centralization and to encourage high rewards for winning voters.  A coalition of stakeholders controlling nearly 25% of the currency is ideally positioned to win a round.
		</p>
		<p>
			As in other cryptocurrencies like Bitcoin, miners have veto authority over any transactions included in their block.  Therefore miners have some influence over the outcome of voting rounds and votes which are cast may not always be included in the current voting round.  Votes cast in the 8th or 9th block of the round are likely to be vetoed by corrupt miners, encouraging voters to stake their coins early within the round.
		</p>
		<p>
			<h1>Gamified Proof of Stake</h1>
			EmpireCoin is fundamentally a proof of work cryptocurrency, with SHA256 miners earning 25 empirecoins per block.  But as described above, 75 coins per block are also created per block by proof-of-stake for an inflation of 100 coins per 3-minute block, or approximately 7.5 million coins per year.  Unlike Bitcoin, EmpireCoin avoids block-reward halving in favor of a fixed linear inflation.
		</p>
		<p>
			<h1>Proof of Burn Betting</h1>
			In addition to EmpireCoin's gamified inflation, the EmpireCoin protocol also enables decentralized betting through a unique proof-of-burn protocol.  By sending coins to an address like "china_wins_round_777", anyone can place a bet against other bettors.  If correct, new coins will be created and sent to the winner.  Proof-of-burn bets are completely decentralized, guaranteeing 0% fee bets to all EmpireCoin stakeholders.
		</p>
		<p>
			<h1>Voting Pools</h1>
			EmpireCoin's unique gameplay encourages stakeholders to cooperate and vote together against competing groups.  These groups are called voting pools and anyone can create their own voting pool by coding up an API endpoint which incorporates their custom voting logic.  Or you can join an existing voting pool by entering a voting pool's URL which will control your voting decisions.
		</p>
		<p>
			<h1>Get Started</h1>
			We're still developing EmpireCoin and it's not ready to download just yet.  But you can try out a simulation of the EmpireCoin game for free by signing up for an EmpireCo.in web wallet. We'll give you 1,000 beta EmpireCoins just for signing up.  But remember, this is just a simulation; rules of the game could change and your coins might be lost at any time. Or browse the blockchain by clicking on the blockchain explorer below.<br/>
			<a href="/wallet/" style="margin: 5px 0px;" class="btn btn-success">Sign Up</a>
			<a href="/explorer/rounds/" style="margin: 5px 0px;" class="btn btn-primary">Blockchain Explorer</a>
		</p>
		<p>
			<h1>EmpireCoin API</h1>
			Automated & algorithmic voting strategies are encouraged in EmpireCoin.  After signing up for a web wallet, you can choose from one of several automated voting strategies and then tweak parameters to optimize your votes.  Or you can choose the "Vote by API" voting strategy and then write code to fully customize your voting strategy.  For more information, please visit the <a href="/api/about/">EmpireCoin API page</a>.
		</p>
		<p>
			<?php
			$last_block_id = last_block_id($game['game_id']);
			$current_round = block_to_round($game, $last_block_id+1);
			$block_within_round = $last_block_id%$game['round_length']+1;
			$score_sum = total_score_in_round($game, $current_round, true);
			
			$round_stats = round_voting_stats_all($game, $current_round);
			$nation_id2rank = $round_stats[3];
			
			if ($thisuser) { ?>
				<div class="row">
					<div class="col-md-6">
						<h1>Your current votes</h1>
						<div id="my_current_votes">
							<?php
							echo my_votes_table($thisuser['game_id'], $current_round, $thisuser);
							?>
						</div>
					</div>
				</div>
				<?php
			} ?>
			<div id="current_round_table">
				<?php
				echo current_round_table($game, $current_round, $thisuser, true);
				?>
			</div>
			
			<div id="vote_popups"><?php	echo initialize_vote_nation_details($game, $nation_id2rank, $score_sum, $thisuser['user_id']); ?></div>
			
			<?php
			if ($thisuser) {
				$account_value = account_coin_value($game, $thisuser);
				$immature_balance = immature_balance($game, $thisuser);
				$mature_balance = $account_value - $immature_balance;
			}
			else $mature_balance = 0;
			?>
			<div style="display: none;" id="vote_details_general">
				<?php echo vote_details_general($mature_balance); ?>
			</div>
		</p>
		<br/>
		<br/>
	</p>
</div>

<script type="text/javascript">
var last_block_id = <?php echo $last_block_id; ?>;
var last_transaction_id = <?php echo last_transaction_id($game['game_id']); ?>;
var my_last_transaction_id = <?php echo my_last_transaction_id($thisuser['user_id'], $thisuser['game_id']); ?>;
var mature_io_ids_csv = '<?php echo mature_io_ids_csv($thisuser['user_id'], $game); ?>';
var game_round_length = <?php echo $game['round_length']; ?>;
var game_loop_index = 1;
var last_game_loop_index_applied = -1;

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
</script>
<?php
include('includes/html_stop.php');
?>