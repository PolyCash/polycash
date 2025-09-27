<?php
include(AppSettings::srcPath()."/includes/html_start.php");

$uri_parts = explode("/", $_SERVER['REQUEST_URI']);

if (isset($uri_parts[4]) && $uri_parts[4] == "approve") {
	$join_request = Faucet::fetchJoinRequestById($app, $uri_parts[5]);
	
	$message = null;
	if ($join_request) {
		$join_faucet = Faucet::fetchById($app, $join_request['faucet_id']);
		
		if ($join_faucet && $join_faucet['faucet_id'] == $faucet['faucet_id'] && $faucet['user_id'] == $thisuser->db_user['user_id']) { 
			if (empty($join_request['approve_time'])) {
				$join_user = $app->fetch_user_by_id($join_request['user_id']);
				
				$app->run_insert_query("faucet_receivers", [
					'faucet_id' => $join_faucet['faucet_id'],
					'user_id' => $join_request['user_id'],
					'join_time' => time(),
				]);
				$new_receiver = Faucet::myReceiverById($app, $app->last_insert_id());

				$app->run_query("UPDATE faucet_join_requests SET approve_time=:approve_time, receiver_id=:receiver_id WHERE request_id=:request_id;", [
					'approve_time' => time(),
					'receiver_id' => $new_receiver['receiver_id'],
					'request_id' => $join_request['request_id'],
				]);

				$message = $join_user['first_name']." ".$join_user['last_name']." has successfully been added to faucet #".$join_faucet['faucet_id'];
			} else {
				$message = "This join request has already been approved.";
			}
		} else {
			$message = "You can't approve join requests for that faucet.";
		}
	} else {
		$message = "Please supply a valid join request ID.";
	}
	?>
	<div class="container-fluid" style="padding-top: 15px;">
		<div class="panel panel-default" style="margin-top: 15px;">
			<div class="panel-heading">
				<div class="panel-title"><?php echo 'Approve user for faucet: #'.$faucet['faucet_id'].', '.$faucet['account_name']; ?></div>
			</div>
			<div class="panel-body">
				<?php echo $message; ?>
			</div>
		</div>
	</div>
	<?php
	die();
}

$message = null;

if ($_SERVER['REQUEST_METHOD'] == "POST") {
	$faucetFieldsInfo = Faucet::$fieldsInfo;

	if ($faucet) {
		$updateFaucetQ = "UPDATE faucets SET ";
		$updateFaucetParams = [];
		foreach ($faucetFieldsInfo as $fieldName => $fieldInfo) {
			$updateFaucetQ .= $fieldName."=:".$fieldName.", ";
			$updateFaucetParams[$fieldName] = $_REQUEST[$fieldName];
			$faucet[$fieldName] = $_REQUEST[$fieldName];
		}
		$updateFaucetQ = substr($updateFaucetQ, 0, strlen($updateFaucetQ)-2)." WHERE faucet_id=:faucet_id;";
		$updateFaucetParams['faucet_id'] = $faucet['faucet_id'];
		$app->run_query($updateFaucetQ, $updateFaucetParams);
		$message = "Successfully edited faucet #".$faucet['faucet_id'];
	} else {
		$account = $app->create_new_account([
			'user_id' => $thisuser->db_user['user_id'],
			'game_id' => $game->db_game['game_id'],
			'currency_id' => $game->blockchain->currency_id(),
			'account_name' => $game->db_game['name']." Faucet: ".htmlspecialchars(strip_tags($_REQUEST['display_from_name'])),
		]);

		$insertFaucetParams['user_id'] = $thisuser->db_user['user_id'];
		$insertFaucetParams['account_id'] = $account['account_id'];
		$insertFaucetParams['game_id'] = $game->db_game['game_id'];
		$insertFaucetParams['created_at'] = time();
		foreach ($faucetFieldsInfo as $fieldName => $fieldInfo) {
			$fieldVal = $_REQUEST[$fieldName];
			if (!empty($fieldInfo['stripTags'])) $fieldVal = htmlspecialchars(strip_tags($fieldVal));
			$insertFaucetParams[$fieldName] = $fieldVal;
			$faucet[$fieldName] = $fieldVal;
		}
		
		$app->run_insert_query("faucets", $insertFaucetParams);
		$faucet = Faucet::fetchById($app, $app->last_insert_id());
		$message = "Successfully created faucet #".$faucet['faucet_id'];
	}
}

