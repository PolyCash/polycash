<?php
include("includes/connect.php");
include("includes/get_session.php");
$viewer_id = insert_pageview($thisuser);

$pagetitle = "EmpireCoin - Vote for your empire in the very first decentralized blockchain voting game.";
$nav_tab_selected = "home";
include('includes/html_start.php');

if ($thisuser) { ?>
	<div class="container" style="max-width: 1000px; padding: 10px 0px;">
		<?php
		$account_value = account_coin_value($thisuser);
		include("includes/wallet_status.php");
		?>
	</div>
	<?php
}
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
				Welcome to EmpireCoin, the first decentralized voting game on the planet.  In EmpireCoin, you can bet money against players from around the world every half hour in an epic and never-ending struggle for power.  By correctly voting your coins you'll win money, but if you're wrong you won't lose anything.  Do you love gambling, sports betting or speculating on currencies and stocks?  Stop playing rigged games and get in on the first betting game where money is created from thin air and given out to the players. Start building your empire today in this massively multiplayer online game of chance!
				<br/><br/>
				EmpireCoin is currently in beta.  You can <a href="/wallet/">sign up</a> for a beta web wallet to try out the game, and the coin itself will be released soon. For more information, please download the <a href="/EmpireCoin.pdf">EmpireCoin Whitepaper</a>.<br/>
				<button onclick="window.location='/wallet/';" class="btn btn-success">Log In</button>
				<button onclick="window.location='/wallet/';" class="btn btn-primary">Sign Up</button> 
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
				<li>Votes may be cast by creating a voting transaction in which coins are sent to a voting address.</li>
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
			Because coin rewards are divided up and given out proportionally to the winning team, winning as part of a small group yields the highest rewards.  The 25% voting cap is instituted to avoid voting centralization and to encourage high rewards for winning voters.  A coalition of stakeholders controlling nearly 25% of the currency is ideally positioned to win a round.  But other voters can sacrifice their votes to sabotage such a coalition by voting with them in order to push the empire over the 25% limit.
		</p>
		<p>
			As in other cryptocurrencies such as Bitcoin, empirecoin miners have veto authority over any transactions included in their block.  Therefore miners have some influence over the outcome of voting rounds and votes which are cast may not always be included in the current voting round.  To ensure that votes are counted, users should cast their votes early in the round to make it more likely that an honest miner will include their vote in the round. Votes cast in the 8th or 9th block of the round are likely to be vetoed by corrupt miners and included in the following voting round, potentially tying up a players money in an undesired voting position.
		</p>
		<p>
			<h1>Get Started</h1>
			We're still developing EmpireCoin and it's not ready to download just yet.  But you can try out a simulation of the EmpireCoin game for free by signing up for an EmpireCo.in web wallet. We'll give you 1,000 beta EmpireCoins just for signing up.  But remember, this is just a simulation; rules of the game could change and your coins might be lost at any time.<br/>
			<button style="margin: 10px 0px;" class="btn btn-primary" onclick="window.location='/wallet/';">Sign Up</button>
		</p>
		<p>
			<h1>EmpireCoin API</h1>
			Automated & algorithmic voting strategies are encouraged in EmpireCoin.  After signing up for a web wallet, you can choose from one of several automated voting strategies and then tweak parameters to optimize your votes.  Or you can choose the "Vote by API" voting strategy and then write code to fully customize your voting strategy.  For more information, please visit the <a href="/api/about/">EmpireCoin API page</a>.
		</p>
		<p>
			<?php
			$last_block_id = last_block_id('beta');
			$current_round = block_to_round($last_block_id+1);
			$block_within_round = $last_block_id%get_site_constant('round_length')+1;
			$total_vote_sum = total_votes_in_round($current_round);
			
			$round_stats = round_voting_stats_all($current_round);
			$total_vote_sum = $round_stats[0];
			$nation_id2rank = $round_stats[3];
			
			if ($thisuser) { ?>
				<div class="row">
					<div class="col-md-6">
						<h1>Your current votes</h1>
						<div id="my_current_votes">
							<?php
							echo my_votes_table($current_round, $thisuser);
							?>
						</div>
					</div>
				</div>
				<?php
			} ?>
			<div id="current_round_table">
				<?php
				echo current_round_table($current_round, $thisuser, true);
				?>
			</div>
			
			<div id="vote_popups"><?php	echo initialize_vote_nation_details($nation_id2rank, $total_vote_sum); ?></div>
			
			<?php
			if ($thisuser) {
				$account_value = account_coin_value($thisuser);
				$immature_balance = immature_balance($thisuser);
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
var last_transaction_id = <?php echo last_voting_transaction_id(); ?>;

var refresh_page = "home";
var refresh_in_progress = false;
var selected_nation_id = false;
var user_logged_in = <?php if ($thisuser) echo 'true'; else echo 'false'; ?>;

var homeCarousel;

$(document).ready(function() {
	homeCarousel = new ImageCarousel('home_carousel');
	homeCarousel.initialize();
	refresh_if_needed();
	nation_selected(0);
});

$(".navbar-toggle").click(function(event) {
	$(".navbar-collapse").toggle('in');
});
</script>
<?php
include('includes/html_stop.php');
?>