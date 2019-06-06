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
	$user_game = $app->fetch_user_game($thisuser->db_user['user_id'], $game->db_game['game_id']);
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
				<p>
					<?php
					if ($game->db_game['short_description'] != "") {
						echo str_replace("<br/>", " ", $game->db_game['short_description'])." ";
					}
					
					if ($game->db_game['game_status'] == "running") {
						echo "This game started ".date("M j, Y g:ia", strtotime($game->db_game['start_datetime'])).". ";
					}
					else if (strtotime($game->db_game['start_datetime']) > 0) {
						echo "This game starts in ".$app->format_seconds(strtotime($game->db_game['start_datetime'])-time())." on ".date("M d Y", strtotime($game->db_game['start_datetime'])).". ";
					}
					?>
				</p>
				<p>
					<a href="/wallet/<?php echo $game->db_game['url_identifier']; ?>/" class="btn btn-sm btn-success"><i class="fas fa-play-circle"></i> &nbsp; 
					<?php
					$ref_user_game = false;
					$faucet_io = $game->check_faucet($ref_user_game);
					
					if ($faucet_io) echo 'Join now & receive '.$app->format_bignum($faucet_io['colored_amount_sum']/pow(10,$game->db_game['decimal_places'])).' '.$game->db_game['coin_name_plural'];
					else echo 'Play Now';
					?>
					</a>
					<a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/events/" class="btn btn-sm btn-primary"><i class="fas fa-database"></i> &nbsp; Blockchain Explorer</a>
					<?php if ($app->user_can_edit_game($thisuser, $game)) { ?>
					<a class="btn btn-sm btn-warning" href="/manage/<?php echo $game->db_game['url_identifier']; ?>/"><i class="fa fa-edit"></i> Manage this Game</a>
					<?php } ?>
				</p>
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
	
	<div style="overflow: auto; margin-bottom: 10px;">
		<div style="float: right;">
			<?php
			echo $game->event_filter_html();
			?>
		</div>
	</div>
	
	<div id="game0_events" class="game_events game_events_short"></div>

	<div class="paragraph text-center">
		<?php echo $GLOBALS['site_name'].", ".date("Y"); ?>
	</div>

	<div class="paragraph">
		<?php
		if ($thisuser) {
			$account_value = $game->account_balance($user_game['account_id']);
			$immature_balance = $thisuser->immature_balance($game, $user_game);
			$mature_balance = $thisuser->mature_balance($game, $user_game);
		}
		else $mature_balance = 0;
		?>
	</div>
</div>
<?php
$filter_arr = false;
$event_ids = "";
$new_event_js = $game->new_event_js(0, $thisuser, $filter_arr, $event_ids);
?>
<script type="text/javascript">
//<![CDATA[
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
	echo ', "game", "'.$event_ids.'"';
	echo ', "'.$game->logo_image_url().'"';
	echo ', "'.$game->vote_effectiveness_function().'"';
	echo ', "'.$game->effectiveness_param1().'"';
	echo ', "'.$game->blockchain->db_blockchain['seconds_per_block'].'"';
	echo ', "'.$game->db_game['inflation'].'"';
	echo ', "'.$game->db_game['exponential_inflation_rate'].'"';
	echo ', "'.$blockchain_last_block['time_mined'].'"';
	echo ', "'.$game->db_game['decimal_places'].'"';
	echo ', "'.$game->blockchain->db_blockchain['decimal_places'].'"';
	echo ', "'.$game->db_game['view_mode'].'"';
	echo ', ';
	if ($user_game) echo $user_game['event_index'];
	else echo "0";
	echo ', false';
	echo ', "'.$game->db_game['default_betting_mode'].'"';
	echo ', true';
?>));

<?php
echo $new_event_js;
?>
//]]>
</script>
<?php
include('includes/html_stop.php');
?>