?>
<div class="container-fluid" style="padding-top: 15px;">
	<?php
	echo $app->render_view('game_links', [
		'explore_mode' => 'manage_faucets',
		'game' => $game,
		'blockchain' => $game->blockchain,
		'block' => null,
		'io' => null,
		'transaction' => null,
		'address' => null,
		'account' => null,
		'my_games' => $app->my_games($thisuser->db_user['user_id'], true)->fetchAll(PDO::FETCH_ASSOC),
	]);

	if (!empty($message)) echo '<div style="margin-top: 15px;">'.$app->render_error_message($message, 'success').'</div>';
	?>
	<div class="panel panel-default" style="margin-top: 15px;">
		<div class="panel-heading">
			<div class="panel-title"><?php echo $faucet ? 'Manage Faucet: '.$faucet['account_name'] : 'New Faucet'; ?></div>
		</div>
		<div class="panel-body">
			<p>&larr; <a href="/manage_faucets/<?php echo $game->db_game['url_identifier']; ?>">All my faucets in <?php echo $game->db_game['name']; ?></a></p>
			<div class="row">
				<div class="col-lg-6">
					<form method="post" action="/manage_faucets/<?php echo $game->db_game['url_identifier']; ?>/<?php echo $faucet ? $faucet['faucet_id'] : 'new'; ?>">
						<?php if ($faucet) { ?>
						<p>Faucet ID: <?php echo $faucet['faucet_id']; ?></p>
						<p>Account: <a href="/accounts/?account_id=<?php echo $faucet['account_id']; ?>"><?php echo $faucet['account_id']; ?></a></p>
						<?php } ?>
						<div class="form-group">
							<label for="faucet_enabled">Faucet enabled?</label>
							<select class="form-control" name="faucet_enabled" id="faucet_enabled">
								<option value="">-- Please Select --</option>
								<option value="0"<?php if ($faucet && !$faucet['faucet_enabled']) echo ' selected="selected"'; ?>>Disabled</option>
								<option value="1"<?php if ($faucet && $faucet['faucet_enabled']) echo ' selected="selected"'; ?>>Enabled</option>
							</select>
						</div>
						<div class="form-group">
							<label for="everyone_eligible">Everyone eligible?</label>
							<select class="form-control" id="everyone_eligible" name="everyone_eligible">
								<option value="">-- Please Select --</option>
								<option value="1"<?php if ($faucet && $faucet['everyone_eligible']) echo ' selected="selected"'; ?>>Yes</option>
								<option value="0"<?php if ($faucet && !$faucet['everyone_eligible']) echo ' selected="selected"'; ?>>No</option>
							</select>
						</div>
						<div class="form-group">
							<label for="approval_method">Approval method:</label>
							<select class="form-control form-control-sm" id="approval_method" name="approval_method">
								<option value="">-- Please Select --</option>
								<?php
								$approvalMethods = [
									'invite_only',
									'request_to_join',
									'auto_approve',
								];
								
								foreach ($approvalMethods as $approvalMethod) {
									echo '<option value="'.$approvalMethod.'"'.($approvalMethod == $faucet['approval_method'] ? ' selected="selected"' : '').'>'.ucfirst(str_replace("_", " ", $approvalMethod)).'</option>';
								}
								?>
							</select>
						</div>
						<div class="form-group">
							<label for="txo_size">TXO size:</label>
							<input class="form-control" type="text" name="txo_size" id="txo_size" value="<?php echo $faucet ? $faucet['txo_size'] : ''; ?>" />
						</div>
						<div class="form-group">
							<label for="display_from_name">Display from name:</label>
							<input class="form-control" type="text" name="display_from_name" id="display_from_name" value="<?php echo $faucet ? htmlspecialchars($faucet['display_from_name']) : ''; ?>" />
						</div>
						<div class="form-group">
							<label for="bonus_claims">How many extra faucet claims should users get upon signing up?</label>
							<input type="text" class="form-control" name="bonus_claims" id="bonus_claims" value="<?php echo $faucet ? $faucet['bonus_claims'] : ''; ?>" />
						</div>
						<div class="form-group">
							<label for="sec_per_faucet_claim">How often should users receive one claim from the faucet? Please answer in seconds.</label>
							<input type="text" class="form-control" name="sec_per_faucet_claim" id="sec_per_faucet_claim" value="<?php echo $faucet ? $faucet['sec_per_faucet_claim'] : ''; ?>" />
						</div>
						<div class="form-group">
							<label for="min_sec_between_claims">If users go for a long time without claiming from the faucet, they may be eligible to claim many times.  What's the maximum number of claims these users should receive at once?</label>
							<input type="text" class="form-control" name="max_claims_at_once" id="max_claims_at_once" value="<?php echo $faucet ? $faucet['max_claims_at_once'] : ''; ?>" />
						</div>
						<div class="form-group">
							<label for="min_sec_between_claims">If users go for a long time without claiming from the faucet, they may be eligible to claim many times.  How many seconds should these users have to wait after claiming?</label>
							<input type="text" class="form-control" name="min_sec_between_claims" id="min_sec_between_claims" value="<?php echo $faucet ? $faucet['min_sec_between_claims'] : ''; ?>" />
						</div>

						<button class="btn btn-success btn-sm">Submit</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
<?php
include(AppSettings::srcPath().'/includes/html_stop.php');
