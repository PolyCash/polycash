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
				Welcome to EmpireCoin, a unique gaming platform where anyone can create a currency backed by dollars or Bitcoins.  To set up an EmpireCoin game, everyone contributes money to the pot in exchange for an initial share of the coins.  Once the game starts, coins are given out at regular intervals following linear or exponential inflation.  Players compete to win a share of the new coins by casting votes for any of 16 empires.  A winning empire is determined in each round and the newly created coins are split up and given out to everyone who voted correctly.  Some EmpireCoin games last forever and you can buy in or sell out at any time.  Other games end at a certain time and then the pot is split up and given out to players in proportion to their final balances.  EmpireCoin strategy is all about collaborating with your teammates and betraying your enemies to get ahead. Start building your empire today in this massively multiplayer online game of chance.
			</div>
		</div>
	</div>
	
	<div class="paragraph">
		<?php
		$player_variation_q = "SELECT COUNT(*), t.start_condition_players FROM game_types t JOIN game_type_variations tv ON t.game_type_id=tv.game_type_id JOIN games g ON tv.variation_id=g.variation_id WHERE g.game_status='published' GROUP BY t.start_condition_players ORDER BY t.start_condition_players ASC;";
		$player_variation_r = run_query($player_variation_q);
		
		while ($player_variation = mysql_fetch_array($player_variation_r)) {
			$game_q = "SELECT *, g.url_identifier AS url_identifier, c.symbol AS symbol FROM game_types t JOIN game_type_variations tv ON t.game_type_id=tv.game_type_id JOIN games g ON tv.variation_id=g.variation_id LEFT JOIN currencies c ON g.invite_currency=c.currency_id WHERE g.game_status='published' AND g.giveaway_status IN ('public_free','public_pay') AND t.start_condition_players='".$player_variation['start_condition_players']."' ORDER BY g.invite_cost ASC;";
			$game_r = run_query($game_q);
			
			echo '<h2>Join a '.$player_variation['start_condition_players'].' player game</h2>';
			echo '<div class="bordered_table">';
			while ($variation_game = mysql_fetch_array($game_r)) {
				echo '<div class="row bordered_row">';
				
				echo '<div class="col-sm-3"><a title="'.game_description($variation_game).'" href="/'.$variation_game['url_identifier'].'/">'.ucfirst($variation_game['variation_name'])."</a></div>";
				
				$invite_disp = format_bignum($variation_game['invite_cost']);
				echo '<div class="col-sm-4">';
				
				if ($variation_game['giveaway_status'] == 'public_free') {
					$receive_disp = format_bignum($variation_game['giveaway_amount']/pow(10,8));
					echo 'Start with '.$receive_disp.' free ';
					if ($receive_disp == '1') echo $variation_game['coin_name'];
					else echo $variation_game['coin_name_plural'];
				}
				else {
					echo 'Buy in at '.$variation_game['symbol'].$invite_disp." ".$variation_game['short_name'];
					if ($invite_disp != '1') echo 's';
					echo " for ";
					$receive_disp = format_bignum($variation_game['giveaway_amount']/pow(10,8));
					echo $receive_disp.' ';
					if ($receive_disp == '1') echo $variation_game['coin_name'];
					else echo $variation_game['coin_name_plural'];
				}
				echo "</div>";
				
				$players = paid_players_in_game($variation_game);
				echo '<div class="col-sm-2">'.$players."/".$variation_game['start_condition_players']." players</div>";
				
				echo '<div class="col-sm-3">';
				if ($variation_game['final_round'] > 0) {
					$final_inflation_pct = game_final_inflation_pct($variation_game);
					$game_seconds = $variation_game['final_round']*$variation_game['round_length']*$variation_game['seconds_per_block'];
					echo number_format($final_inflation_pct)."% inflation in ".format_seconds($game_seconds);
				}
				echo '</div>';
				
				echo "</div>\n";
			}
			echo "</div>\n";
		}
		
		/*$q = "SELECT g.*, c.short_name AS currency_short_name FROM games g LEFT JOIN currencies c ON g.invite_currency=c.currency_id WHERE g.featured=1 AND (g.game_status='editable' OR g.game_status='running');";
		$r = run_query($q);
		echo '<div class="row">';
		$counter = 0;
		while ($featured_game = mysql_fetch_array($r)) {
			echo '<div class="col-md-6">';
			echo game_description($featured_game);
			echo '</div>';
			
			if ($counter%2 == 1) echo '</div><div class="row">';
			$counter++;
		}
		echo '</div>';*/
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