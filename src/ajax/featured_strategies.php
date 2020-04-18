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
				Please select a strategy from the options below. 
				An auto strategy stakes your coins for you so that your account gains value 24/7 without requiring you to do anything. 
				The percentages shown below reflect recent performances but are no guarantee of future performance.
			</p>
			<p>
				To write your own custom auto strategy, please see our <a href="/api/about">API documentation</a>.
			</p>
			<?php
			$previous_rounds = 3;
			$current_event = $game->current_events[0];
			
			$featured_strategies = $game->fetch_featured_strategies();
			
			while ($featured_strategy = $featured_strategies->fetch()) {
				if ($featured_strategy['account_id'] > 0) {
					$event_ref_block = $current_event->db_event['event_starting_block'];				
					$performances = [];
					
					for ($i=0; $i<$previous_rounds; $i++) {
						$first_prev_event = $app->run_query("SELECT * FROM events WHERE game_id=:game_id AND event_starting_block<:event_ref_block ORDER BY event_index DESC;", [
							'game_id' => $game->db_game['game_id'],
							'event_ref_block' => $event_ref_block
						])->fetch();
						$event_ref_block = $first_prev_event['event_starting_block'];
						
						if ($featured_strategy['reference_starting_block'] <= $first_prev_event['event_starting_block']) {
							$ref_performance_events = $app->run_query("SELECT * FROM events WHERE game_id=:game_id AND event_starting_block=:block_id ORDER BY event_index ASC;", [
								'game_id' => $game->db_game['game_id'],
								'block_id' => $first_prev_event['event_starting_block']
							]);
							
							while ($db_event = $ref_performance_events->fetch()) {
								$bal1 = $game->account_balance_at_block($featured_strategy['account_id'], $db_event['event_final_block'], false);
								$bal2 = $game->account_balance_at_block($featured_strategy['account_id'], $db_event['event_final_block'], true);
								$performance = ($bal2/$bal1)-1;
								array_push($performances, $performance);
							}
						}
					}
					$performance_sum = array_sum($performances);
					$average_performance = $performance_sum/count($performances);
					
					$daily_performance = pow(1+$average_performance, 3);
				}
				?>
				<div class="row">
					<div class="col-sm-8">
						<input type="radio" name="featured_strategy_id" value="<?php echo $featured_strategy['featured_strategy_id']; ?>" id="featured_strategy_<?php echo $featured_strategy['featured_strategy_id']; ?>"<?php if ($user_strategy['featured_strategy_id'] == $featured_strategy['featured_strategy_id']) echo ' checked="checked"'; ?> /><label for="featured_strategy_<?php echo $featured_strategy['featured_strategy_id']; ?>">&nbsp; <?php echo $featured_strategy['strategy_name']; ?></label>
					</div>
					<div class="col-sm-4">
						<?php
						if ($featured_strategy['account_id'] > 0) echo round(($daily_performance-1)*100, 2)."% per day";
						?>
					</div>
				</div>
				<?php
			}
			?>
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