<?php
include("includes/connect.php");
include("includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

$pagetitle = "EmpireCoin - Vote for your empire in the very first decentralized blockchain voting game.";
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
				Welcome to EmpireCoin, the first decentralized voting game on the planet. EmpireCoin is a cryptocurrency where you can bet money against players from around the world every day in an epic and never-ending struggle for power. By correctly voting your coins you'll win money, but if you're wrong you won't lose anything. Do you love gambling, sports betting or speculating on currencies and stocks? Stop playing rigged games and get in on the first betting game where money is created from thin air and given out to the players. Start building your empire today in this massively multiplayer online game of chance!
			</div>
			<div class="paragraph">
				For more information, please download the <a href="/EmpireCoin.pdf">EmpireCoin Whitepaper</a>.
			</div>
		</div>
	</div>

	<div class="paragraph">
		<h2>EmpireCoin Private Games</h2>
		In addition to the EmpireCoin decentralized currency, EmpireCoin's unique software allows anyone to create a private currency backed by dollars or Bitcoins which incorporates EmpireCoin gameplay.  Users can create a private game and then invite friends to buy in and play for real money.  Unlike the official EmpireCoin currency, private games end after a certain number of rounds and the money that was initially paid in is split up and given out in proportion to players' final balances.
	</div>

	<div class="paragraph">
		We're still developing the decentralized version of EmpireCoin and it's not yet ready to download.  But you can get involved with EmpireCoin now by joining one of these private games and buying in with Bitcoins or dollars:<br/>
		<?php
		$q = "SELECT g.*, c.short_name AS currency_short_name FROM games g LEFT JOIN currencies c ON g.invite_currency=c.currency_id WHERE featured=1;";
		$r = run_query($q);
		echo '<div class="row">';
		$counter = 0;
		while ($featured_game = mysql_fetch_array($r)) {
			$blocks_per_hour = 3600/$featured_game['seconds_per_block'];
			$round_reward = ($featured_game['pos_reward']+$featured_game['pow_reward']*$featured_game['round_length'])/pow(10,8);
			$rounds_per_hour = 3600/($featured_game['seconds_per_block']*$featured_game['round_length']);
			$coins_per_hour = $round_reward*$rounds_per_hour;
			$seconds_per_round = $featured_game['seconds_per_block']*$featured_game['round_length'];
			echo '<div class="col-md-6">';

			echo '<h3>'.$featured_game['name'].'</h3>';
			if ($featured_game['giveaway_status'] == "invite_pay" || $featured_game['giveaway_status'] == "public_pay") {
				echo "You can join this game by paying ".number_format($featured_game['invite_cost'])." ".$featured_game['currency_short_name']."s for ".format_bignum($featured_game['giveaway_amount']/pow(10,8))." coins. ";
			}
			else echo "You can join this game and receive ".format_bignum($featured_game['giveaway_amount']/pow(10,8))." coins for free. ";

			if ($featured_game['final_round'] > 0) {
				$game_total_seconds = $seconds_per_round*$featured_game['final_round'];
				echo "This game will last ".$featured_game['final_round']." rounds (".format_seconds($game_total_seconds)."). ";
			}
			else echo "This game doesn't end, but you can sell your coins at any time. ";

			echo 'This coin has '.$featured_game['inflation'].' inflation';
			if ($featured_game['inflation'] == "linear") echo format_bignum($round_reward)."; coins are created per round with ".format_bignum($featured_game['pos_reward']/pow(10,8))." coins given to voters, and ".format_bignum($featured_game['pow_reward']*$featured_game['round_length']/pow(10,8))." coins given to miners. ";
			else echo " of ".(100*$featured_game['exponential_inflation_rate'])."% per round  with ".(100 - 100*$featured_game['exponential_inflation_minershare'])."% given to voters and ".(100*$featured_game['exponential_inflation_minershare'])."% given to miners. ";

			echo "Each round consists of ".$featured_game['round_length'].", ".rtrim(format_seconds($featured_game['seconds_per_block']), 's')." blocks and coins are ";
			if ($featured_game['maturity'] > 0) {
				echo "locked for ";
				echo $featured_game['maturity']." block";
				if ($featured_game['maturity'] != 1) echo "s";
				echo " after being spent.";
			}
			else echo "immediately available after being confirmed in a transaction.";
			echo "<br/>\n";
			echo '<a class="btn btn-primary" style="margin-top: 5px;" href="/'.$featured_game['url_identifier'].'">Join '.$featured_game['name'].'</a>';

			echo '</div>';
			if ($counter != 0 && $counter%2 == 0) echo '</div><div class="row">';
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
		EmpireCoin's unique gameplay encourages stakeholders to cooperate and vote together against competing factions.  Groups can create voting pools by coding up an API endpoint which incorporates their custom voting logic.  Or you can join an existing voting pool by entering a voting pool's URL which will control your voting decisions.
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