<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $app->user_is_admin($thisuser)) {
	$blockchain = new Blockchain($app, $_REQUEST['blockchain_id']);
	?>
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-body">
				<?php
				if ($_REQUEST['action'] == "view") {
					$blockchain_games = $app->run_query("SELECT * FROM games WHERE blockchain_id=:blockchain_id ORDER BY name ASC;", [
						'blockchain_id' => $blockchain->db_blockchain['blockchain_id']
					])->fetchAll();
					
					if (count($blockchain_games) == 1) $_REQUEST['game_id'] = $blockchain_games[0]['game_id'];
					
					if (!empty($_REQUEST['game_id'])) {
						$db_game = $app->fetch_game_by_id($_REQUEST['game_id']);
						
						if ($db_game['blockchain_id'] == $blockchain->db_blockchain['blockchain_id']) {
							$game = new Game($blockchain, $db_game['game_id']);
							$unclaimed_coins = $game->fetch_unclaimed_coins(true);
							$unclaimed_coins_total = array_sum(array_column($unclaimed_coins, 'colored_amount'));
							?>
							<p>There are <?php echo $game->display_coins($unclaimed_coins_total); ?> unclaimed coins.</p>
							
							<div class="form-group">
								<label for="manage_unclaimed_action">What would you like to do next?</label>
								
								<select class="form-control" id="manage_unclaimed_action" onChange="if (this.value == 'claim_all_to_account') $('#claim_all_to_account').show(); else $('#claim_all_to_account').hide();">
									<option value="">-- Please Select --</option>
									<option value="claim_all_to_account">Claim all <?php echo $game->db_game['coin_name_plural'] ;?> to an account</option>
								</select>
							</div>
							
							<div id="claim_all_to_account" style="display: none;">
								<form onSubmit="thisBlockchainManager.submitUnclaimedAction(<?php echo $blockchain->db_blockchain['blockchain_id']; ?>, <?php echo $game->db_game['game_id']; ?>); return false;">
									<div class="form-group">
										<label for="claim_all_to_account_id">Which account should the <?php echo $game->db_game['coin_name_plural'] ;?> be claimed to?</label>
										<select class="form-control" id="claim_all_to_account_id">
											<option value="">-- Please Select --</option>
											<?php
											$accounts = $app->run_query("SELECT * FROM currency_accounts WHERE user_id=:user_id AND game_id=:game_id ORDER BY account_name ASC;", [
												'user_id' => $thisuser->db_user['user_id'],
												'game_id' => $game->db_game['game_id']
											])->fetchAll();
											
											foreach ($accounts as $account) {
												?>
												<option value="<?php echo $account['account_id']; ?>"><?php echo "#".$account['account_id']." ".$account['account_name']; ?></option>
												<?php
											}
											?>
										</select>
									</div>
									<button class="btn btn-success">Claim <?php echo ucfirst($game->db_game['coin_name_plural']); ?></button>
								</form>
							</div>
							<?php
						}
						else {
							?>
							The game & blockchain IDs you supplied are inconsistent.
							<?php
						}
					}
					else {
						if (count($blockchain_games) > 0) {
							?>
							<div class="form-group">
								<label for="manage_unclaimed_game_id">Which game would you like to transfer coins to?</label>
								<select class="form-control" id="manage_unclaimed_game_id" onChange="thisBlockchainManager.manageUnclaimedGameSelected(<?php echo $blockchain->db_blockchain['blockchain_id']; ?>, this);">
									<option value="">-- Select --</option>
									<?php
									foreach ($blockchain_games as $db_game) {
										?>
										<option value="<?php echo $db_game['game_id']; ?>"><?php echo $db_game['name']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
							<?php
						}
						else {
							?>
							There are no games associated with this blockchain.
							<?php
						}
					}
				}
				else {
					if ($app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
						if ($_REQUEST['action'] == "claim_all_to_account") {
							$db_game = $app->fetch_game_by_id($_REQUEST['game_id']);
							
							if ($db_game['blockchain_id'] == $blockchain->db_blockchain['blockchain_id']) {
								$game = new Game($blockchain, $db_game['game_id']);
								$account = $app->fetch_account_by_id($_REQUEST['account_id']);
								
								if ($account['game_id'] == $game->db_game['game_id']) {
									$unclaimed_coins = $game->fetch_unclaimed_coins(true);
									$address_ids = array_values(array_unique(array_column($unclaimed_coins, 'address_id')));
									
									if (count($address_ids) > 0) {
										$app->give_many_addresses_to_account($account, $address_ids);
										echo ucfirst($game->db_game['coin_name_plural']); ?> have been granted!<br/>
										<a href="/accounts/?account_id=<?php echo $account['account_id']; ?>">Continue to account #<?php echo $account['account_id']; ?></a>
										<?php
									}
									else {
										?>
										Didn't find any coins to claim.
										<?php
									}
								}
								else {
									?>
									Please select a <?php echo $game->db_game['name']; ?> account.
									<?php
								}
							}
							else {
								?>
								The game & blockchain IDs you supplied are inconsistent.
								<?php
							}
						}
						else {
							?>
							Please supply a valid action.
							<?php
						}
					}
					else {
						?>
						You've reached an invalid link.
						<?php
					}
				}
				?>
			</div>
		</div>
	</div>
	<?php
}
else $app->output_message(2, "Permission denied.");
