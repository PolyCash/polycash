<?php
include("includes/connect.php");
include("includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $GLOBALS['pageview_controller']->insert_pageview($thisuser);

$pagetitle = $GLOBALS['coin_brand_name']." - Vote for your empire in the first decentralized blockchain voting game.";
$nav_tab_selected = "home";
include('includes/html_start.php');
?>
<div class="container-fluid nopadding">
	<div class="top_banner" id="home_carousel">
		<div class="carouselText"><h1><?php echo $GLOBALS['coin_brand_name']; ?></h1></div>
	</div>
</div>
<div class="container" style="max-width: 1000px; padding-top: 10px;">
	<div class="row">
		<div class="col-sm-2 text-center">
			<img alt="<?php echo $GLOBALS['coin_brand_name']; ?> Logo" id="home_logo" src="/img/logo/icon-150x150.png" />
		</div>
		<div class="col-sm-10">
			<div class="paragraph">
				Welcome to <?php echo $GLOBALS['coin_brand_name']; ?>, an innovative blockchain-based gaming platform.  In <?php echo $GLOBALS['coin_brand_name']; ?> games the in-game currency inflates rapidly and players compete to win coins by casting votes.  One empire wins in each round and the reward is split among everyone who voted correctly.  <?php echo $GLOBALS['coin_brand_name']; ?> supports a wide variety of game types.  Battle against a single opponent in a quick two player game, set up a daily fantasy sports game with your friends or join a massive battle with thousands of other players.  Free games are available so that you can try <?php echo $GLOBALS['coin_brand_name']; ?> without any risk, but most games are played with real money.  You can buy in with bitcoins or dollars and then sell out at any time. <?php echo $GLOBALS['coin_brand_name']; ?> strategy is all about collaborating with your teammates and scheming against your enemies to get ahead. Start building your empire today in this massively multiplayer online game of chance.
			</div>
		</div>
	</div>
	<?php
	$app->display_featured_games();
	?>
	<div class="paragraph">
		<?php
		$player_variation_q = "SELECT COUNT(*), t.start_condition_players FROM game_types t JOIN game_type_variations tv ON t.game_type_id=tv.game_type_id JOIN games g ON tv.variation_id=g.variation_id WHERE g.game_status='published' GROUP BY t.start_condition_players ORDER BY t.start_condition_players ASC;";
		$player_variation_r = $app->run_query($player_variation_q);
		
		while ($player_variation = $player_variation_r->fetch()) {
			$game_q = "SELECT *, tv.url_identifier AS url_identifier, c.symbol AS symbol, c.short_name AS currency_short_name FROM game_types t JOIN game_type_variations tv ON t.game_type_id=tv.game_type_id JOIN games g ON tv.variation_id=g.variation_id LEFT JOIN currencies c ON g.invite_currency=c.currency_id WHERE g.game_status='published' AND g.giveaway_status IN ('public_free','public_pay') AND t.start_condition_players='".$player_variation['start_condition_players']."' ORDER BY g.invite_cost ASC;";
			$game_r = $app->run_query($game_q);
			
			echo '<h2>Join a '.$player_variation['start_condition_players'].' player game</h2>';
			echo '<div class="bordered_table">';
			while ($db_game = $game_r->fetch()) {
				$variation_game = new Game($app, $db_game['game_id']);
				echo '<div class="row bordered_row">';
				
				echo '<div class="col-sm-3"><a title="'.$variation_game->game_description().'" href="/'.$db_game['url_identifier'].'/">'.ucfirst($variation_game->db_game['type_name'])."</a></div>";
				
				$invite_disp = $app->format_bignum($variation_game->db_game['invite_cost']);
				echo '<div class="col-sm-4">';
				
				if ($variation_game->db_game['giveaway_status'] == 'public_free') {
					$receive_disp = $app->format_bignum($variation_game->db_game['giveaway_amount']/pow(10,8));
					echo 'Start with '.$receive_disp.' ';
					if ($receive_disp == '1') echo $variation_game->db_game['coin_name'];
					else echo $variation_game->db_game['coin_name_plural'];
					echo ' for free';
				}
				else {
					echo 'Buy in at '.$db_game['symbol'].$invite_disp." ".$db_game['short_name'];
					if ($invite_disp != '1') echo 's';
					echo " for ";
					$receive_disp = $app->format_bignum($variation_game->db_game['giveaway_amount']/pow(10,8));
					echo $receive_disp.' ';
					if ($receive_disp == '1') echo $variation_game->db_game['coin_name'];
					else echo $variation_game->db_game['coin_name_plural'];
				}
				echo "</div>";
				
				$players = $variation_game->paid_players_in_game();
				echo '<div class="col-sm-2">'.$players."/".$variation_game->db_game['start_condition_players']." players</div>";
				
				echo '<div class="col-sm-3">';
				if ($variation_game->db_game['final_round'] > 0) {
					$final_inflation_pct = game_final_inflation_pct($variation_game->db_game);
					$game_seconds = $variation_game->db_game['final_round']*$variation_game->db_game['round_length']*$variation_game->db_game['seconds_per_block'];
					echo number_format($final_inflation_pct)."% inflation in ".$app->format_seconds($game_seconds);
				}
				echo '</div>';
				
				echo "</div>\n";
			}
			echo "</div>\n";
		}
		?>
	</div>

	<div class="paragraph">
		<h2>Strategy &amp; Gameplay</h2>
		The winning empire for an <?php echo $GLOBALS['coin_brand_name']; ?> voting round is determined entirely by the votes of the players.  Therefore, winning in <?php echo $GLOBALS['coin_brand_name']; ?> is all about colluding with other players and organizing against competing factions.  Players can form voting pools and vote together to influence the winning empire.
	</div>
	<div class="paragraph">
		As with other cryptocurrencies like Bitcoin, miners have veto authority over any transactions included in their block.  Because miners have some influence on the winning empire it is possible that your votes may not be counted.
	</div>
	<div class="paragraph">
		<h2>Voting Pools</h2>
		<?php echo $GLOBALS['coin_brand_name']; ?>'s unique gameplay encourages stakeholders to cooperate and vote together against the other teams.  Groups can create voting pools by coding up an API endpoint which incorporates their custom voting logic.  You can assign your voting decisions to a voting pool by entering it's URL into your web wallet.
	</div>
	<div class="paragraph">
		<h2><?php echo $GLOBALS['coin_brand_name']; ?> API</h2>
		Automated &amp; algorithmic voting strategies are encouraged in <?php echo $GLOBALS['coin_brand_name']; ?>.  After signing up for a web wallet, you can choose from one of several automated voting strategies and then tweak parameters to optimize your votes.  Or you can choose the "Vote by API" voting strategy and then write code to fully customize your voting strategy.  For more information, please visit the <a href="/api/about/"><?php echo $GLOBALS['coin_brand_name']; ?> API page</a>.
	</div>
	<?php /*<div class="paragraph">
		<h2>Proof of Burn Betting</h2>
		In addition to <?php echo $GLOBALS['coin_brand_name']; ?>'s gamified inflation, the <?php echo $GLOBALS['coin_brand_name']; ?> protocol also enables decentralized betting through a unique proof-of-burn protocol.  By sending coins to an address like "china_wins_round_777", anyone can place a bet against other bettors.  If correct, new coins will be created and sent to the winner.
	</div>*/ ?>

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