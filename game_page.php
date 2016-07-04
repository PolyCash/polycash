<?php
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $GLOBALS['pageview_controller']->insert_pageview($thisuser);

$pagetitle = $game->db_game['name']." - Play now and win coins in this blockchain voting game.";
$nav_tab_selected = "game_homepage";
include('includes/html_start.php');

$last_block_id = $game->last_block_id();
$current_round = $game->block_to_round($last_block_id+1);

$user_game = false;
if ($game) {
	$q = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
	$r = $app->run_query($q);
	if ($r->rowCount() == 1) {
		$user_game = $r->fetch();
	}
}
?>
<div class="container" style="max-width: 1000px; padding-top: 10px;">
	<div class="paragraph">
		<a href="/">&larr; All <?php echo $GLOBALS['coin_brand_name']; ?> Games</a>
	</div>
	<div class="paragraph">
		<h1 style="margin-bottom: 2px;"><?php echo $game->db_game['name']; ?></h1>
		<div class="row">
			<div class="col-md-6">
				<h4><?php echo $game->db_game['variation_name']; ?></h4>
				<?php
				$blocks_per_hour = 3600/$game->db_game['seconds_per_block'];
				$seconds_per_round = $game->db_game['seconds_per_block']*$game->db_game['round_length'];
				$round_reward = (coins_created_in_round($game->db_game, $current_round))/pow(10,8);

				if ($game->db_game['inflation'] == "linear") {
					$miner_pct = 100*($game->db_game['pow_reward']*$game->db_game['round_length'])/($round_reward*pow(10,8));
				}
				else $miner_pct = 100*$game->db_game['exponential_inflation_minershare'];
				?>
				<div class="paragraph">
					<?php
					if ($game->db_game['inflation'] == "linear") {
						$rounds_per_hour = 3600/($game->db_game['seconds_per_block']*$game->db_game['round_length']);
						$coins_per_hour = $round_reward*$rounds_per_hour;
						echo "In this game, ";
						echo number_format($coins_per_hour)." ".$game->db_game['coin_name_plural']." are generated every hour. ";
						echo $app->format_bignum($round_reward)." ".$game->db_game['coin_name_plural']." are given out per ".rtrim($app->format_seconds($seconds_per_round), 's')." voting round. ";
					}
					else {
						echo "In this game, ".$game->db_game['coin_name_plural']." experience an inflation of ".(100*$game->db_game['exponential_inflation_rate'])."% every ".$app->format_seconds($seconds_per_round).". ";
					}
					
					echo $app->format_bignum($miner_pct);
					?>% of the currency is given to proof of work miners for securing the network and the remaining <?php
					echo $app->format_bignum(100-$miner_pct);
					?>% is given out to the players for winning votes.<?php
					?>
				</div>
				<?php
				if ($game->db_game['giveaway_status'] == "public_pay" || $game->db_game['giveaway_status'] == "invite_pay") {
					$q = "SELECT * FROM currencies WHERE currency_id='".$game->db_game['invite_currency']."';";
					$r = $app->run_query($q);
					if ($r->rowCount() > 0) {
						$invite_currency = $r->fetch();
						echo '<div class="paragraph">';
						
						$receive_disp = $app->format_bignum($game->db_game['giveaway_amount']/pow(10,8));
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

						if ($game->db_game['game_status'] == "running") {
							echo "This game started ".$app->format_seconds(time()-$game->db_game['start_time'])." ago; ".$app->format_bignum(coins_in_existence($app, $game, false)/pow(10,8))." ".$game->db_game['coin_name_plural']."  are already in circulation. ";
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
						}
						echo '</div>';
					}
				}
				?>
				<div class="paragraph">
					In this game you can win <?php echo $game->db_game['coin_name_plural']; ?> by casting your votes correctly.  Votes build up over time based on the number of <?php echo $game->db_game['coin_name_plural']; ?> that you hold.  When you vote for <?php echo $app->prepend_a_or_an($game->db_game['option_name']); ?>, your votes are used up but your <?php echo $game->db_game['coin_name_plural']; ?> are retained. By collaborating with your teammates against competing groups, you can make money by accumulating <?php echo $game->db_game['coin_name_plural']; ?> faster than the other players.  Through the <?php echo $GLOBALS['coin_brand_name']; ?> API you can code up a custom strategy which makes smart, real-time decisions about how to cast your votes.
				</div>
				<div class="paragraph">
					<a href="/wallet/<?php echo $game->db_game['url_identifier']; ?>/" class="btn btn-success">Play Now</a>
					<a href="/explorer/<?php echo $game->db_game['url_identifier']; ?>/rounds/" class="btn btn-primary">Blockchain Explorer</a>
				</div>
			</div>
			<div class="col-md-6">
				<h4><?php echo $game->db_game['type_name']; ?></h4>
				<div style="border: 1px solid #ccc; padding: 10px;">
					<?php echo game_info_table($app, $game->db_game); ?>
				</div>
			</div>
		</div>

		<div class="paragraph">
			<a href="" onclick="$('#escrow_details').modal('show'); return false;">See escrowed contributions</a>
		</div>
		<?php
		if ($_REQUEST['action'] == "show_escrow") { ?>
			<script type="text/javascript">
			$(document).ready(function() {
				$('#escrow_details').modal('show');
			});
			</script>
			<?php
		}
		?>
		<div style="display: none;" class="modal fade" id="escrow_details">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title">Escrowed funds: &nbsp; <?php echo $game->db_game['name']; ?></h4>
					</div>
					<div class="modal-body">
						<?php
						if ($user_game) {
							$escrow_html = "";
							$btc_sum = 0;
							$settle_sum = 0;
							
							$q = "SELECT * FROM currency_invoices ci JOIN invoice_addresses ia ON ci.invoice_address_id=ia.invoice_address_id JOIN currencies c ON ci.settle_currency_id=c.currency_id WHERE ci.game_id='".$game->db_game['game_id']."' AND ci.status='confirmed';";
							$r = $app->run_query($q);
							
							while ($currency_invoice = $r->fetch()) {
								$escrow_html .= '<div class="row">';
								$escrow_html .= '<div class="col-sm-3">'.$app->format_bignum($currency_invoice['settle_amount']).' '.$currency_invoice['short_name'].'s</div>';
								$escrow_html .= '<div class="col-sm-3">'.$app->format_bignum($currency_invoice['unconfirmed_amount_paid']).' BTC</div>';
								$escrow_html .= '<div class="col-sm-5"><a target="_blank" href="https://blockchain.info/address/'.$currency_invoice['pub_key'].'">'.$currency_invoice['pub_key'].'</a></div>';
								$escrow_html .= '</div>';
								$btc_sum += $currency_invoice['unconfirmed_amount_paid'];
								$settle_sum += $currency_invoice['settle_amount'];
							}
							
							$q = "SELECT * FROM game_buyins gb JOIN invoice_addresses ia ON gb.invoice_address_id=ia.invoice_address_id JOIN currencies c ON gb.settle_currency_id=c.currency_id WHERE gb.game_id='".$game->db_game['game_id']."' AND gb.status='confirmed';";
							$r = $app->run_query($q);
							
							while ($buyin = $r->fetch()) {
								$escrow_html .= '<div class="row">';
								$escrow_html .= '<div class="col-sm-3">'.$app->format_bignum($buyin['settle_amount']).' '.$buyin['short_name'].'s</div>';
								$escrow_html .= '<div class="col-sm-3">'.$app->format_bignum($buyin['unconfirmed_amount_paid']).' BTC</div>';
								$escrow_html .= '<div class="col-sm-5"><a target="_blank" href="https://blockchain.info/address/'.$buyin['pub_key'].'">'.$buyin['pub_key'].'</a></div>';
								$escrow_html .= '</div>';
								$btc_sum += $buyin['unconfirmed_amount_paid'];
								$settle_sum += $buyin['settle_amount'];
							}
							
							if ($escrow_html == "") {
								echo "No funds are currently in escrow for this game.";
							}
							else {
								echo $escrow_html;
								echo '<br/>In total, escrowed funds are worth '.$app->format_bignum($settle_sum)." ".$invite_currency['short_name']."s. ";
							}
						}
						else {
							echo "You need to join this game before you can see it's escrowed funds.";
						}
						?>
					</div>
				</div>
			</div>
		</div>
		
		<?php
		if ($thisuser) { ?>
			<div class="row">
				<div class="col-md-6">
					<div id="my_current_votes">
						<?php
						echo $game->my_votes_table($current_round, $thisuser);
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
			<li>Players can vote on these <?php echo $game->db_game['num_voting_options']." ".$game->db_game['option_name_plural']." every ".$app->format_seconds($seconds_per_round); ?> by submitting a voting transaction.</li>
			<?php
			$block_within_round = $last_block_id%$game->db_game['round_length']+1;
			$score_sums = $game->total_score_in_round($current_round, true);
			
			$round_stats = $game->round_voting_stats_all($current_round);
			$option_id2rank = $round_stats[3];
			?>
			<div id="current_round_table" style="margin-bottom: 10px;">
				<?php
				echo $game->current_round_table($current_round, $thisuser, false, true);
				?>
			</div>
			
			<li>Voting transactions are only counted if they are confirmed in a voting block. All blocks are voting blocks except for the final block of each round.</li>
			<li>Blocks are mined approximately every <?php echo $app->format_seconds($game->db_game['seconds_per_block']); ?> by the SHA256 algorithm. 
			<?php if ($game->db_game['inflation'] == "linear") { ?>Miners receive <?php echo $app->format_bignum($game->db_game['pow_reward']/pow(10,8))." ".$game->db_game['coin_name_plural']; ?> per block.<?php } ?></li>
			<li>Blocks are grouped into voting rounds.  Blocks 1 through <?php echo $game->db_game['round_length']; ?> make up the first round, and every subsequent <?php echo $game->db_game['round_length']; ?> blocks are grouped into a round.</li>
			<li>A voting round will have a winning <?php echo $game->db_game['option_name']; ?> if at least one <?php echo $game->db_game['option_name']; ?> receives votes but is not disqualified.</li>
			<li>Any <?php echo $game->db_game['option_name']; ?> with more than <?php echo $app->format_bignum(100*$game->db_game['max_voting_fraction']); ?>% of the votes is disqualified from winning the round.</li>
			<li>The eligible <?php echo $game->db_game['option_name']; ?> with the most votes wins the round.</li>
			<li>In case of a tie, the <?php echo $game->db_game['option_name']; ?> with the lowest ID number wins.</li>
			<li>When a round ends <?php
			if ($game->db_game['inflation'] == "linear") {
				echo $app->format_bignum($game->db_game['pos_reward']/pow(10,8))." ".$game->db_game['coin_name_plural']." are divided up";
			}
			else echo $app->format_bignum(100*$game->db_game['exponential_inflation_rate']).'% is added to the currency supply';
			?> and given to the winning voters in proportion to their votes.</li>
		</ol>
	</div>

	<div class="paragraph text-center">
		<?php echo $GLOBALS['site_name'].", ".date("Y"); ?>
	</div>

	<div class="paragraph">
		<div id="vote_popups"><?php
		echo $game->initialize_vote_option_details($option_id2rank, $score_sums['sum'], $thisuser->db_user['user_id']);
		?></div>
		
		<?php
		if ($thisuser) {
			$account_value = $thisuser->account_coin_value($game);
			$immature_balance = $thisuser->immature_balance($game);
			$mature_balance = $thisuser->mature_balance($game);
		}
		else $mature_balance = 0;
		?>
		<div style="display: none;" id="vote_details_general">
			<?php echo $app->vote_details_general($mature_balance); ?>
		</div>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
var last_block_id = <?php echo $last_block_id; ?>;
var last_transaction_id = <?php echo $game->last_transaction_id(); ?>;
var my_last_transaction_id = <?php echo $thisuser->my_last_transaction_id($game->db_game['game_id']); ?>;
var mature_io_ids_csv = '<?php echo $game->mature_io_ids_csv($thisuser->db_user['user_id']); ?>';
var game_round_length = <?php echo $game->db_game['round_length']; ?>;
var game_id = <?php echo $game->db_game['game_id']; ?>;
var game_loop_index = 1;
var num_voting_options = <?php echo $game->db_game['num_voting_options']; ?>;

var coin_name = '<?php echo $game->db_game['coin_name']; ?>';
var coin_name_plural = '<?php echo $game->db_game['coin_name_plural']; ?>';

var last_game_loop_index_applied = -1;
var min_bet_round = <?php
	$bet_round_range = $game->bet_round_range();
	echo $bet_round_range[0];
?>;
var option_has_votingaddr = [];
for (var i=1; i<=num_voting_options; i++) { option_has_votingaddr[i] = false; }
var votingaddr_count = 0;

var refresh_page = "game";
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