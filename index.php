<?php
include("includes/connect.php");
include("includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

$pagetitle = "EmpireCoin - Vote for your empire in the first decentralized blockchain voting game.";
$nav_tab_selected = "home";
include('includes/html_start.php');
?>
<div class="container-fluid nopadding">
	<div class="top_banner" id="home_carousel">
		<div class="carouselText"><h1>EmpireCoin</h1></div>
	</div>
</div>
<div class="container" style="max-width: 1000px; padding-top: 10px;">
	<div class="row">
		<div class="col-sm-2 text-center">
			<img alt="EmpireCoin Logo" id="home_logo" src="/img/logo/icon-150x150.png" />
		</div>
		<div class="col-sm-10">
			<div class="paragraph">
				Welcome to EmpireCoin, the first decentralized voting game on the planet. EmpireCoin is a cryptocurrency where you can compete against players around the world in epic coin battles. Buy votes and collude with your teammates or betray your enemies to maximize your net worth.  Do you love gambling, sports betting or speculating on currencies and stocks? Stop playing rigged games and get in on the first provably fair betting game where money is created from thin air and given out to the players. Start building your empire today in this massively multiplayer online game of chance.
			</div>
			<div class="paragraph">
				For more information, please download the <a href="/EmpireCoin.pdf">EmpireCoin Whitepaper</a>.
			</div>
		</div>
	</div>

	<div class="paragraph">
		<h2>EmpireCoin Private Games</h2>
		In addition to the EmpireCoin decentralized currency, EmpireCoin's unique software allows anyone to create a private currency backed by dollars or Bitcoins which incorporates EmpireCoin gameplay.  Users can create a private game and then invite friends to buy in and play for real money.  Unlike the official EmpireCoin currency, private games end after a certain number of rounds and the money that was initially contributed is given out in proportion to players' final balances.
	</div>

	<div class="paragraph">
		We're still developing the decentralized version of EmpireCoin and it's not yet ready to download.  But you can get involved with EmpireCoin now by joining one of these private games and buying in with Bitcoins or dollars:<br/>
		<?php
		$q = "SELECT g.*, c.short_name AS currency_short_name FROM games g LEFT JOIN currencies c ON g.invite_currency=c.currency_id WHERE g.featured=1 AND (g.game_status='editable' OR g.game_status='running');";
		$r = run_query($q);
		echo '<div class="row">';
		$counter = 0;
		while ($featured_game = mysql_fetch_array($r)) {
			$blocks_per_hour = 3600/$featured_game['seconds_per_block'];
			$round_reward = ($featured_game['pos_reward']+$featured_game['pow_reward']*$featured_game['round_length'])/pow(10,8);
			$rounds_per_hour = 3600/($featured_game['seconds_per_block']*$featured_game['round_length']);
			$coins_per_hour = $round_reward*$rounds_per_hour;
			$seconds_per_round = $featured_game['seconds_per_block']*$featured_game['round_length'];
			$coins_per_block = format_bignum($featured_game['pow_reward']/pow(10,8));

			echo '<div class="col-md-6">';

			echo '<h3>'.$featured_game['name'].'</h3>';
			if ($featured_game['giveaway_status'] == "invite_pay" || $featured_game['giveaway_status'] == "public_pay") {
				echo "To join this game, buy ".format_bignum($featured_game['giveaway_amount']/pow(10,8))." ".$featured_game['coin_name_plural']." (".round((100*$featured_game['giveaway_amount']/coins_in_existence($featured_game, false)), 2)."% of the coins) for ".format_bignum($featured_game['invite_cost'])." ".$featured_game['currency_short_name']."s";
			}
			else echo "Join this game and get ".format_bignum($featured_game['giveaway_amount']/pow(10,8))." ".$featured_game['coin_name_plural']." (".round((100*$featured_game['giveaway_amount']/coins_in_existence($featured_game, false)), 2)."% of the coins) for free";
			echo ". ";

			if ($featured_game['game_status'] == "running") {
				echo "This game started ".format_seconds(time()-$featured_game['start_time'])." ago; ".format_bignum(coins_in_existence($featured_game, false)/pow(10,8))." ".$featured_game['coin_name_plural']."  are already in circulation.";

			}
			else {
				if ($featured_game['start_condition'] == "fixed_time") {
					$unix_starttime = strtotime($featured_game['start_datetime']);
					echo "This game starts in ".format_seconds($unix_starttime-time())." at ".date("M j, Y g:ia", $unix_starttime).". ";
				}
				else {
					$current_players = paid_players_in_game($featured_game);
					echo "This game will start when ".$featured_game['start_condition_players']." player";
					if ($featured_game['start_condition_players'] == 1) echo " joins";
					else echo "s have joined";
					echo ". ".($featured_game['start_condition_players']-$current_players)." player";
					if ($featured_game['start_condition_players']-$current_players == 1) echo " is";
					else echo "s are";
					echo " needed, ".$current_players;
					if ($current_players == 1) echo " has";
					else echo " have";
					echo " already joined. ";
				}
			}

			if ($featured_game['final_round'] > 0) {
				$game_total_seconds = $seconds_per_round*$featured_game['final_round'];
				echo "This game will last ".$featured_game['final_round']." rounds (".format_seconds($game_total_seconds)."). ";
			}
			else echo "This game doesn't end, but you can sell out at any time. ";

			echo '';
			if ($featured_game['inflation'] == "linear") {
				echo "This coin has linear inflation: ".format_bignum($round_reward)." ".$featured_game['coin_name_plural']." are minted approximately every ".format_seconds($seconds_per_round);
				echo " (".format_bignum($coins_per_hour)." coins per hour)";
				echo ". In each round, ".format_bignum($featured_game['pos_reward']/pow(10,8))." ".$featured_game['coin_name_plural']." are given to voters and ".format_bignum($featured_game['pow_reward']*$featured_game['round_length']/pow(10,8))." ".$featured_game['coin_name_plural']." are given to miners";
				echo " (".$coins_per_block." coin";
				if ($coins_per_block != 1) echo "s";
				echo " per block). ";
			}
			else echo "This currency grows by ".(100*$featured_game['exponential_inflation_rate'])."% per round. ".(100 - 100*$featured_game['exponential_inflation_minershare'])."% is given to voters and ".(100*$featured_game['exponential_inflation_minershare'])."% is given to miners every ".format_seconds($seconds_per_round).". ";

			echo "Each round consists of ".$featured_game['round_length'].", ".str_replace(" ", "-", rtrim(format_seconds($featured_game['seconds_per_block']), 's'))." blocks. ";
			if ($featured_game['maturity'] > 0) {
				echo ucwords($featured_game['coin_name_plural'])." are locked for ";
				echo $featured_game['maturity']." block";
				if ($featured_game['maturity'] != 1) echo "s";
				echo " when spent. ";
			}

			echo "<br/>\n";
			echo '<a class="btn btn-primary" style="margin-top: 5px;" href="/'.$featured_game['url_identifier'].'">Join '.$featured_game['name'].'</a>';

			echo '</div>';
			if ($counter%2 == 1) echo '</div><div class="row">';
			$counter++;
		}
		echo '</div>';
		?>
	</div>

	<div class="paragraph">
		<h2>Strategy &amp; Gameplay</h2>
		The winning empire for an EmpireCoin voting round is determined entirely by the votes of the players.  Therefore, winning in EmpireCoin is all about colluding with other players and organizing against competing factions.  Players can form voting pools and vote together to influence the winning empire.
	</div>
	<div class="paragraph">
		As in other cryptocurrencies like Bitcoin, miners have veto authority over any transactions included in their block.  Because miners have some influence on the winning empire it is possible that your votes may not be counted.
	</div>
	<div class="paragraph">
		<h2>Voting Pools</h2>
		EmpireCoin's unique gameplay encourages stakeholders to cooperate and vote together against the other teams.  Groups can create voting pools by coding up an API endpoint which incorporates their custom voting logic.  You can assign your voting decisions to a voting pool by entering it's URL into your web wallet.
	</div>
	<div class="paragraph">
		<h2>EmpireCoin API</h2>
		Automated &amp; algorithmic voting strategies are encouraged in EmpireCoin.  After signing up for a web wallet, you can choose from one of several automated voting strategies and then tweak parameters to optimize your votes.  Or you can choose the "Vote by API" voting strategy and then write code to fully customize your voting strategy.  For more information, please visit the <a href="/api/about/">EmpireCoin API page</a>.
	</div>
	<div class="paragraph">
		<h2>Proof of Burn Betting</h2>
		In addition to EmpireCoin's gamified inflation, the EmpireCoin protocol also enables decentralized betting through a unique proof-of-burn protocol.  By sending coins to an address like "china_wins_round_777", anyone can place a bet against other bettors.  If correct, new coins will be created and sent to the winner.
	</div>

	<div class="paragraph text-center">
		<?php echo $GLOBALS['site_name'].", ".date("Y"); ?>
	</div>
</div>
<script type="text/javascript">
//<![CDATA[
var homeCarousel;

$(document).ready(function() {
	homeCarousel = new ImageCarousel('home_carousel');
	homeCarousel.initialize();
});
//]]>
</script>
<?php
include('includes/html_stop.php');
?>