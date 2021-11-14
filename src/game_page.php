<?php
if (!isset($_REQUEST['action'])) $_REQUEST['action'] = "";

$pagetitle = $game->db_game['name'];
$nav_tab_selected = "game_page";
include(AppSettings::srcPath().'/includes/html_start.php');

$last_block_id = $game->blockchain->last_block_id();
$blockchain_last_block = $game->blockchain->fetch_block_by_id($last_block_id);
$current_round = $game->block_to_round($last_block_id+1);

$user_game = false;
if ($game && $thisuser) {
	$user_game = $app->fetch_user_game($thisuser->db_user['user_id'], $game->db_game['game_id']);
}

if ($game->db_game['invite_currency'] > 0) {
	$invite_currency = $app->fetch_currency_by_id($game->db_game['invite_currency']);
	
	$btc_currency = $app->get_currency_by_abbreviation('btc');
	
	$coins_in_existence = $game->coins_in_existence(false, true);
	$escrow_value = $game->escrow_value(false);
	if ($escrow_value > 0) {
		$exchange_rate = $coins_in_existence/$escrow_value;
	}
	else $exchange_rate = 0;
}
else $exchange_rate = 0;
?>
<div class="container-fluid" style="padding-top: 15px;">
	<?php
	$top_nav_show_search = true;
	$explorer_type = "games";
	$explore_mode = "about";
	include('includes/explorer_top_nav.php');
	?>
	<h2 style="margin-top: 0px;"><?php echo $game->db_game['name']; ?></h2>
	<div class="row">
		<div class="col-md-6">
			<p>
				<?php
				if ($game->db_game['short_description'] != "") {
					echo str_replace("<br/>", " ", $game->db_game['short_description'])." ";
				}
				?>
			</p>
			<p>
				<a href="/wallet/<?php echo $game->db_game['url_identifier']; ?>/" class="btn btn-sm btn-success"><i class="fas fa-play-circle"></i> &nbsp; 
				<?php
				$ref_user_game = false;
				$faucet_io = $game->check_faucet($ref_user_game);
				
				if ($faucet_io) {
					echo 'Join now & receive '.$game->display_coins($faucet_io['colored_amount_sum']);
				}
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
	
	<div style="overflow: auto; margin-bottom: 10px;">
		<div style="float: right;">
			<?php
			echo $game->event_filter_html();
			?>
		</div>
	</div>
	<?php
	$filter_arr = false;
	$event_ids = "";
	list($new_event_js, $new_event_html) = $game->new_event_js(0, $thisuser, $filter_arr, $event_ids, true);
	?>
	<div id="game0_events" class="game_events game_events_short"><?php echo $new_event_html; ?></div>

	<div class="paragraph text-center">
		<?php echo AppSettings::getParam('site_name').", ".date("Y"); ?>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
games.push(new Game(thisPageManager, <?php
	echo $game->db_game['game_id'];
	echo ', '.$game->last_block_id();
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
	echo ', false';
?>));

<?php
echo $new_event_js;
?>
//]]>
</script>
<?php
include(AppSettings::srcPath().'/includes/html_stop.php');
?>
