<?php
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if (!isset($_REQUEST['action'])) $_REQUEST['action'] = "";

$pagetitle = $game->db_game['name'];
$nav_tab_selected = "game_page";
include('includes/html_start.php');

$last_block_id = $game->blockchain->last_block_id();
$blockchain_last_block = $game->blockchain->fetch_block_by_id($last_block_id);
$current_round = $game->block_to_round($last_block_id+1);

$user_game = false;
if ($game && $thisuser) {
	$q = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
	$r = $app->run_query($q);
	if ($r->rowCount() == 1) {
		$user_game = $r->fetch();
	}
}

if ($game->db_game['invite_currency'] > 0) {
	$invite_currency = $app->run_query("SELECT * FROM currencies WHERE currency_id='".$game->db_game['invite_currency']."';")->fetch();
	
	$btc_currency = $app->get_currency_by_abbreviation('btc');
	
	$coins_in_existence = $game->coins_in_existence(false);
	$escrow_value = $game->escrow_value(false);
	if ($escrow_value > 0) {
		$exchange_rate = $coins_in_existence/$escrow_value;
	}
	else $exchange_rate = 0;
}
else $exchange_rate = 0;
?>
<div class="container-fluid">
	<div class="paragraph">
		<h1 style="margin-bottom: 2px;"><?php echo $game->db_game['name']; ?></h1>
		<div class="row">
			<div class="col-md-6">
				<?php
				$blocks_per_hour = 3600/$game->blockchain->db_blockchain['seconds_per_block'];
				$seconds_per_round = $game->blockchain->db_blockchain['seconds_per_block']*$game->db_game['round_length'];
				$round_reward = ($app->coins_created_in_round($game->db_game, $current_round))/pow(10,$game->db_game['decimal_places']);

				if ($game->db_game['inflation'] == "linear") {
					$miner_pct = 100*($game->db_game['pow_reward']*$game->db_game['round_length'])/($round_reward*pow(10,$game->db_game['decimal_places']));
				}
				else $miner_pct = 100*$game->db_game['exponential_inflation_minershare'];
				?>
				<div class="paragraph">
					<?php
					if ($game->db_game['final_round'] > 0) echo "This game runs for ".$game->db_game['final_round']." rounds (approximately ".$app->format_seconds($seconds_per_round*$game->db_game['final_round'])."). ";
					
					if ($game->db_game['inflation'] == "linear") {
						$rounds_per_hour = 3600/($game->blockchain->db_blockchain['seconds_per_block']*$game->db_game['round_length']);
						$coins_per_hour = $round_reward*$rounds_per_hour;
						echo "Rounds take about ".$app->format_seconds($seconds_per_round)." and ";
						echo $app->format_bignum($round_reward)." ".$game->db_game['coin_name_plural']." are created and given out at the end of each round. ";
					}
					else {
						echo "Rounds take about ".$app->format_seconds($seconds_per_round)." each";
						if ($game->db_game['inflation'] == "exponential") echo ". Votes can be traded for ".$game->db_game['coin_name_plural']." at a rate of ".$app->votes_per_coin($game->db_game)." votes per ".$game->db_game['coin_name'].", giving this game an inflation rate of approximately ".(100*$game->db_game['exponential_inflation_rate'])."% per round. ";
						else ", and the supply of coins increases by ".(100*$game->db_game['exponential_inflation_rate'])."% after each round. ";
					}
					
					if ($game->db_game['start_condition'] == "players_joined") {
						$num_players = $game->paid_players_in_game();
						if ($game->db_game['game_status'] == "running") {
							echo "This game started ".$app->format_seconds(time()-strtotime($game->db_game['start_datetime']))." ago with ".$game->db_game['start_condition_players']." players. ";
						}
						else {
							echo "This game starts when ".$game->db_game['start_condition_players']." players have joined. So far ".$num_players;
							if ($num_players == 1) echo " has ";
							else echo " have ";
							echo "joined.<br/>\n";
						}
					}
					else {
						if ($game->db_game['game_status'] == "running") {
							echo "This game started at ".date("M j, Y g:ia", strtotime($game->db_game['start_datetime'])).". ";
						}
						else if (strtotime($game->db_game['start_datetime']) > 0) {
							echo "This game starts in ".$app->format_seconds(strtotime($game->db_game['start_datetime'])-time())." on ".date("M d Y", strtotime($game->db_game['start_datetime'])).". ";
						}
					}
					
					if ($game->db_game['short_description'] != "") {
						echo '</div><div class="paragraph">'.$game->db_game['short_description']." ";
					}
					
					if ($exchange_rate > 0 && $game->db_game['buyin_policy'] != "none") {
						if ($game->escrow_value(false) > 0) {
							$escrow_amount_disp = $app->format_bignum($game->escrow_value(false)/pow(10,$game->db_game['decimal_places']));
							echo "Right now there's ".$escrow_amount_disp." ";
							if ($escrow_amount_disp == "1") echo $game->blockchain->db_blockchain['coin_name'];
							else echo $game->blockchain->db_blockchain['coin_name_plural'];
							echo " in escrow and the";
						}
						else echo "The";
						echo " exchange rate is ".$app->format_bignum($exchange_rate)." ".$game->db_game['coin_name_plural']." per ".$invite_currency['short_name'].". ";
					}
					?>
				</div>
				<?php
				if ($game->db_game['giveaway_status'] == "public_pay" || $game->db_game['giveaway_status'] == "invite_pay") {
					$q = "SELECT * FROM currencies WHERE currency_id='".$game->db_game['invite_currency']."';";
					$r = $app->run_query($q);
					if ($r->rowCount() > 0) {
						$invite_currency = $r->fetch();
						echo '<div class="paragraph">';
						
						$receive_disp = $app->format_bignum($game->db_game['giveaway_amount']/pow(10,$game->db_game['decimal_places']));
						if ($game->db_game['giveaway_status'] == "invite_pay" || $game->db_game['giveaway_status'] == "invite_free") echo "You need an invitation to join this game. After receiving an invitation you can join";
						else echo 'You can join this game';
						echo ' by buying '.$receive_disp.' ';
						if ($receive_disp == '1') echo $game->db_game['coin_name'];
						else echo $game->db_game['coin_name_plural'];
						
						$buyin_disp = $app->format_bignum($game->db_game['invite_cost']);
						echo ' for '.$buyin_disp.' ';
						echo $invite_currency['short_name'];
						if ($buyin_disp != '1') echo "s";
						echo ". ";

						/*if ($game->db_game['game_status'] == "running") {
							echo "This game started ".$app->format_seconds(time()-$game->db_game['start_time'])." ago; ".$app->format_bignum($game->coins_in_existence(false)/pow(10,$game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']."  are already in circulation. ";
						}
						else {
							if ($game->db_game['start_condition'] == "fixed_time") {
								$unix_starttime = strtotime($game->db_game['start_datetime']);
								echo "This game starts in ".$app->format_seconds($unix_starttime-time())." at ".date("M j, Y g:ia", $unix_starttime).". ";
							}
							else {
								$current_players = $game->paid_players_in_game();
								echo "This game will start when ".$game->db_game['start_condition_players']." player";
								if ($game->db_game['start_condition_players'] == 1) echo " joins";
								else echo "s have joined";
								echo ". ".($game->db_game['start_condition_players']-$current_players)." player";
								if ($game->db_game['start_condition_players']-$current_players == 1) echo " is";
								else echo "s are";
								echo " needed, ".$current_players;
								if ($current_players == 1) echo " has";
								else echo " have";
								echo " already joined. ";
							}
						}*/
						echo '</div>';
					}
				}
				/* ?>
				<div class="paragraph">
					In this game you can win <?php echo $game->db_game['coin_name_plural']; ?> by casting your votes correctly.  Votes build up over time based on the number of <?php echo $game->db_game['coin_name_plural']; ?> that you hold.  When you vote for <?php echo $app->prepend_a_or_an($game->db_game['option_name']); ?>, your votes are used up but your <?php echo $game->db_game['coin_name_plural']; ?> are retained. By collaborating with your teammates against competing groups, you can make money by accumulating <?php echo $game->db_game['coin_name_plural']; ?> faster than the other players.  Through the <?php echo $GLOBALS['coin_brand_name']; ?> API you can code up a custom strategy which makes smart, real-time decisions about how to cast your votes.
				</div>*/ ?>
				<div class="paragraph">
					<a href="/wallet/<?php echo $game->db_game['url_identifier']; ?>/" class="btn btn-success">
					<?php
					$faucet_io = $game->check_faucet(false);
					
					if ($faucet_io) echo 'Join now & receive '.$app->format_bignum($faucet_io['colored_amount_sum']/pow(10,$game->db_game['decimal_places'])).' '.$game->db_game['coin_name_plural'];
					else echo 'Play Now';
					?>
					</a>
					<a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/events/" class="btn btn-primary">Blockchain Explorer</a>
				</div>
			</div>
			<div class="col-md-6">
				<div style="border: 1px solid #ccc; padding: 10px; background-color: #fff;">
					<?php
					$game->db_game['seconds_per_block'] = $game->blockchain->db_blockchain['seconds_per_block'];
					echo $app->game_info_table($game->db_game);
					?>
				</div>
			</div>
		</div>
	</div>
	
	<div id="game0_events"></div>

	<div class="paragraph text-center">
		<?php echo $GLOBALS['site_name'].", ".date("Y"); ?>
	</div>

	<div class="paragraph">
		<?php
		if ($thisuser) {
			$account_value = $thisuser->account_coin_value($game, $user_game);
			$immature_balance = $thisuser->immature_balance($game, $user_game);
			$mature_balance = $thisuser->mature_balance($game, $user_game);
		}
		else $mature_balance = 0;
		?>
		<div style="display: none;" id="game0_vote_details_general">
			<?php echo $app->vote_details_general($mature_balance); ?>
		</div>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
