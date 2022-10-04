<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($game && $thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$game->load_current_events();
	$user_game = $thisuser->ensure_user_in_game($game, false);
	$user_strategy = $game->fetch_user_strategy($user_game);
	?>
	<form method="get" onsubmit="thisPageManager.save_featured_strategy(); return false;">
		<div class="modal-body">
			<p>
				You can select an automated betting strategy from the options below. 
				The percentages shown here reflect recent performances but are no guarantee of future performance.
			</p>
			<p>
				To write your own betting strategy, please see our <a href="/api/about">API documentation</a>.
			</p>
			<?php
			$previous_rounds = 3;
			$current_event = $game->current_events[0];
			
			$featured_strategies = $game->fetch_featured_strategies();
			
			while ($featured_strategy = $featured_strategies->fetch()) {
				$pct_increase = null;
				
				if ($featured_strategy['account_id'] > 0 && $featured_strategy['reference_starting_block'] <= $game->blockchain->last_block_id()) {
					$ref_block = $app->run_query("SELECT * FROM blocks WHERE time_mined < ".(time()-(3600*24*7))." ORDER BY block_id DESC LIMIT 1;")->fetch();
					
					if ($ref_block) {
						$start_bal_block = $game->blockchain->fetch_block_by_id(max($ref_block['block_id'], $featured_strategy['reference_starting_block']));
						
						$starting_balance = $game->account_balance_at_block($featured_strategy['account_id'], $start_bal_block['block_id'], true);
						$pending_bets_params = ['account_id' => $featured_strategy['account_id']];
						$final_balance = $game->account_balance($featured_strategy['account_id'])+$game->user_pending_bets($pending_bets_params);
						
						$pct_increase = 100*(($final_balance/$starting_balance)-1);
					}
				}
				?>
				<div class="row">
					<div class="col-sm-6">
						<input type="radio" name="featured_strategy_id" value="<?php echo $featured_strategy['featured_strategy_id']; ?>" id="featured_strategy_<?php echo $featured_strategy['featured_strategy_id']; ?>"<?php if ($user_strategy['featured_strategy_id'] == $featured_strategy['featured_strategy_id']) echo ' checked="checked"'; ?> /><label for="featured_strategy_<?php echo $featured_strategy['featured_strategy_id']; ?>">&nbsp; <?php echo $featured_strategy['strategy_name']; ?></label>
					</div>
					<div class="col-sm-6">
						<?php
						if ($pct_increase !== null) {
							if ($pct_increase >= 0) echo 'Up <font class="text-success">'.round($pct_increase, 2).'%</font>';
							else echo 'Down <font class="text-danger">'.round(-1*$pct_increase, 2).'%</font>';
							echo " in the past ".$app->format_seconds(time() - $start_bal_block['time_mined']);
						}
						?>
					</div>
				</div>
				<?php
			}
			?>
			<div class="row">
				<div class="col-sm-8">
					<input type="radio" name="featured_strategy_id" value="0" id="featured_strategy_0"<?php if ($user_strategy['voting_strategy'] == 'manual') echo ' checked="checked"'; ?> /><label for="featured_strategy_0">&nbsp; No automated strategy</label>
				</div>
				<div class="col-sm-4"></div>
			</div>
			<div id="featured_strategy_success" style="display: none;" class="text-success"></div>
			<div id="featured_strategy_error" style="display: none;" class="text-danger"></div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-sm btn-warning" data-dismiss="modal"><i class="fas fa-times"></i> &nbsp; Close</button>
			 &nbsp;&nbsp;or&nbsp;&nbsp; 
			<button class="btn btn-sm btn-success" id="featured_strategy_save_btn"><i class="fas fa-check-circle"></i> &nbsp; Save</button>
		</div>
	</form>
	<?php
}
else echo "Invalid game ID supplied.";
?>