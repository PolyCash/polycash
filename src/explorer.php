<?php
ini_set('memory_limit', '1024M');
require(AppSettings::srcPath().'/includes/connect.php');
require(AppSettings::srcPath().'/includes/get_session.php');

if (empty($uri_parts[4])) $explore_mode = "";
else $explore_mode = $uri_parts[4];
if ($explore_mode == "tx") $explore_mode = "transactions";

if (empty($uri_parts[3])) $game_identifier = "";
else $game_identifier = $uri_parts[3];

$game = false;
$blockchain = false;
$user_game = false;

if ($uri_parts[2] == "games") {
	$db_game = $app->fetch_game_by_identifier($game_identifier);
	
	if ($db_game && in_array($db_game['game_status'], ['running','completed','published'])) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		if ($thisuser) {
			$user_game = $app->fetch_user_game($thisuser->db_user['user_id'], $game->db_game['game_id']);
		}
	}
}
else if ($uri_parts[2] == "blockchains") {
	$blockchain_identifier = $game_identifier;
	$db_blockchain = $app->fetch_blockchain_by_identifier($blockchain_identifier);
	
	if ($db_blockchain) {
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		
		if (rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer/blockchains/".$db_blockchain['url_identifier']) {
			header("Location: /explorer/blockchains/".$db_blockchain['url_identifier']."/blocks/");
			die();
		}
	}
}