var games = new Array();
games.push(new Game(<?php
	echo $game->db_game['game_id'];
	echo ', false';
	echo ', false';
	echo ', "", "'.$game->db_game['payout_weight'].'"';
	echo ', '.$game->db_game['round_length'];
	echo ', 0';
	echo ', "'.$game->db_game['url_identifier'].'"';
	echo ', "'.$game->db_game['coin_name'].'"';
	echo ', "'.$game->db_game['coin_name_plural'].'"';
	echo ', "'.$game->blockchain->db_blockchain['coin_name'].'"';
	echo ', "'.$game->blockchain->db_blockchain['coin_name_plural'].'"';
	echo ', "game", "'.$game->event_ids().'"';
	echo ', "'.$game->logo_image_url().'"';
	echo ', "'.$game->vote_effectiveness_function().'"';
	echo ', "'.$game->effectiveness_param1().'"';
	echo ', "'.$game->blockchain->db_blockchain['seconds_per_block'].'"';
	echo ', "'.$game->db_game['inflation'].'"';
	echo ', "'.$game->db_game['exponential_inflation_rate'].'"';
	echo ', "'.$blockchain_last_block['time_mined'].'"';
	echo ', "'.$game->db_game['decimal_places'].'"';
?>));

games[0].game_loop_event();

<?php
echo $game->new_event_js(0, false);
?>
var user_logged_in = <?php if (empty($thisuser)) echo 'false'; else echo 'true'; ?>;

var homeCarousel;

$(document).ready(function() {
	homeCarousel = new ImageCarousel('home_carousel');
	homeCarousel.initialize();
});

$(".navbar-toggle").click(function(event) {
	$(".navbar-collapse").toggle('in');
});
//]]>
</script>
<?php
include('includes/html_stop.php');
?>
