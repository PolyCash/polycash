<?php
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$uri = $_SERVER['REQUEST_URI'];
$uri_parts = explode("/", $uri);

if ($uri_parts[1] == "api") {
	if ($uri_parts[2] != "" && strval(intval($uri_parts[2])) === strval($uri_parts[2])) {
		$game_id = intval($uri_parts[2]);
		
		$q = "SELECT game_id, maturity, pos_reward, pow_reward, round_length, p2p_mode, payout_weight, seconds_per_block, name FROM games WHERE game_id=".$game_id.";";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$db_game = $r->fetch();
			$game = new Game($app, $db_game['game_id']);
			$last_block_id = $game->last_block_id();
			$current_round = $game->block_to_round($last_block_id+1);
			
			$intval_vars = array('game_id','round_length','seconds_per_block','maturity');
			for ($i=0; $i<count($intval_vars); $i++) {
				$game->db_game[$intval_vars[$i]] = intval($game->db_game[$intval_vars[$i]]);
			}
			
			if (empty($uri_parts[3]) || $uri_parts[3] == "status") {
				$api_user = FALSE;
				$api_user_info = FALSE;
				
				if (!empty($_REQUEST['api_access_code'])) {
					$q = "SELECT * FROM users WHERE api_access_code=".$app->quote_escape($_REQUEST['api_access_code']).";";
					$r = $app->run_query($q);
					if ($r->rowCount() == 1) {
						$db_api_user = $r->fetch();
						$api_user = new User($app, $db_api_user['user_id']);
						$account_value = $api_user->account_coin_value($game);
						$immature_balance = $api_user->immature_balance($game);
						$mature_balance = $api_user->mature_balance($game);
						$votes_available = $api_user->user_current_votes($game, $last_block_id, $current_round);
						
						$api_user_info['username'] = $api_user->db_user['username'];
						$api_user_info['balance'] = $account_value;
						$api_user_info['mature_balance'] = $mature_balance;
						$api_user_info['immature_balance'] = $immature_balance;
						$api_user_info['votes_available'] = $votes_available;
						
						$mature_utxos = array();
						$mature_utxo_q = "SELECT * FROM transaction_ios i JOIN addresses a ON i.address_id=a.address_id WHERE i.spend_status='unspent' AND i.spend_transaction_id IS NULL AND a.user_id=".$api_user->db_user['user_id']." AND i.game_id=".$game->db_game['game_id']." AND (i.create_block_id <= ".($last_block_id-$game->db_game['maturity'])." OR i.instantly_mature = 1) ORDER BY i.io_id ASC;";
						$mature_utxo_r = $app->run_query($mature_utxo_q);
						$utxo_i = 0;
						while ($utxo = $mature_utxo_r->fetch()) {
							$mature_utxos[$utxo_i] = array('utxo_id'=>intval($utxo['io_id']), 'coins'=>$utxo['amount'], 'create_block_id'=>intval($utxo['create_block_id']));
							$utxo_i++;
						}
						$api_user_info['my_utxos'] = $mature_utxos;
					}
				}
				
				$output_game['game_id'] = $game->db_game['game_id'];
				$output_game['name'] = $game->db_game['name'];
				$output_game['last_block_id'] = $last_block_id;
				$output_game['current_round'] = $current_round;
				$output_game['block_within_round'] = $game->block_id_to_round_index($last_block_id+1);
				
				$event_vars = array('event_id','event_type_id','event_name','event_starting_block','event_final_block','option_name','option_name_plural');
				$current_events = array();
				for ($i=0; $i<count($game->current_events); $i++) {
					for ($j=0; $j<count($event_vars); $j++) {
						$api_event[$event_vars[$j]] = $game->current_events[$i]->db_event[$event_vars[$j]];
					}
					$api_event['options'] = array();
					
					$event_stats = $game->current_events[$i]->round_voting_stats_all($current_round);
					$total_vote_sum = $event_stats[0];
					$max_vote_sum = $event_stats[1];
					$ranked_stats = $event_stats[2];
					$option_id_to_rank = $event_stats[3];
					$confirmed_votes = $event_stats[4];
					$unconfirmed_votes = $event_stats[5];
					
					$qq = "SELECT * FROM options op JOIN events e ON op.event_id=e.event_id WHERE e.event_id=".$game->current_events[$i]->db_event['event_id'].";";
					$rr = $app->run_query($qq);
					while ($option = $rr->fetch()) {
						$stat = $ranked_stats[$option_id_to_rank[$option['option_id']]];
						$api_stat = false;
						$api_stat['option_id'] = (int) $option['option_id'];
						$api_stat['name'] = $stat['name'];
						$api_stat['rank'] = $option_id_to_rank[$option['option_id']]+1;
						$api_stat['confirmed_votes'] = $app->friendly_intval($stat[$game->db_game['payout_weight'].'_score']);
						$api_stat['unconfirmed_votes'] = $app->friendly_intval($stat['unconfirmed_'.$game->db_game['payout_weight'].'_score']);
						array_push($api_event['options'], $api_stat);
					}
					array_push($current_events, $api_event);
				}
				$output_game['current_events'] = $current_events;
				/*$game_votes = false;
				
				$qq = "SELECT * FROM options op JOIN events e ON op.event_id=e.event_id WHERE e.game_id=".$game->db_game['game_id'].";";
				$rr = $app->run_query($qq);
				while ($option = $rr->fetch()) {
					$stat = $ranked_stats[$option_id_to_rank[$option['option_id']]];
					$api_stat = false;
					$api_stat['option_id'] = (int) $option['option_id'];
					$api_stat['name'] = $stat['name'];
					$api_stat['rank'] = $option_id_to_rank[$option['option_id']]+1;
					$api_stat['confirmed_votes'] = $app->friendly_intval($stat[$game->db_game['payout_weight'].'_score']);
					$api_stat['unconfirmed_votes'] = $app->friendly_intval($stat['unconfirmed_'.$game->db_game['payout_weight'].'_score']);
					
					$game_votes[$option['option_id']] = $api_stat;
				}*/
				
				$api_output = array('status_code'=>1, 'status_message'=>"Successful", 'game'=>$output_game, 'user_info'=>$api_user_info);
			}
			else {
				$api_output = array('status_code'=>0, 'status_message'=>'Error, URL not recognized');
			}
		}
		else {
			$api_output = array('status_code'=>0, 'status_message'=>'Error: Invalid game ID');
		}
		echo json_encode($api_output);
	}
	else if ($uri_parts[2] == "about") {
		require_once('includes/connect.php');
		require_once('includes/get_session.php');
		if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);
		
		$pagetitle = $GLOBALS['coin_brand_name']." API Documentation";
		$nav_tab_selected = "api";
		include('includes/html_start.php');
		
		$game_id = $app->get_site_constant('primary_game_id');
		if (empty($game_id)) {
			$game_id = $app->run_query("SELECT * FROM games WHERE featured=1 ORDER BY game_id ASC LIMIT 1;")->fetch()['game_id'];
		}
		$api_game = new Game($app, $game_id);
		?>
		<div class="container" style="max-width: 1000px;">
			<h1><?php echo $GLOBALS['coin_brand_name']; ?> API Documentation</h1>
			<p>
				<?php echo $GLOBALS['coin_brand_name']; ?> web wallets provide several strategies for automating your <?php echo $GLOBALS['coin_brand_name']; ?> voting behavior.  However, some users may wish to use custom logic in their voting strategies. The <?php echo $GLOBALS['coin_brand_name']; ?> API allows this functionality through a standardized format for sharing <?php echo $GLOBALS['coin_brand_name']; ?> voting recommendations. Using the <?php echo $GLOBALS['coin_brand_name']; ?> API can be as simple as finding a public recommendations URL and plugging it into your <?php echo $GLOBALS['coin_brand_name']; ?> user account.  Or you can set up your own voting recommendations client using the information below.
			</p>
			<p>
				To get started, please download this example API client written in PHP.<br/>
				<a class="btn btn-success" href="/api/download-client-example/">Download example API client</a>
				<br/><br/>
			</p>
			<p>
				<b><a target="_blank" href="/api/<?php echo $api_game->db_game['game_id']; ?>/status/">/api/<?php echo $api_game->db_game['game_id']; ?>/status/</a></b> &nbsp;&nbsp;&nbsp; <a href="" onclick="$('#api_status_example').toggle('fast'); return false;">See Example</a><br/>
				Yields information about current status of the blockchain.
				<br/>
			</p>