if (rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer") $explore_mode = "explorer_home";
else if ($game && rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer/games/".$game->db_game['url_identifier']) $explore_mode = "blocks";
else if (!$game && $blockchain && rtrim($_SERVER['REQUEST_URI'], "/") == "/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']) $explore_mode = "blocks";

if ($explore_mode == "explorer_home" || ($blockchain && !$game && in_array($explore_mode, array('blocks','addresses','transactions','utxos','utxo','definition'))) || ($game && in_array($explore_mode, array('events','blocks','addresses','transactions','utxos','utxo','my_bets','definition')))) {
	if ($game) {
		$last_block_id = $blockchain->last_block_id();
		$current_round = $game->block_to_round($last_block_id+1);
	}
	
	$round = false;
	$block = false;
	$address = false;
	
	$mode_error = true;
	
	if ($explore_mode == "explorer_home") {
		$mode_error = false;
		$pagetitle = AppSettings::getParam('coin_brand_name')." - Please select a game";
	}
	else if ($explore_mode == "my_bets") {
		$mode_error = false;
		$pagetitle = "My bets in ".$game->db_game['name'];
	}
	else if ($explore_mode == "events") {
		$event_id = $uri_parts[5];
		
		if ($event_id === "") {
			$mode_error = false;
			$pagetitle = "Results - ".$game->db_game['name'];
		}
		else {
			$event_index = (int) $event_id;
			$db_event = $game->fetch_event_by_index($event_index);
			
			if ($db_event) {
				$event = new Event($game, false, $db_event['event_id']);
				$pagetitle = "Results: ".$event->db_event['event_name'];
				$mode_error = false;
			}
			else {
				$mode_error = true;
				$pagetitle = $game->db_game['name']." - Failed to load event";
			}
		}
	}
	else if ($explore_mode == "addresses") {
		$address_text = $uri_parts[5];
		$address = $app->fetch_address($address_text);
		
		if ($address) {
			$mode_error = false;
			if ($game) $pagetitle = $game->db_game['name'];
			else $pagetitle = $blockchain->db_blockchain['blockchain_name'];
			$pagetitle .= " Address: ".$address['address'];
		}
	}
	else if ($explore_mode == "blocks") {
		if (empty($uri_parts[5])) $block_id_str = "";
		else $block_id_str = $uri_parts[5];
		
		if ($block_id_str !== "0" && (empty($block_id_str) || strpos($block_id_str, '?') !== false)) {
			$mode_error = false;
			if ($game) $pagetitle = $game->db_game['name']." - List of blocks";
			else $pagetitle = $blockchain->db_blockchain['blockchain_name']." - List of blocks";
		}
		else {
			$block_id = (int) $block_id_str;
			
			if ($game) {
				$block = $app->run_query("SELECT b.time_mined, b.block_hash, gb.*, gb.load_time AS game_load_time FROM blocks b JOIN game_blocks gb ON b.block_id=gb.block_id WHERE b.blockchain_id=:blockchain_id AND b.block_id=:block_id AND gb.game_id=:game_id;", [
					'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
					'block_id' => $block_id,
					'game_id' => $game->db_game['game_id']
				])->fetch();
			}
			else {
				$block = $blockchain->fetch_block_by_id($block_id);
			}
			
			if ($block) {
				$mode_error = false;
				
				if ($game) $pagetitle = $game->db_game['name'];
				else $pagetitle = $blockchain->db_blockchain['blockchain_name'];
				$pagetitle .= " Block #".$block['block_id'];
			}
			else {
				if ($game) {
					$block = $app->run_query("SELECT b.time_mined, b.block_hash, gb.*, gb.load_time AS game_load_time FROM blocks b JOIN game_blocks gb ON b.block_id=gb.block_id WHERE b.blockchain_id=:blockchain_id AND b.block_hash=:block_hash AND gb.game_id=:game_id;", [
						'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
						'block_hash' => $uri_parts[5],
						'game_id' => $game->db_game['game_id']
					])->fetch();
				}
				else {
					$block = $blockchain->fetch_block_by_hash($uri_parts[5]);
				}
				
				if ($block) {
					$mode_error = false;
					if ($game) $pagetitle = $game->db_game['name'];
					else $pagetitle = $blockchain->db_blockchain['blockchain_name'];
					$pagetitle .= " Block #".$block['block_id'];
				}
			}
		}
	}
	else if ($explore_mode == "utxo") {
		if (count($uri_parts) >= 7) {
			$tx_hash = $uri_parts[5];
			$out_index = (int) $uri_parts[6];
			
			$io_tx = $blockchain->fetch_transaction_by_hash($tx_hash);
			
			if ($io_tx) {
				$io_params = [
					'create_transaction_id' => $io_tx['transaction_id'],
					'out_index' => $out_index
				];
				
				if ($game) {
					$io_q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:create_transaction_id AND gio.game_id=:game_id AND gio.game_out_index=:out_index;";
					$io_params['game_id'] = $game->db_game['game_id'];
				}
				else {
					$io_q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:create_transaction_id AND io.out_index=:out_index;";
				}
				$io_r = $app->run_query($io_q, $io_params);
			}
		}
		else {
			if ($game) {
				$io_q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id JOIN addresses a ON io.address_id=a.address_id WHERE gio.game_id=:game_id AND gio.game_io_index=:game_io_index;";
				$io_r = $app->run_query($io_q, [
					'game_id' => $game->db_game['game_id'],
					'game_io_index' => (int) $uri_parts[5]
				]);
			}
			else {
				$io_q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN addresses a ON io.address_id=a.address_id WHERE io.io_id=:io_id;";
				$io_r = $app->run_query($io_q, ['io_id' => (int) $uri_parts[5]]);
			}
		}
		
		if (!empty($io_r) && $io_r->rowCount() > 0) {
			$io = $io_r->fetch();
			$mode_error = false;
			if ($game) $pagetitle = "UTXO #".$io['game_io_index'].": ".$game->db_game['name']." Explorer";
			else $pagetitle = "UTXO #".$io['io_id'].": ".$blockchain->db_blockchain['blockchain_name']." Explorer";
		}
		else {
			$io = false;
			$mode_error = true;
		}
	}
	else if ($explore_mode == "transactions") {
		if ($uri_parts[5] == "unconfirmed") {
			$explore_mode = "unconfirmed";
			$mode_error = false;
			$pagetitle = "Unconfirmed Transactions";
			if ($game) $pagetitle .= " - ".$game->db_game['name'];
			else $pagetitle .= " - ".$blockchain->db_blockchain['blockchain_name'];
		}
		else {
			if (strlen($uri_parts[5]) < 15) {
				$tx_id = intval($uri_parts[5]);
				$transaction = $app->fetch_transaction_by_id($tx_id);
			}
			else {
				$tx_hash = trim(strip_tags(urldecode($uri_parts[5])));
				
				if (strpos($tx_hash, " ") === false) {
					$transaction = $blockchain->fetch_transaction_by_hash($tx_hash);
					
					// Allow unconfirmed txns to be loaded by viewing them in explorer, for user convenience
					// But not confirmed b/c they may be before the blockchain first required block
					if (!$transaction) {
						$blockchain->load_coin_rpc();
						
						if ($blockchain->coin_rpc) {
							try {
								$transaction_rpc = $blockchain->coin_rpc->getrawtransaction($tx_hash, true);
								
								if ($transaction_rpc && empty($transaction_rpc['blockhash'])) {
									$blockchain->walletnotify($tx_hash, true);
									$transaction = $blockchain->fetch_transaction_by_hash($tx_hash);
								}
							}
							catch (Exception $e) {}
						}
					}
				}
			}
			
			if ($transaction) {
				$mode_error = false;
				
				if ($game) $pagetitle = $game->db_game['name'];
				else $pagetitle = $blockchain->db_blockchain['blockchain_name'];
				$pagetitle .= " Transaction: ".$transaction['tx_hash'];
			}
		}
	}
	else if ($explore_mode == "utxos") {
		$account = false;
		
		if (!empty($_REQUEST['account_id'])) {
			$account_id = (int) $_REQUEST['account_id'];
			$db_account = $app->fetch_account_by_id($account_id);
			
			if ($db_account) {
				if ($thisuser && $thisuser->db_user['user_id'] == $db_account['user_id']) $account = $db_account;
				else echo '<font class="redtext">Error: invalid account ID.</font><br/>';
			}
		}
		
		if ($game) $pagetitle = $game->db_game['name'];
		else $pagetitle = $blockchain->db_blockchain['blockchain_name'];
		
		if ($account) $pagetitle .= " - UTXOs in ".$account['account_name'];
		else $pagetitle .= " - List of UTXOs";
		
		$mode_error = false;
	}
	else if ($explore_mode == "definition") {
		if ($game) $pagetitle = $game->db_game['name']." game definition";
		else $pagetitle = $blockchain->db_blockchain['blockchain_name']." blockchain definition";
		$mode_error = false;
	}
	
	if ($mode_error) {
		Router::Send404();
	}
	else {
		if (empty($pagetitle)) $pagetitle = AppSettings::getParam('coin_brand_name')." - Blockchain Explorer";
		$nav_tab_selected = "explorer";
		include(AppSettings::srcPath().'/includes/html_start.php');
		?>
		<div class="container-fluid" style="padding-top: 15px;">
			<?php
			$coins_per_vote = 0;
			
			if ($game) {
				?>
				<script type="text/javascript">
				games.push(new Game(thisPageManager, <?php
					echo $game->db_game['game_id'];
					echo ', false';
					echo ', false';
					echo ', false';
					echo ', "'.$game->db_game['payout_weight'].'"';
					echo ', '.$game->db_game['round_length'];
					echo ', 0';
					echo ', "'.$game->db_game['url_identifier'].'"';
					echo ', "'.$game->db_game['coin_name'].'"';
					echo ', "'.$game->db_game['coin_name_plural'].'"';
					echo ', "'.$game->blockchain->db_blockchain['coin_name'].'"';
					echo ', "'.$game->blockchain->db_blockchain['coin_name_plural'].'"';
					echo ', "explorer", false';
					echo ', "'.$game->logo_image_url().'"';
					echo ', "'.$game->vote_effectiveness_function().'"';
					echo ', "'.$game->effectiveness_param1().'"';
					echo ', "'.$game->blockchain->db_blockchain['seconds_per_block'].'"';
					echo ', "'.$game->db_game['inflation'].'"';
					echo ', "'.$game->db_game['exponential_inflation_rate'].'"';
					echo ', false';
					echo ', "'.$game->db_game['decimal_places'].'"';
					echo ', "'.$game->blockchain->db_blockchain['decimal_places'].'"';
					echo ', "'.$game->db_game['view_mode'].'"';
					echo ', 0';
					echo ', false';
					echo ', "'.$game->db_game['default_betting_mode'].'"';
					echo ', false';
				?>));
				</script>
				<?php
				
				if ($game->db_game['inflation'] == "exponential") $coins_per_vote = $game->blockchain->app->coins_per_vote($game->db_game);
			}
			
			if ($blockchain || $game) {
				?>
				<script type="text/javascript">
				thisPageManager.blockchain_id = <?php
				if ($blockchain) echo $blockchain->db_blockchain['blockchain_id'];
				else echo $game->blockchain->db_blockchain['blockchain_id'];
				?>;
				</script>
				<div class="row">
					<div class="col-sm-7 ">
						<ul class="list-inline explorer_nav" id="explorer_nav">
							<?php if ($game) { ?>
							<li><a <?php if ($explore_mode == 'my_bets') echo 'class="selected" '; ?>href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/my_bets/">My Bets</a></li>
							<?php } ?>
							<li><a <?php if ($explore_mode == 'blocks') echo 'class="selected" '; ?>href="/explorer/<?php echo $uri_parts[2]; ?>/<?php
							if ($game) echo $game->db_game['url_identifier'];
							else echo $blockchain->db_blockchain['url_identifier'];
							?>/blocks/">Blocks</a></li>
							<?php if ($game) { ?>
							<li><a <?php if ($explore_mode == 'events') echo 'class="selected" '; ?>href="/explorer/<?php echo $uri_parts[2]; ?>/<?php
							if ($game) echo $game->db_game['url_identifier'];
							else echo $blockchain->db_blockchain['url_identifier'];
							?>/events/">Events</a></li>
							<?php } ?>
							<?php if ($game) { ?>
							<li><a <?php if ($explore_mode == 'utxos') echo 'class="selected" '; ?>href="/explorer/<?php echo $uri_parts[2]; ?>/<?php
							echo $game->db_game['url_identifier'];
							?>/utxos/">UTXOs</a></li>
							<?php } ?>
							<li><a <?php if ($explore_mode == 'unconfirmed') echo 'class="selected" '; ?>href="/explorer/<?php echo $uri_parts[2]; ?>/<?php
							if ($game) echo $game->db_game['url_identifier'];
							else echo $blockchain->db_blockchain['url_identifier'];
							?>/transactions/unconfirmed/">Unconfirmed TXNs</a></li>
							<?php if ($game && $game->db_game['escrow_address'] != "") { ?>
							<li><a <?php if (($explore_mode == 'addresses' && $address['address'] == $game->db_game['escrow_address']) || ($explore_mode == "transactions" && $transaction['tx_hash'] == $game->db_game['genesis_tx_hash'])) echo 'class="selected" '; ?>href="/explorer/<?php echo $uri_parts[2]; ?>/<?php echo $game->db_game['url_identifier']; ?>/transactions/<?php echo $game->db_game['genesis_tx_hash']; ?>">Genesis</a></li>
							<?php } ?>
							<?php if ($game) { ?>
							<li><a <?php if ($explore_mode == 'definition') echo 'class="selected" '; ?>href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/definition/">Game Definition</a>
							<?php } else { ?>
							<li><a <?php if ($explore_mode == 'definition') echo 'class="selected" '; ?>href="/explorer/blockchains/<?php echo $blockchain->db_blockchain['url_identifier']; ?>/definition/">Definition</a>
							<?php } ?>
						</ul>
					</div>
					<div class="col-sm-4 row-no-padding">
						<input type="text" class="form-control" placeholder="Search..." id="explorer_search" />
					</div>
					<div class="col-sm-1 row-no-padding">
						<button class="btn btn-primary" onclick="thisPageManager.explorer_search();">Go</button>
					</div>
				</div>
				<?php
				if ($game) {
					echo "<a class='btn btn-sm btn-primary' href='/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/";
					if (in_array($explore_mode, array('blocks','addresses','transactions','utxos','utxo'))) {
						echo $explore_mode."/";
						if ($explore_mode == "blocks") echo $block['block_id'];
						else if ($explore_mode == "addresses") echo $address['address'];
						else if ($explore_mode == "transactions") echo $transaction['tx_hash'];
						else if ($explore_mode == "utxo") echo $io['tx_hash']."/".$io['out_index'];
						else if ($explore_mode == "utxos") {
							if ($account) echo "?account_id=".$account['account_id'];
						}
						if ($explore_mode != "utxos") echo "/";
					}
					echo "'><i class=\"fas fa-link\"></i> &nbsp; View on ".$game->blockchain->db_blockchain['blockchain_name']."</a>\n";
					?>
					<a href="/wallet/<?php echo $game->db_game['url_identifier']; ?>/" class="btn btn-sm btn-success"><i class="fas fa-play-circle"></i> &nbsp; Play Now</a>
					<?php
				}
			}
			?>
			<div class="panel panel-default" style="margin-top: 15px;">
				<?php
				if ($game) {
					$coins_per_vote = $game->blockchain->app->coins_per_vote($game->db_game);
				}
				else $coins_per_vote = 0;
				
				if ($explore_mode == "events") {
					if (!empty($db_event)) {
						$last_block_id = $game->last_block_id();
						
						echo '<div class="panel-heading"><div class="panel-title">';
						echo $event->db_event['event_name'];
						echo '</div></div>'."\n";
						echo '<div class="panel-body">'."\n";
						
						$rankings = $event->round_voting_stats_all();
						$sum_votes = $rankings[0];
						$max_votes = $rankings[1];
						$stats_all = $rankings[2];
						$option_id_to_rank = $rankings[3];
						$confirmed_votes = $rankings[4];
						$unconfirmed_votes = $rankings[5];
						$confirmed_score = $rankings[6];
						$unconfirmed_score = $rankings[7];
						$destroy_score = $rankings[8];
						$unconfirmed_destroy_score = $rankings[9];
						$effective_destroy_score = $rankings[10];
						$unconfirmed_effective_destroy_score = $rankings[11];
						
						$sum_score = $confirmed_score+$unconfirmed_score;
						
						$total_bets = floor($sum_score*$coins_per_vote) + $destroy_score;
						$total_effective_bets = floor($sum_votes*$coins_per_vote) + $effective_destroy_score + $unconfirmed_effective_destroy_score;
						
						if ((string)$event->db_event['next_event_index'] != "") {
							$db_next_event = $game->fetch_event_by_index($event->db_event['next_event_index']);
							
							if ($db_next_event) {
								echo "<p>The winner advances to <a href=\"/explorer/games/".$game->db_game['url_identifier']."/events/".$db_next_event['event_index']."\">".$db_next_event['event_name']."</a></p>\n";
							}
						}
						
						$precursor_events = $app->run_query("SELECT * FROM events WHERE game_id=:game_id AND next_event_index=:next_event_index ORDER BY event_index ASC;", [
							'game_id' => $game->db_game['game_id'],
							'next_event_index' => $event->db_event['event_index']
						]);
						
						if ($precursor_events->rowCount() > 0) {
							echo "<p>".$precursor_events->rowCount()." ";
							if ($precursor_events->rowCount() == 1) echo $game->db_game['event_type_name'];
							else echo $game->db_game['event_type_name_plural'];
							echo " contributed to this ".$game->db_game['event_type_name'].".<br/>\n";
							while ($precursor_event = $precursor_events->fetch()) {
								echo "<a href=\"/explorer/games/".$game->db_game['url_identifier']."/events/".$precursor_event['event_index']."\">".$precursor_event['event_name']."</a><br/>\n";
							}
							echo "</p>";
						}
						
						if (empty($db_event['winning_option_id'])) {
							echo "<b>No Winner</b><br/>\n";
						}
						else {
							$winner_option = $app->fetch_option_by_id($db_event['winning_option_id']);
							echo "<b>Winner: ".$winner_option['name']."</b><br/>\n";
						}
						?>
						<div class="row">
							<div class="col-md-6">
								<div class="row">
									<div class="col-sm-4">Total bets:</div>
									<div class="col-sm-8"><font class="greentext"><?php echo $app->format_bignum($total_bets/pow(10,$game->db_game['decimal_places']))."</font> ".$game->db_game['coin_name_plural']; ?></div>
								</div>
							</div>
						</div>
						<?php
						if (!empty($db_event)) {
							$payout_amount = (int)($app->run_query("SELECT SUM(colored_amount) FROM transaction_game_ios WHERE event_id=:event_id AND is_coinbase=1;", [
								'event_id' => $event->db_event['event_id']
							])->fetch()['SUM(colored_amount)']);
							$payout_disp = $app->format_bignum($payout_amount/pow(10,$game->db_game['decimal_places']));
							echo '<font class="greentext">'.$payout_disp."</font> ";
							if ($payout_disp == '1') echo $game->db_game['coin_name']." was";
							else echo $game->db_game['coin_name_plural']." were";
							echo " paid out to the winners.<br/>\n";
						}
						
						echo "Blocks in this event: ";
						echo "<a href=\"/explorer/games/".$game->db_game['url_identifier']."/blocks/".$db_event['event_starting_block']."\">".$db_event['event_starting_block']."</a> ... <a href=\"/explorer/games/".$game->db_game['url_identifier']."/blocks/".$db_event['event_final_block']."\">".$db_event['event_final_block']."</a>";
						if ($db_event['event_payout_block'] != $db_event['event_final_block']) echo " ... <a href=\"/explorer/games/".$game->db_game['url_identifier']."/blocks/".$db_event['event_payout_block']."\">".$db_event['event_payout_block']."</a>";
						?>
						<br/>
						<?php
						$event_next_prev_links = $game->event_next_prev_links($event);
						echo $event_next_prev_links;
						?>
						<br/>
						<a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/events/">See all events</a><br/>
						<br/>
						
						<?php
						if ($app->user_can_edit_game($thisuser, $game)) {
							echo "<p>\n";
							
							if ($game->db_game['module'] == "CryptoDuels") {
								?>
								<button class="btn btn-sm btn-success" onclick="thisPageManager.refresh_prices_by_event(<?php echo $game->db_game['game_id'].", ".$event->db_event['event_id']; ?>);">Reset pricing info</button>
								<?php
							}
							?>
							<button class="btn btn-sm btn-primary" onclick="thisPageManager.set_event_outcome(<?php echo $game->db_game['game_id'].", ".$event->db_event['event_id']; ?>);">Set Outcome</button>
							<?php
							echo "</p>\n";
						}
						
						if ($game->db_game['module'] == "CoinBattles") {
							$chart_starting_block = $event->db_event['event_starting_block'];
							$chart_final_block = $event->db_event['event_final_block'];
							
							list($html, $js) = $game->module->currency_chart($game, $chart_starting_block, $chart_final_block);
							echo '<div style="margin: 20px 0px;" id="game0_chart_html">'.$html."</div>\n";
							echo '<div id="game0_chart_js"><script type="text/javascript">'.$js.'</script></div>'."\n";
						}
						
						$event_html = $event->event_html($thisuser, false, false, 0, 0);
						echo $event_html;
						
						if ($event->db_event['option_block_rule'] == "football_match") {
							echo "<br/><h2>Match Summary</h2>\n";
							
							$option_blocks = $app->run_query("SELECT * FROM option_blocks ob JOIN options o ON ob.option_id=o.option_id JOIN entities e ON o.entity_id=e.entity_id WHERE o.event_id=:event_id AND ob.score > 0 ORDER BY ob.option_block_id ASC;", ['event_id' => $event->db_event['event_id']]);
							$scores_by_entity_id = [];
							$entities_by_id = [];
							
							if ($option_blocks->rowCount() > 0) {
								while ($option_block = $option_blocks->fetch()) {
									if (empty($scores_by_entity_id[$option_block['entity_id']])) {
										$scores_by_entity_id[$option_block['entity_id']] = $option_block['score'];
										$entities_by_id[$option_block['entity_id']] = $option_block;
									}
									else $scores_by_entity_id[$option_block['entity_id']] += $option_block['score'];
									
									echo $option_block['entity_name']." scored in block #".$option_block['block_height']."<br/>\n";
								}
							}
							else echo "No one has scored.<br/>\n";
							
							if (!empty($scores_by_entity_id)) {
								echo "<br/><b>Final Score:</b><br/>\n";
								$winning_entity_id = false;
								foreach ($scores_by_entity_id as $entity_id => $score) {
									echo $entities_by_id[$entity_id]['entity_name'].": ".$score."<br/>\n";
								}
							}
							
							$options_by_event = $app->run_query("SELECT *, SUM(ob.score) AS score FROM option_blocks ob JOIN options o ON ob.option_id=o.option_id LEFT JOIN entities e ON o.entity_id=e.entity_id WHERE o.event_id=:event_id GROUP BY o.option_id ORDER BY o.option_index ASC;", ['event_id' => $event->db_event['event_id']]);
							
							if ($options_by_event->rowCount() > 0) {
								$first_option = $options_by_event->fetch();
								$second_option = $options_by_event->fetch();
								$winning_option = false;
								
								if ($first_option['score'] == $second_option['score']) {
									$tiebreaker = $game->module->break_tie($game, $event->db_event, $first_option, $second_option);
									
									if ($tiebreaker) {
										list($winning_option, $pk_shootout_data) = $tiebreaker;
										
										for ($i=0; $i<count($pk_shootout_data); $i++) {
											echo "<br/><b>PK Shootout #".($i+1)."</b><br/>\n";
											echo $first_option['entity_name'].": ".$pk_shootout_data[$i][0]."<br/>\n";
											echo $second_option['entity_name'].": ".$pk_shootout_data[$i][1]."<br/>\n";
										}
									}
								}
							}
						}
						
						$event_tx_count = 0;
						$event_tx_r = $blockchain->transactions_by_event($event->db_event['event_id']);
						$event_tx_count += $event_tx_r->rowCount();
						?>
						<br/>
						<h2>Transactions (<?php echo number_format($event_tx_count); ?>)</h2>
						<div class="transaction_table">
						<?php
						if ($event_tx_r->rowCount() > 0) {
							while ($transaction = $event_tx_r->fetch()) {
								echo $game->render_transaction($transaction, false, false, $coins_per_vote, $last_block_id);
							}
						}
						echo '</div>';
						
						echo "<br/>\n";
						echo $event_next_prev_links;
						
						echo "</div>\n";
					}
					else {
						echo '<div class="panel-heading"><div class="panel-title">';
						echo $game->db_game['name'].' Results';
						echo '</div></div>'."\n";
						?>
						<div class="panel-body">
							<div style="border-bottom: 1px solid #bbb; margin-bottom: 5px;" id="render_event_outcomes">
								<div id="event_outcomes_0">
									<?php
									$events_to_block_id = $game->db_game['events_until_block'];
									if ($events_to_block_id > $game->blockchain->last_block_id()) $events_to_block_id = $game->blockchain->last_block_id();
									
									$db_latest_event = $app->run_query("SELECT * FROM events WHERE game_id=:game_id AND event_starting_block <= :events_to_block ORDER BY event_index DESC LIMIT 1;", [
										'game_id' => $game->db_game['game_id'],
										'events_to_block' => $events_to_block_id
									])->fetch();
									
									if ($db_latest_event) {
										$to_event_index = $db_latest_event['event_index'];
										$from_event_index = max(0, $to_event_index-50);
										$event_outcomes = $game->event_outcomes_html($from_event_index, $to_event_index, $thisuser);
										echo $event_outcomes[1];
									}
									?>
								</div>
							</div>
							<center>
								<a href="" onclick="thisPageManager.show_more_event_outcomes(<?php echo $game->db_game['game_id']; ?>); return false;" id="show_more_link">Show More</a>
							</center>
						</div>
						
						<script type="text/javascript">
						thisPageManager.last_event_index_shown = <?php echo $from_event_index ?? 'false'; ?>;
						</script>
						<?php
					}
					?>
					<div style="display: none;" class="modal fade" id="set_event_outcome_modal">
						<div class="modal-dialog">
							<div class="modal-content" id="set_event_outcome_modal_content">
							</div>
						</div>
					</div>
					<?php
				}
				else if ($explore_mode == "blocks" || $explore_mode == "unconfirmed") {
					if ($block || $explore_mode == "unconfirmed") {
						if ($block) {
							if ($game) {
								$round_id = $game->block_to_round($block['block_id']);
								$block_index = $game->block_id_to_round_index($block['block_id']);
								$block_sum_disp = $block['sum_coins_in']/pow(10,$game->db_game['decimal_places']);
							}
							else {
								$block_sum_disp = $block['sum_coins_out']/pow(10,$blockchain->db_blockchain['decimal_places']);
							}
							
							echo '<div class="panel-heading"><div class="panel-title">';
							if ($game) echo $game->db_game['name']." block #".$block['block_id'];
							else echo $blockchain->db_blockchain['blockchain_name']." block #".$block['block_id'];
							echo '</div></div>'."\n";
							
							echo '<div class="panel-body">';
							
							if (!empty($block['block_hash'])) {
								echo "Block hash: ".$block['block_hash']."<br/>\n";
							}
							
							if (!empty($block['time_mined'])) {
								echo "Mined at ".date("Y-m-d H:m:s", $block['time_mined'])." UTC (".$app->format_seconds(time()-$block['time_mined'])." ago)<br/>\n";
							}
							
							if (!empty($block['num_transactions'])) {
								echo "This block contains ".number_format($block['num_transactions'])." transactions totaling ".$app->format_bignum($block_sum_disp)." ";
								if ($game) echo $game->db_game['coin_name_plural'];
								else echo $blockchain->db_blockchain['coin_name_plural'];
								echo ".<br/>\n";
							}
							
							if ($block['locally_saved'] == 0) {
								if ($block['block_id'] < $blockchain->db_blockchain['first_required_block']) {
									echo "This block is before the first required block for ".$blockchain->db_blockchain['blockchain_name'].". ";
									echo AppSettings::getParam('coin_brand_name')." has not fully loaded this block.\n";
								}
								else {
									if (!empty($block['num_transactions'])) {
										$load_time = $app->run_query("SELECT SUM(load_time) FROM transactions WHERE blockchain_id=:blockchain_id AND block_id=:block_id;", [
											'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
											'block_id' => $block['block_id']
										])->fetch()['SUM(load_time)'];
										
										echo "Still loading... ".number_format($load_time, 2)." seconds elapsed.\n";
									}
									else {
										echo AppSettings::getParam('coin_brand_name')." hasn't loaded this block yet.\n";
									}
								}
								echo "<br/>\n";
							}
							else {
								$load_time = $block['load_time'];
								if ($game) $load_time += $block['game_load_time'];
								
								echo AppSettings::getParam('coin_brand_name')." took ".number_format($load_time, 2)." seconds to load this block.<br/>\n";
							}
							
							if (empty($game)) {
								$associated_games = $blockchain->associated_games(array("running"));
								if (count($associated_games) > 0) {
									echo "<p>";
									for ($i=0; $i<count($associated_games); $i++) {
										echo "See block #".$block['block_id']." on <a href=\"/explorer/games/".$associated_games[$i]->db_game['url_identifier']."/blocks/".$block['block_id']."\">".$associated_games[$i]->db_game['name']."</a><br/>\n";
									}
									echo "</p>\n";
								}
							}
							
							if ($game) echo '<p><a href="/explorer/games/'.$game->db_game['url_identifier'].'/blocks/">&larr; All Blocks</a></p>';
							else echo '<p><a href="/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/blocks/">&larr; All Blocks</a></p>';
							
							if ($game) {
								$filter_arr = false;
								$events = $game->events_by_block($block['block_id'], $filter_arr);
								
								if (count($events) <= 50) {
									echo "<p>This block is referenced in ".count($events)." events<br/>\n";
									for ($i=0; $i<count($events); $i++) {
										echo "<a href=\"/explorer/games/".$game->db_game['url_identifier']."/events/".$events[$i]->db_event['event_index']."\">".$events[$i]->db_event['event_name']."</a><br/>\n";
									}
									echo '</p>';
								}
							}
						}
						else {
							if ($game) {
								$block_sum = (int)($app->run_query("SELECT SUM(gio.colored_amount) FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE t.blockchain_id=:blockchain_id AND t.block_id IS NULL;", [
									'blockchain_id' => $blockchain->db_blockchain['blockchain_id']
								])->fetch(PDO::FETCH_NUM)[0]);
								
								$block_sum_disp = $block_sum/pow(10,$game->db_game['decimal_places']);
								
								$num_trans = $app->run_query("SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE t.blockchain_id=:blockchain_id AND t.block_id IS NULL GROUP BY t.transaction_id;", [
									'blockchain_id' => $blockchain->db_blockchain['blockchain_id']
								])->rowCount();
							}
							else {
								$info = $app->run_query("SELECT COUNT(*), SUM(amount) FROM transactions WHERE blockchain_id=:blockchain_id AND block_id IS NULL;", [
									'blockchain_id' => $blockchain->db_blockchain['blockchain_id']
								])->fetch(PDO::FETCH_NUM);
								
								$num_trans = $info[0];
								$block_sum = $info[1];
								$block_sum_disp = $block_sum/pow(10,$blockchain->db_blockchain['decimal_places']);
							}
							
							$expected_block_id = $blockchain->last_block_id()+1;
							if ($game) {
								$expected_round_id = $game->block_to_round($expected_block_id);
								$expected_block_index = $game->block_id_to_round_index($expected_block_id);
							}
							
							echo '<div class="panel-heading"><div class="panel-title">';
							if ($game) echo $game->db_game['name'];
							else echo $blockchain->db_blockchain['blockchain_name'];
							echo ': Unconfirmed Transactions</div></div>'."\n";
							
							echo '<div class="panel-body">';
							
							echo $num_trans." transaction";
							if ($num_trans == 1) echo " is";
							else echo "s are";
							echo " awaiting confirmation with a sum of ".number_format($block_sum_disp, 2)." ";
							if ($game) echo $game->db_game['coin_name_plural'];
							else echo $blockchain->db_blockchain['coin_name_plural'];
							echo ".<br/>\n";
							echo "Block #".$expected_block_id." is currently being mined.<br/>\n";
						}
						
						if ($game) $next_prev_links = $game->block_next_prev_links($block, $explore_mode);
						else $next_prev_links = $blockchain->block_next_prev_links($block, $explore_mode);
						
						echo $next_prev_links;
						
						echo '<div style="margin-top: 10px; border-bottom: 1px solid #bbb;">';
						
						if ($game) {
							$relevant_tx_params = [
								'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
								'game_id' => $game->db_game['game_id']
							];
							$relevant_tx_q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE t.blockchain_id=:blockchain_id AND t.block_id";
							if ($explore_mode == "unconfirmed") $relevant_tx_q .= " IS NULL";
							else {
								$relevant_tx_q .= "=:block_id";
								$relevant_tx_params['block_id'] = $block['block_id'];
							}
							$relevant_tx_q .= " AND gio.game_id=:game_id GROUP BY t.transaction_id ORDER BY t.position_in_block ASC;";
							$relevant_transactions = $app->run_query($relevant_tx_q, $relevant_tx_params);
							
							while ($transaction = $relevant_transactions->fetch()) {
								echo $game->render_transaction($transaction, false, false, $coins_per_vote, $last_block_id);
							}
						}
						else {
							echo $blockchain->render_transactions_in_block($block, $explore_mode == "unconfirmed");
						}
						
						echo '</div>';
						echo "<br/>\n";
						
						echo $next_prev_links;
						?>
						<br/><br/>
						<?php
						echo "</div>\n";
					}
					else {
						$blocks_per_section = 40;
						$last_block_id = $blockchain->last_block_id();
						if ($game) $last_block_id = $game->last_block_id();
						$complete_block_id = $blockchain->last_complete_block_id();
						if ($game) $complete_block_id = $last_block_id;
						
						$filter_complete = false;
						if (!empty($_REQUEST['block_filter']) && $_REQUEST['block_filter'] == "complete") {
							$to_block_id = $complete_block_id+1;
							$filter_complete = true;
						}
						else $to_block_id = $last_block_id;
						if ($to_block_id === false) $to_block_id = 0;
						
						$from_block_id = $to_block_id-$blocks_per_section+1;
						if ($from_block_id < 0) $from_block_id = 0;
						?>
						<script type="text/javascript">
						thisPageManager.explorer_blocks_per_section = <?php echo $blocks_per_section; ?>;
						thisPageManager.explorer_block_list_sections = 1;
						thisPageManager.explorer_block_list_from_block = <?php echo $from_block_id; ?>;
						thisPageManager.filter_complete = <?php if ($filter_complete) echo "1"; else echo "0"; ?>;
						</script>
						<?php
						if ($game) {
							echo '<div class="panel-heading"><div class="panel-title">';
							echo $game->db_game['name']." Blocks";
							echo "</div></div>\n";
							
							echo '<div class="panel-body">';
							
							echo '<div id="explorer_block_list" style="margin-bottom: 15px;">';
							echo '<div id="explorer_block_list_0">';
							echo $game->explorer_block_list($from_block_id, $to_block_id, false);
							echo '</div>';
							echo '</div>';
							
							echo '<a href="" onclick="thisPageManager.explorer_block_list_show_more(); return false;">Show More</a>';
							
							echo '</div>';
						}
						else {
							$recent_block = $blockchain->most_recently_loaded_block();
							
							echo '<div class="panel-heading"><div class="panel-title">';
							echo $blockchain->db_blockchain['blockchain_name']." Blocks";
							echo "</div></div>\n";
							
							echo '<div class="panel-body">';
							
							echo "<p>".$blockchain->db_blockchain['blockchain_name']." is synced from block <a href=\"/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/blocks/".$blockchain->db_blockchain['first_required_block']."\">#".$blockchain->db_blockchain['first_required_block']."</a> to block <a href=\"/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/blocks/".$complete_block_id."\">#".$complete_block_id."</a></p>\n";
							
							if (!empty($recent_block)) {
								echo "<p>Last block loaded was <a href=\"/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/blocks/".$recent_block['block_id']."\">#".$recent_block['block_id']."</a> (loaded ".$app->format_seconds(time()-$recent_block['time_loaded'])." ago)</p>\n";
							}
							
							$pending_blocks_params = [
								'blockchain_id' => $blockchain->db_blockchain['blockchain_id']
							];
							$pending_blocks_q = "SELECT COUNT(*) FROM blocks WHERE blockchain_id=:blockchain_id AND locally_saved=0";
							if (!empty($blockchain->db_blockchain['first_required_block'])) {
								$pending_blocks_q .= " AND block_id > :first_required_block";
								$pending_blocks_params['first_required_block'] = $blockchain->db_blockchain['first_required_block'];
							}
							$pending_blocks = (int)($app->run_query($pending_blocks_q, $pending_blocks_params)->fetch()['COUNT(*)']);
							
							if ($pending_blocks > 0) {
								$loadtime = $app->run_query("SELECT COUNT(*), SUM(load_time) FROM blocks WHERE blockchain_id=:blockchain_id AND locally_saved=1 AND block_id >= :ref_block;", [
									'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
									'ref_block' => $complete_block_id-10
								])->fetch();
								
								if ($loadtime['COUNT(*)'] > 0) $avg_loadtime = $loadtime['SUM(load_time)']/$loadtime['COUNT(*)'];
								else $avg_loadtime = 0;
								
								$sec_left = round($avg_loadtime*$pending_blocks);
								echo "<p>".number_format($pending_blocks)." blocks haven't loaded yet (".$app->format_seconds($sec_left)." left)</p>\n";
							}
							
							$associated_games = $blockchain->associated_games(array("running"));
							if (count($associated_games) > 0) {
								echo "<p>";
								echo count($associated_games)." game";
								if (count($associated_games) == 1) echo " is";
								else echo "s are";
								echo " currently running on this blockchain.<br/>\n";
								
								for ($i=0; $i<count($associated_games); $i++) {
									echo "<a href=\"/explorer/games/".$associated_games[$i]->db_game['url_identifier']."/events/\">".$associated_games[$i]->db_game['name']."</a><br/>\n";
								}
								echo "</p>\n";
							}
							?>
							<div class="row">
								<div class="col-sm-6">
									<p>
										<select class="form-control" name="block_filter" onchange="window.location='/<?php echo $uri_parts[1]."/".$uri_parts[2]."/".$uri_parts[3]."/".$uri_parts[4]; ?>/?block_filter='+$(this).val();">
											<option value="">All blocks</option>
											<option <?php if ($filter_complete) echo 'selected="selected" '; ?>value="complete">Fully loaded blocks only</option>
										</select>
									</p>
								</div>
							</div>
							<div id="explorer_block_list" style="margin-bottom: 15px;">
								<div id="explorer_block_list_0">
									<?php
									$ref_game = false;
									echo $blockchain->explorer_block_list($from_block_id, $to_block_id, $ref_game, $filter_complete);
									?>
								</div>
							</div>
							<a href="" onclick="thisPageManager.explorer_block_list_show_more(); return false;">Show More</a>
							<br/>
							<?php
							echo '</div>';
						}
					}
				}
				else if ($explore_mode == "addresses") {
					echo '<div class="panel-heading"><div class="panel-title">';
					if ($game) echo $game->db_game['name'];
					else echo $blockchain->db_blockchain['blockchain_name'];
					echo " Address: ".$address['address'];
					echo "</div></div>\n";
					
					echo '<div class="panel-body">';
					
					echo '<img style="margin: 10px;" src="/render_qr_code.php?data='.$address['address'].'" />';
					
					if ($thisuser) {
						$account = $app->fetch_account_by_user_and_address($thisuser->db_user['user_id'], $address['address_id']);
						
						if ($account) {
							echo '<p>This address is in your account <a href="/accounts/?account_id='.$account['account_id'].'">'.$account['account_name'].'</a></p>';
						}
					}
					
					if (empty($game)) {
						$address_associated_games = $blockchain->games_by_address($address);
						echo "<p>This address is associated with ".count($address_associated_games)." game";
						if (count($address_associated_games) != 1) echo "s";
						echo ".</p>\n";
						
						for ($i=0; $i<count($address_associated_games); $i++) {
							$db_game = $address_associated_games[$i];
							echo '<p><a href="/explorer/games/'.$db_game['url_identifier'].'/addresses/'.$address['address'].'/">'.$db_game['name']."</a></p>\n";
						}
					}
					else {
						$associated_events = $app->run_query("SELECT * FROM events ev JOIN options op ON ev.event_id=op.event_id WHERE ev.game_id=:game_id AND op.option_index=:option_index;", [
							'game_id' => $game->db_game['game_id'],
							'option_index' => $address['option_index']
						]);
						
						if ($associated_events->rowCount() > 0) {
							echo "<p>This is a staking address for ";
							
							while ($associated_event = $associated_events->fetch()) {
								echo '<a href="/explorer/games/'.$game->db_game['url_identifier'].'/events/'.$associated_event['event_index'].'">'.$associated_event['event_name']."</a><br/>\n";
							}
							echo "</p>";
						}
					}
					
					$transaction_ids = [];
					$transaction_ios = [];
					
					$transactions_by_address = $app->run_query("SELECT * FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.create_transaction_id WHERE t.blockchain_id=:blockchain_id AND i.address_id=:address_id GROUP BY t.transaction_id ORDER BY t.transaction_id ASC;", [
						'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
						'address_id' => $address['address_id']
					]);
					
					while ($transaction_io = $transactions_by_address->fetch()) {
						array_push($transaction_ids, $transaction_io['transaction_id']);
						array_push($transaction_ios, $transaction_io);
					}
					
					$transactions_by_address = $app->run_query("SELECT * FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.spend_transaction_id WHERE t.blockchain_id=:blockchain_id AND i.address_id=:address_id GROUP BY t.transaction_id ORDER BY t.transaction_id ASC;", [
						'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
						'address_id' => $address['address_id']
					]);
					
					while ($transaction_io = $transactions_by_address->fetch()) {
						if (!in_array($transaction_io['transaction_id'], $transaction_ids)) {
							array_push($transaction_ids, $transaction_io['transaction_id']);
							array_push($transaction_ios, $transaction_io);
						}
					}
					
					echo "<p>This address has been used in ".count($transaction_ios)." transactions.</p>\n";
					
					echo "<p>Identifier: ".$address['vote_identifier']." (#".$address['option_index'].")</p>\n";
					
					if ($address['is_destroy_address'] == 1) {
						echo "<p>This is a destroy address.";
						if ($game) echo " Any ".$game->db_game['coin_name_plural']." sent to this address will be destroyed.";
						echo "</p>\n";
					}
					if ($address['is_separator_address'] == 1) {
						echo "<p>This is a separator address.</p>\n";
					}
					
					echo "<p>".ucwords($blockchain->db_blockchain['coin_name'])." balance: ".$app->format_bignum($blockchain->address_balance_at_block($address, false)/pow(10,$blockchain->db_blockchain['decimal_places']))." ".$blockchain->db_blockchain['coin_name_plural']."</p>\n";
					
					if ($game) echo "<p>".ucwords($game->db_game['coin_name'])." balance: ".$app->format_bignum($game->address_balance_at_block($address, false)/pow(10,$game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']."</p>\n";
					
					?>
					<div style="border-bottom: 1px solid #bbb;">
						<?php
						for ($i=0; $i<count($transaction_ios); $i++) {
							if ($game) echo $game->render_transaction($transaction_ios[$i], $address['address_id'], false, $coins_per_vote, $last_block_id);
							else echo $blockchain->render_transaction($transaction_ios[$i], $address['address_id'], false);
						}
						?>
					</div>
					
					<br/>
					<?php
					$permission_to_claim_address = $app->permission_to_claim_address($thisuser, $blockchain, $address);
					
					if ($permission_to_claim_address) {
						if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "claim") {
							?>
							<script type="text/javascript">
							window.onload = function() {
								thisPageManager.try_claim_address(<?php
								echo $blockchain->db_blockchain['blockchain_id'].", ";
								if ($game) echo $game->db_game['game_id'];
								else echo "false";
								echo ", ";
								echo $address['address_id'];
								?>);
							};
							</script>
							<?php
						}
						?>
						<button class="btn btn-success btn-sm" onclick="thisPageManager.try_claim_address(<?php
						echo $blockchain->db_blockchain['blockchain_id'].", ";
						if ($game) echo $game->db_game['game_id'];
						else echo "false";
						echo ", ";
						echo $address['address_id'];
						?>);">Claim this address</button>
						<?php
					}
					echo "</div>\n";
				}
				else if ($explore_mode == "initial") {
					$initial_block_transactions = $app->run_query("SELECT * FROM transactions WHERE blockchain_id=:blockchain_id AND block_id=0 AND amount > 0 ORDER BY transaction_id ASC;", [
						'blockchain_id' => $blockchain->db_blockchain['blockchain_id']
					]);
					
					echo '<div class="panel-body">'."\n";
					echo '<div style="border-bottom: 1px solid #bbb;">'."\n";
					while ($transaction = $initial_block_transactions->fetch()) {
						echo $blockchain->render_transaction($transaction, false, false);
					}
					echo '</div>'."\n";
					echo '</div>'."\n";
				}
				else if ($explore_mode == "transactions") {
					echo '<div class="panel-heading"><div class="panel-title">';
					echo $blockchain->db_blockchain['blockchain_name']." Transaction: ".$transaction['tx_hash'];
					echo "</div></div>\n";
					
					echo '<div class="panel-body">';
					
					if (empty($game)) {
						$tx_associated_games = $blockchain->games_by_transaction($transaction);
						echo "<p>This transaction is associated with ".count($tx_associated_games)." game";
						if (count($tx_associated_games) != 1) echo "s";
						echo ".</p>\n";
						
						for ($i=0; $i<count($tx_associated_games); $i++) {
							$db_game = $tx_associated_games[$i];
							echo '<p><a href="/explorer/games/'.$db_game['url_identifier'].'/transactions/'.$transaction['tx_hash'].'/">'.$db_game['name']."</a></p>\n";
						}
					}
					
					if ($game && $transaction['block_id'] > 0) {
						$block_index = $game->block_id_to_round_index($transaction['block_id']);
						$round_id = $game->block_to_round($transaction['block_id']);
					}
					else {
						$block_index = false;
						$round_id = false;
					}
					echo "This transaction has ".(int) $transaction['num_inputs']." inputs and ".(int) $transaction['num_outputs']." outputs totalling ".$app->format_bignum($transaction['amount']/pow(10,$blockchain->db_blockchain['decimal_places']))." ".$blockchain->db_blockchain['coin_name_plural'].".<br/>\n";
					if ($game) {
						$coins_per_votes = $app->coins_per_vote($game->db_game);
						$coins_in = $game->transaction_coins_in($transaction['transaction_id']);
						$coins_out = $game->transaction_coins_out($transaction['transaction_id'], true);
						$coins_diff = $coins_in-$coins_out;
						echo $app->format_bignum($coins_in/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']." in, ".$app->format_bignum($coins_out/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']." out";
						if ($coins_in > 0) echo " (".$app->format_bignum($coins_diff/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']." destroyed)";
						echo ".<br/>\n";
						
						$votes_in_params = [
							'votes_field' => "gio.".$game->db_game['payout_weight']."s_created",
							'transaction_id' => $transaction['transaction_id']
						];
						$votes_in = $app->run_query("SELECT SUM(:votes_field) votes_in FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE io.spend_transaction_id=:transaction_id;", $votes_in_params)->fetch();
						
						$votes_in_value = $votes_in['votes_in']*$coins_per_votes;
						
						echo "This transaction realizes ".$app->format_bignum($votes_in_value/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']." of unrealized gains.<br/>\n";
					}
					echo "Loaded in ".number_format($transaction['load_time'], 2)." seconds.";
					echo "<br/>\n";
					
					echo '<div style="margin-top: 10px; border-bottom: 1px solid #bbb;">';
					if ($game) echo $game->render_transaction($transaction, false, false, $coins_per_vote, $last_block_id);
					else echo $blockchain->render_transaction($transaction, false, false);
					echo "</div>\n";
					
					echo "</div>\n";
				}
				else if ($explore_mode == "utxo") {
					$create_tx = $app->fetch_transaction_by_id($io['create_transaction_id']);
					
					if (!empty($io['spend_transaction_id'])) {
						$spend_tx = $app->fetch_transaction_by_id($io['spend_transaction_id']);
					}
					else $spend_tx = false;
					
					echo '<div class="panel-heading"><div class="panel-title">';
					echo $pagetitle;
					echo "</div></div>\n";
					
					echo '<div class="panel-body">';
					
					if ($thisuser) {
						$account = $app->fetch_account_by_user_and_address($thisuser->db_user['user_id'], $io['address_id']);
						
						if ($account) {
							echo 'This UTXO is in your account <a href="/accounts/?account_id='.$account['account_id'].'">'.$account['account_name']."</a><br/>\n";
						}
					}
					
					echo 'This UTXO belongs to <a href="/explorer/';
					if ($game) echo 'games/'.$game->db_game['url_identifier'];
					else echo 'blockchains/'.$blockchain->db_blockchain['url_identifier'];
					echo '/addresses/'.$io['address'].'">'.$io['address']."</a><br/>\n";
					
					if ($game) {
						echo "Amount: &nbsp;&nbsp; ".$app->format_bignum($io['colored_amount']/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']."<br/>";
						echo "Status: &nbsp;&nbsp; ".ucwords($io['spend_status']);
						
						if ($io['is_resolved'] == 1) echo ", Resolved\n";
						else echo ", Unresolved\n";
						
						echo "<br/>\n";

						echo "This UTXO";
						if ($io['spend_status'] == "unconfirmed") echo " has not been confirmed yet";
						else echo " was created on block <a href=\"/explorer/games/".$game->db_game['url_identifier']."/blocks/".$io['create_block_id']."\">#".$io['create_block_id']."</a> (round #".$game->round_to_display_round($game->block_to_round($io['create_block_id'])).")";
						
						if ($io['spend_block_id'] > 0) {
							echo " and spent on block <a href=\"/explorer/games/".$game->db_game['url_identifier']."/blocks/".$io['spend_block_id']."\">#".$io['spend_block_id']."</a> (round #".$game->round_to_display_round($game->block_to_round($io['spend_block_id'])).")";
							
							if ($coins_per_vote > 0) {
								$votes_value = $io[$game->db_game['payout_weight']."s_created"]*$coins_per_vote;
								echo ".<br/>It added ".$app->format_bignum($votes_value/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']." to inflation.";
							}
						}
						else if ($io['spend_status'] != "unconfirmed" && $coins_per_vote > 0) {
							$blocks = ($last_block_id+1)-$io['create_block_id'];
							$rounds = $game->block_to_round($last_block_id+1)-$io['create_round_id'];
							if ($game->db_game['payout_weight'] == "coin_round") $votes = $rounds*$io['colored_amount'];
							else $votes = $blocks*$io['colored_amount'];
							$votes_value = floor($votes*$coins_per_vote);
							echo ".<br/>It currently holds ".$app->format_bignum($votes_value/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']." in unrealized gains.";
						}
						
						echo "<br/>\n";
					}
					
					if ($create_tx || $spend_tx) {
						if (empty($game)) {
							$tx_associated_games = $blockchain->games_by_io($io['io_id']);
							echo "<p>This UTXO is associated with ".count($tx_associated_games)." game";
							if (count($tx_associated_games) != 1) echo "s";
							echo ".</p>\n";
							
							for ($i=0; $i<count($tx_associated_games); $i++) {
								$db_game = $tx_associated_games[$i];
								$associated_game = new Game($blockchain, $db_game['game_id']);
								
								$game_ios = $associated_game->fetch_game_ios_by_io($io['io_id']);
								
								while ($game_io = $game_ios->fetch()) {
									echo '<p><a href="/explorer/games/'.$db_game['url_identifier'].'/utxo/'.$io['tx_hash'].'/'.$game_io['game_out_index'].'">'.$app->format_bignum($game_io['colored_amount']/pow(10,$db_game['decimal_places']))." ".$db_game['coin_name_plural']." in ".$db_game['name']."</a></p>\n";
								}
							}
						}
						
						echo '<div style="margin-top: 10px; border-bottom: 1px solid #bbb;">';
						if ($create_tx) {
							if ($game) echo $game->render_transaction($create_tx, false, $io['game_io_id'], $coins_per_vote, $last_block_id);
							else echo $blockchain->render_transaction($create_tx, false, $io['io_id']);
						}
						if ($spend_tx) {
							if ($game) echo $game->render_transaction($spend_tx, false, $io['game_io_id'], $coins_per_vote, $last_block_id);
							else echo $blockchain->render_transaction($spend_tx, false, $io['io_id']);
						}
						echo "</div>\n";
					}
					
					echo "</div>\n";
				}
				else if ($explore_mode == "explorer_home") {
					$all_blockchains = $app->run_query("SELECT * FROM blockchains ORDER BY blockchain_name ASC;");
					
					echo '<div class="panel-heading"><div class="panel-title">';
					echo 'Blockchains ('.$all_blockchains->rowCount().')';
					echo "</div></div>\n";
					
					echo '<div class="panel-body">';
					
					while ($blockchain = $all_blockchains->fetch()) {
						echo '<a href="/explorer/blockchains/'.$blockchain['url_identifier'].'/blocks/">'.$blockchain['blockchain_name'].'</a><br/>'."\n";
					}
					
					$running_games = $app->run_query("SELECT * FROM games WHERE game_status IN ('running','published','completed') AND featured=1 ORDER BY game_status ASC;");
					$game_id_csv = "";
					$section = "";
					while ($db_game = $running_games->fetch()) {
						if ($db_game['game_status'] == "completed") $this_section = "completed";
						else $this_section = "running";
						if ($section != $this_section) echo "</div></div>\n<div class=\"panel panel-default\">\n<div class=\"panel-heading\"><div class=\"panel-title\">".ucwords($this_section)." Games</div></div>\n<div class=\"panel-body\">\n";
						$section = $this_section;
						echo '<a href="/explorer/games/'.$db_game['url_identifier'].'/events/">'.$db_game['name']."</a><br/>\n";
					}
					echo "</div>\n";
				}
				else if ($explore_mode == "utxos") {
					if ($game) {
						$mining_block_id = $game->blockchain->last_block_id()+1;
						$mining_round = $game->block_to_round($mining_block_id);
						
						echo '<div class="panel-heading"><div class="panel-title">';
						if ($account) echo "UTXOs in account: ".$account['account_name'];
						else echo "UTXOs in ".$game->db_game['name'];
						echo "</div></div>\n";
						
						echo '<div class="panel-body">';
						
						$utxo_params = [
							'game_id' => $game->db_game['game_id']
						];
						if ($account) {
							echo '<p><a href="/accounts/?account_id='.$account['account_id'].'">Manage this Account</a></p>';
							
							$utxo_q = "SELECT * FROM transactions t JOIN transaction_ios io ON io.create_transaction_id=t.transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id JOIN addresses a ON a.address_id=io.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE gio.game_id=:game_id AND io.spend_status IN ('unspent','unconfirmed') AND k.account_id=:account_id ORDER BY gio.colored_amount DESC;";
							
							$utxo_params['account_id'] = $account['account_id'];
						}
						else {
							$utxo_q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id JOIN addresses a ON a.address_id=io.address_id WHERE gio.game_id=:game_id AND gio.colored_amount > 0 AND io.spend_status IN ('unspent','unconfirmed') ORDER BY gio.colored_amount DESC;";
						}
						$utxo_r = $app->run_query($utxo_q, $utxo_params);
						
						while ($utxo = $utxo_r->fetch()) {
							if ($game->db_game['payout_weight'] == "coin") $votes = $utxo['colored_amount'];
							else if ($game->db_game['payout_weight'] == "coin_block") $votes = $utxo['colored_amount']*($mining_block_id-$utxo['create_block_id']);
							else if ($game->db_game['payout_weight'] == "coin_round") $votes = $utxo['colored_amount']*($mining_round-$utxo['create_round_id']);
							else $votes = 0;
							
							echo '<div class="row">';
							echo '<div class="col-sm-3"><a href="/explorer/games/'.$game->db_game['url_identifier'].'/utxo/'.$utxo['tx_hash']."/".$utxo['game_out_index'].'">'.$app->format_bignum($utxo['colored_amount']/pow(10,$game->db_game['decimal_places'])).' '.$game->db_game['coin_name_plural'].'</a></div>';
							echo '<div class="col-sm-3 greentext text-right">';
							
							if ($game->db_game['inflation'] == "exponential") {
								$coin_equiv = $votes*$coins_per_vote;
								echo "+".$app->format_bignum($coin_equiv/pow(10,$game->db_game['decimal_places'])).' '.$game->db_game['coin_abbreviation'];
							}
							else echo $app->format_bignum($votes).' votes';
							
							echo '</div>';
							
							echo '<div class="col-sm-3">'.$utxo['spend_status']."</div>\n";
							
							echo '<div class="col-sm-3"><a href="/explorer/games/'.$game->db_game['url_identifier'].'/addresses/'.$utxo['address'].'">'.$utxo['address']."</a></div>\n";
							echo '</div>';
						}
					}
					else {
						if ($account) {
							$display_utxos = $app->run_query("SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN addresses a ON a.address_id=io.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE io.blockchain_id=:blockchain_id AND io.spend_status IN ('unspent','unconfirmed') AND k.account_id=:account_id ORDER BY io.amount DESC;", [
								'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
								'account_id' => $account['account_id']
							]);
							
							echo '<div class="panel-heading"><div class="panel-title">';
							echo "Showing all ".$display_utxos->rowCount()." UTXOs for ".$account['account_name'];
							echo "</div></div>\n";
							
							echo '<div class="panel-body">';
							
							echo '<p><a href="/accounts/?account_id='.$account['account_id'].'">Manage this Account</a></p>';
							
							while ($utxo = $display_utxos->fetch()) {
								echo '<div class="row">';
								echo '<div class="col-sm-3"><a href="/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/utxo/'.$utxo['tx_hash']."/".$utxo['out_index'].'">'.$app->format_bignum($utxo['amount']/pow(10,$blockchain->db_blockchain['decimal_places'])).' '.$blockchain->db_blockchain['coin_name_plural'].'</a></div>';
								echo '<div class="col-sm-3">'.$utxo['spend_status']."</div>\n";
								echo '<div class="col-sm-3"><a href="/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/addresses/'.$utxo['address'].'">'.$utxo['address']."</a></div>\n";
								echo '</div>';
							}
						}
					}
					echo "</div>\n";
				}
				else if (!empty($game) && $explore_mode == "my_bets") {
					if ($thisuser) {
						if (!empty($_REQUEST['user_game_id'])) {
							$app->change_user_game($thisuser, $game, $_REQUEST['user_game_id']);
						}
						$user_game = $app->fetch_user_game($thisuser->db_user['user_id'], $game->db_game['game_id']);
					}
					else $user_game = false;
					
					if (empty($thisuser)) echo "<br/><br/>\n<p>You must be logged in to view this page. <a href=\"/wallet/".$game->db_game['url_identifier']."/\">Log in</a></p>\n";
					else if (!$user_game) echo "<br/><br/>\n<p>Invalid user game selected.</p>\n";
					else {
						$net_delta = 0;
						$net_stake = 0;
						$pending_stake = 0;
						$resolved_fees_paid = 0;
						$num_wins = 0;
						$num_losses = 0;
						$num_unresolved = 0;
						$num_refunded = 0;
						$unresolved_net_delta = 0;
						
						$current_round = $game->block_to_round(1+$last_block_id);
						
						$bet_table_headers = [];
						
						$bet_table_headers['linear'] = '
						<div class="row">
							<div class="col-sm-1 boldtext">Amt Paid</div>
							<div class="col-sm-2 boldtext">Option Purchased</div>
							<div class="col-sm-1 text-center boldtext">Range</div>
							<div class="col-sm-2 boldtext">Position Purchased</div>
							<div class="col-sm-3 boldtext">Asset Performance</div>
							<div class="col-sm-3 boldtext">Position Performance</div>
						</div>';
						
						$bet_table_headers['binary'] = '
						<div class="row">
							<div class="col-sm-1 boldtext text-center">Stake</div>
							<div class="col-sm-1 boldtext text-center">Payout</div>
							<div class="col-sm-1 text-center boldtext">Odds</div>
							<div class="col-sm-1 boldtext">Effectiveness</div>
							<div class="col-sm-2 boldtext text-center">Your Bet</div>
							<div class="col-sm-3 boldtext">Event</div>
							<div class="col-sm-3 boldtext">Outcome</div>
						</div>';
						
						$bet_tables = [
							'binary' => [
								'resolved' => '',
								'unresolved' => ''
							],
							'linear' => [
								'resolved' => '',
								'unresolved' => ''
							]
						];
						
						$my_bets_base_q = "SELECT gio.game_io_id, gio.colored_amount, gio.option_id, gio.is_coinbase, gio.is_resolved, gio.game_out_index, p.ref_block_id, p.ref_round_id, p.ref_coin_blocks, p.ref_coin_rounds, p.effectiveness_factor, p.effective_destroy_amount, p.destroy_amount, p.votes, p.".$game->db_game['payout_weight']."s_destroyed, p.game_io_id AS parent_game_io_id, io.spend_transaction_id, io.spend_status, ev.*, ev.effective_destroy_score AS sum_effective_destroy_score, et.vote_effectiveness_function, et.effectiveness_param1, o.effective_destroy_score AS option_effective_destroy_score, o.unconfirmed_effective_destroy_score, o.unconfirmed_votes, o.name AS option_name, o.event_option_index, o.entity_id, ev.destroy_score AS sum_destroy_score, p.votes, o.votes AS option_votes, t.tx_hash FROM addresses a JOIN address_keys ak ON a.address_id=ak.address_id JOIN currency_accounts ca ON ak.account_id=ca.account_id JOIN user_games ug ON ug.account_id=ca.account_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN transactions t ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id JOIN options o ON gio.option_id=o.option_id JOIN events ev ON o.event_id=ev.event_id LEFT JOIN event_types et ON ev.event_type_id=et.event_type_id LEFT JOIN transaction_game_ios p ON gio.parent_io_id=p.game_io_id WHERE gio.game_id=:game_id AND ug.user_game_id=:user_game_id AND gio.is_coinbase=1";
						
						$my_binary_bets = $app->run_query($my_bets_base_q." AND ev.payout_rule='binary' ORDER BY ev.event_index DESC, gio.game_io_index DESC;", [
							'game_id' => $game->db_game['game_id'],
							'user_game_id' => $user_game['user_game_id']
						]);
						$num_binary_bets = $my_binary_bets->rowCount();
						
						while ($bet = $my_binary_bets->fetch()) {
							$this_bet_html = $app->render_binary_bet($bet, $game, $coins_per_vote, $current_round, $net_delta, $net_stake, $pending_stake, $resolved_fees_paid, $num_wins, $num_losses, $num_unresolved, $num_refunded, 'div', $last_block_id);
							
							if (!empty($this_bet_html)) {
								$this_bet_html = '<div class="row">'.$this_bet_html."</div>\n";
								
								if (empty($bet['winning_option_id']) && $bet['outcome_index'] != -1) $bet_tables['binary']['unresolved'] .= $this_bet_html;
								else $bet_tables['binary']['resolved'] .= $this_bet_html;
							}
						}
						
						$my_linear_bets = $app->run_query($my_bets_base_q." AND ev.payout_rule='linear' ORDER BY ev.event_index DESC, gio.game_io_index DESC;", [
							'game_id' => $game->db_game['game_id'],
							'user_game_id' => $user_game['user_game_id']
						]);
						$num_linear_bets = $my_linear_bets->rowCount();
						$ref_html = "";
						
						while ($bet = $my_linear_bets->fetch()) {
							list($track_entity, $track_price_usd, $track_pay_price, $asset_price_usd, $bought_price_usd, $fair_io_value, $inflation_stake, $effective_stake, $unconfirmed_votes, $max_payout, $odds, $effective_paid, $equivalent_contracts, $event_equivalent_contracts, $track_position_price, $bought_leverage, $current_leverage, $borrow_delta, $bet_net_delta, $payout_fees) = $game->get_payout_info($bet, $coins_per_vote, $last_block_id, $ref_html);
							
							$this_bet_html = $app->render_linear_bet($bet, $game, $inflation_stake, $effective_paid, $current_leverage, $equivalent_contracts, $borrow_delta, $track_pay_price, $bought_price_usd, $fair_io_value, $bet_net_delta, $net_delta, $net_stake, $pending_stake, $resolved_fees_paid, $num_wins, $num_losses, $num_unresolved, $num_refunded, $unresolved_net_delta);
							
							if (!empty($this_bet_html)) {
								$this_bet_html = '<div class="row">'.$this_bet_html."</div>\n";
								
								if (empty($bet['winning_option_id']) && (string)$bet['track_payout_price'] == "" && $bet['outcome_index'] != -1) $bet_tables['linear']['unresolved'] .= $this_bet_html;
								else $bet_tables['linear']['resolved'] .= $this_bet_html;
							}
						}
						
						echo '<div class="panel-body">';
						?>
						<div id="change_user_game">
							<select id="select_user_game" class="form-control" onchange="thisPageManager.explorer_change_user_game();">
								<?php
								$my_user_games = $app->run_query("SELECT * FROM user_games WHERE user_id=:user_id AND game_id=:game_id;", [
									'user_id' => $thisuser->db_user['user_id'],
									'game_id' => $game->db_game['game_id']
								]);
								while ($db_user_game = $my_user_games->fetch()) {
									echo "<option ";
									if ($db_user_game['user_game_id'] == $user_game['user_game_id']) echo "selected=\"selected\" ";
									echo "value=\"".$db_user_game['user_game_id']."\">Account #".$db_user_game['account_id']." &nbsp;&nbsp; ".$app->format_bignum(($game->account_balance($db_user_game['account_id'])+$game->user_pending_bets($db_user_game))/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_abbreviation']."</option>\n";
								}
								?>
							</select>
						</div>
						<?php
						echo "<p>You've placed ".$app->bets_summary($game, $net_stake, $num_wins, $num_losses, $num_unresolved, $num_refunded, $pending_stake, $net_delta, $resolved_fees_paid);
						if ($unresolved_net_delta > 0) {
							echo "<br/>You're ";
							if ($unresolved_net_delta >= 0) echo 'up <font class="greentext">';
							else echo 'down <font class="redtext">';
							echo $app->format_bignum(abs($unresolved_net_delta)).' '.$game->db_game['coin_name_plural'];
							echo '</font> on your outstanding positions.';
						}
						
						$destroyed_coins = $game->destroyed_coins_by_account($user_game['account_id']);
						if ($destroyed_coins > 0) {
							echo "<br/>You've destroyed <font class=\"redtext\">".$app->format_bignum($destroyed_coins/pow(10, $game->db_game['decimal_places']))."</font> ".$game->db_game['coin_name_plural'];
						}
						echo "</p>\n";
						
						if (!empty($bet_tables['binary']['unresolved'])) {
							echo "<p><b>Outstanding Bets</b></p>\n";
							echo $bet_table_headers['binary'].$bet_tables['binary']['unresolved'];
							echo "<br/>\n";
						}
						
						if (!empty($bet_tables['linear']['unresolved'])) {
							echo "<p><b>Outstanding Positions</b></p>\n";
							echo $bet_table_headers['linear'].$bet_tables['linear']['unresolved'];
							echo "<br/>\n";
						}
						
						if (!empty($bet_tables['binary']['resolved'])) {
							echo "<p><b>Resolved Bets</b></p>\n";
							echo $bet_table_headers['binary'].$bet_tables['binary']['resolved'];
							echo "<br/>\n";
						}
						
						if (!empty($bet_tables['linear']['resolved'])) {
							echo "<p><b>Resolved Positions</b></p>\n";
							echo $bet_table_headers['linear'].$bet_tables['linear']['resolved'];
							echo "<br/>\n";
						}
						
						echo "</div>\n";
					}
				}
				else if ($explore_mode == "definition") {
					if ($game) {
						$definition_mode = "defined";
						$def_field = 'defined_cached_definition_hash';
						
						if (!empty($_REQUEST['definition_mode']) && $_REQUEST['definition_mode'] == "actual") {
							$definition_mode = "actual";
							$def_field = 'cached_definition_hash';
						}
						
						$game_def_str = $app->get_game_definition_by_hash($game->db_game[$def_field]);
						?>
						<div class="panel panel-info">
							<div class="panel-heading">
								<div class="panel-title">Game definition for <?php echo $game->db_game['name']; ?></div>
							</div>
							<div class="panel-body">
								<p>
									<a <?php if ($definition_mode == "defined") echo 'class="selected" '; ?>href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/definition/?definition_mode=defined">As Defined</a>
									 &nbsp;&nbsp; 
									<a <?php if ($definition_mode == "actual") echo 'class="selected" '; ?>href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/definition/?definition_mode=actual">Actual Game</a>
								</p>
								<div class="row">
									<div class="col-sm-2"><label class="form-control-static" for="definition_hash">Definition hash:</label></div>
									<div class="col-sm-10"><input type="text" class="form-control" id="definition_hash" value="<?php echo $game->db_game[$def_field]; ?>" /></div>
								</div>
								
								<textarea class="definition" id="definition"><?php echo $game_def_str; ?></textarea>
							</div>
						</div>
						<?php
					}
					else {
						$blockchain_def = $app->fetch_blockchain_definition($blockchain);
						$blockchain_def_str = $app->game_def_to_text($blockchain_def);
						$blockchain_def_hash = $app->game_def_to_hash($blockchain_def_str);
						?>
						<div class="panel panel-info">
							<div class="panel-heading">
								<div class="panel-title"><?php echo $blockchain->db_blockchain['blockchain_name']; ?> blockchain definition</div>
							</div>
							<div class="panel-body">
								<div class="row">
									<div class="col-sm-2"><label class="form-control-static" for="definition_hash">Definition hash:</label></div>
									<div class="col-sm-10"><input type="text" class="form-control" id="definition_hash" value="<?php echo $blockchain_def_hash; ?>" /></div>
								</div>
								
								<textarea class="definition" id="definition"><?php echo $blockchain_def_str; ?></textarea>
							</div>
						</div>
						<?php
					}
					?>
					<script type="text/javascript">
					window.onload = function() {
						$('#definition').dblclick(function() {
							$('#definition').focus().select();
						});
					}
					</script>
					<?php
				}
				?>
			</div>
		</div>
		<?php
		include(AppSettings::srcPath().'/includes/html_stop.php');
	}
}
else {
	Router::Send404();
}
?>
