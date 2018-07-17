<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");
include(realpath(dirname(dirname(__FILE__)))."/includes/get_session.php");

if ($game) {
	$game->load_current_events();
	$user_game = $thisuser->ensure_user_in_game($game, false);
	$user_strategy = $game->fetch_user_strategy($user_game);
	?>
	<p>
		Please select a strategy from the options below. 
		An auto strategy stakes your coins for you so that your account gains value 24/7 without requiring you to do anything. 
		The percentages shown below reflect recent performances but are no guarantee of future performance.
	</p>
	<p>
		To write your own custom auto strategy, please see our <a href="/api/about">API documentation</a>.
	</p>
	<form method="get" onsubmit="save_featured_strategy(); return false;">
		<?php
		$previous_rounds = 3;
		$current_event = $game->current_events[0];
		
		$q = "SELECT * FROM featured_strategies fs JOIN currency_accounts ca ON fs.reference_account_id=ca.account_id WHERE ca.game_id='".$game->db_game['game_id']."';";
		$r = $app->run_query($q);
		
		while ($featured_strategy = $r->fetch()) {
			$event_ref_block = $current_event->db_event['event_starting_block'];
			
			$performances = array();
			
			for ($i=0; $i<$previous_rounds; $i++) {
				$qq = "SELECT * FROM events WHERE game_id='".$game->db_game['game_id']."' AND event_starting_block<".$event_ref_block." ORDER BY event_index DESC;";
				$rr = $app->run_query($qq);
				$first_prev_event = $rr->fetch();
				$event_ref_block = $first_prev_event['event_starting_block'];
				
				if ($featured_strategy['reference_starting_block'] <= $first_prev_event['event_starting_block']) {
					$qq = "SELECT * FROM events WHERE game_id='".$game->db_game['game_id']."' AND event_starting_block='".$first_prev_event['event_starting_block']."' ORDER BY event_index ASC;";
					$rr = $app->run_query($qq);
					
					while ($db_event = $rr->fetch()) {
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
			?>
			<div class="row bordered_row">
				<div class="col-sm-4">
					<input type="radio" name="featured_strategy_id" value="<?php echo $featured_strategy['featured_strategy_id']; ?>" id="featured_strategy_<?php echo $featured_strategy['featured_strategy_id']; ?>"<?php if ($user_strategy['featured_strategy_id'] == $featured_strategy['featured_strategy_id']) echo ' checked="checked"'; ?> /><label for="featured_strategy_<?php echo $featured_strategy['featured_strategy_id']; ?>">&nbsp; <?php echo $featured_strategy['strategy_name']; ?></label>
				</div>
				<div class="col-sm-4">
					<?php
					echo round(($daily_performance-1)*100, 2)."% per day";
					?>
				</div>
			</div>
			<?php
		}
		?>
		<div class="row bordered_row"></div>
		
		<button class="btn btn-success" id="featured_strategy_save_btn">Save</button>
	</form>
	<?php
}
else echo "Invalid game ID supplied.";
?>