<pre id="api_status_example" style="display: none;">

</pre>
			<p>
				<b>/api/<?php echo $api_game->db_game['game_id']; ?>/status/?api_access_code=&lt;ACCESS_CODE&gt;</b> &nbsp;&nbsp;&nbsp; <a href="" onclick="$('#api_status_user_example').toggle('fast'); return false;">See Example</a><br/>
				Supply your API access code to get relevant info on your user account in addition to general blockchain information.
				<br/>
			</p>
<pre id="api_status_user_example" style="display: none;">

</pre>
		</div>
		<?php
		include('includes/html_stop.php');
	}
	else if ($uri_parts[2] == "download-client-example") {
		$example_password = "password123";
		$fname = "api_client.php";
		
		$fh = fopen($fname, 'r');
		$raw = fread($fh, filesize($fname));
		$raw = str_replace('include("includes/config.php");', '', $raw);
		$raw = str_replace('$access_key = $GLOBALS[\'cron_key_string\']', '$access_key = "'.$example_password.'"', $raw);
		$raw = str_replace('$GLOBALS[\'cron_key_string\']', $example_password, $raw);
		$raw = str_replace('$GLOBALS[\'default_server_api_access_key\']', '""', $raw);
		
		header('Content-Type: application/x-download');
		header('Content-disposition: attachment; filename="'.$GLOBALS['coin_brand_name'].'APIClient.php"');
		header('Cache-Control: public, must-revalidate, max-age=0');
		header('Pragma: public');
		header('Content-Length: '.strlen($raw));
		header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
		echo $raw;
	}
	else if ($uri == "/api/") {
		header("Location: /api/about");
		die();
	}
	else {
		echo json_encode(array('status_code'=>0, 'status_message'=>"You've reached an invalid URL."));
	}
}
?>