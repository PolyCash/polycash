<?php
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $GLOBALS['pageview_controller']->insert_pageview($thisuser);

$uri = $_SERVER['REQUEST_URI'];
$uri_parts = explode("/", $uri);

if ($uri_parts[1] == "api") {
	if ($uri_parts[2] != "" && strval(intval($uri_parts[2])) === strval($uri_parts[2])) {
		$game_id = intval($uri_parts[2]);
		
		$q = "SELECT game_id, maturity, pos_reward, pow_reward, round_length, game_type, payout_weight, seconds_per_block, name, num_voting_options, max_voting_fraction FROM games WHERE game_id='".$game_id."';";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$db_game = $r->fetch();
			$game = new Game($app, $db_game['game_id']);
			$last_block_id = $game->last_block_id();
			$current_round = $game->block_to_round($last_block_id+1);
			
			$intval_vars = array('game_id','round_length','seconds_per_block','num_voting_options', 'maturity', 'last_block_id');
			for ($i=0; $i<count($intval_vars); $i++) {
				$game->db_game[$intval_vars[$i]] = intval($game->db_game[$intval_vars[$i]]);
			}
			$game->db_game['max_voting_fraction'] = floatval($game->db_game['max_voting_fraction']);
			
			if ($uri_parts[3] == "status") {
				$api_user = FALSE;
				$api_user_info = FALSE;
				
				if ($_REQUEST['api_access_code'] != "") {
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
						$mature_utxo_q = "SELECT * FROM transaction_ios i JOIN addresses a ON i.address_id=a.address_id WHERE i.spend_status='unspent' AND i.spend_transaction_id IS NULL AND a.user_id='".$api_user->db_user['user_id']."' AND i.game_id='".$game->db_game['game_id']."' AND (i.create_block_id <= ".($last_block_id-$game->db_game['maturity'])." OR i.instantly_mature = 1) ORDER BY i.io_id ASC;";
						$mature_utxo_r = $app->run_query($mature_utxo_q);
						$utxo_i = 0;
						while ($utxo = $mature_utxo_r->fetch()) {
							$mature_utxos[$utxo_i] = array('utxo_id'=>intval($utxo['io_id']), 'coins'=>$utxo['amount'], 'create_block_id'=>intval($utxo['create_block_id']));
							$utxo_i++;
						}
						$api_user_info['my_utxos'] = $mature_utxos;
					}
				}
				$round_stats = $game->round_voting_stats_all($current_round);
				$total_vote_sum = $round_stats[0];
				$max_vote_sum = $round_stats[1];
				$ranked_stats = $round_stats[2];
				$option_id_to_rank = $round_stats[3];
				$confirmed_votes = $round_stats[4];
				$unconfirmed_votes = $round_stats[5];
				
				$output_game['last_block_id'] = $last_block_id;
				$output_game['current_round'] = $current_round;
				$output_game['confirmed_votes'] = $confirmed_votes;
				$output_game['unconfirmed_votes'] = $unconfirmed_votes;
				$output_game['block_within_round'] = $game->block_id_to_round_index($last_block_id+1);
				
				$game_scores = false;
				
				$qq = "SELECT * FROM game_voting_options WHERE game_id='".$game->db_game['game_id']."';";
				$rr = $app->run_query($qq);
				while ($option = $rr->fetch()) {
					$stat = $ranked_stats[$option_id_to_rank[$option['option_id']]];
					$api_stat = false;
					$api_stat['option_id'] = (int) $option['option_id'];
					$api_stat['name'] = $stat['name'];
					$api_stat['rank'] = $option_id_to_rank[$option['option_id']]+1;
					$api_stat['confirmed_votes'] = $app->friendly_intval($stat[$game->db_game['payout_weight'].'_score']);
					$api_stat['unconfirmed_votes'] = $app->friendly_intval($stat['unconfirmed_'.$game->db_game['payout_weight'].'_score']);
					
					$game_scores[$option['option_id']] = $api_stat;
				}
				
				$api_output = array('status_code'=>1, 'status_message'=>"Successful", 'game'=>$output_game, 'game_scores'=>$game_scores, 'user_info'=>$api_user_info);
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
		if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $GLOBALS['pageview_controller']->insert_pageview($thisuser);
		
		$pagetitle = $GLOBALS['coin_brand_name']." API Documentation";
		$nav_tab_selected = "home";
		include('includes/html_start.php');
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
				<a target="_blank" href="/api/<?php echo$app->get_site_constant('primary_game_id'); ?>/status/">/api/<?php echo$app->get_site_constant('primary_game_id'); ?>/status/</a> &nbsp;&nbsp;&nbsp; <a href="" onclick="$('#api_status_example').toggle('fast'); return false;">See Example</a><br/>
				Yields information about current status of the blockchain.
				<br/><br/>
			</p>
<pre id="api_status_example" style="display: none;">
{
   "status_code":1,
   "status_message":"Successful",
   "game":{
      "game_id":46,
      "maturity":5,
      "pos_reward":750000000000,
      "pow_reward":2500000000,
      "round_length":100,
      "game_type":"simulation",
      "payout_weight":"coin_block",
      "seconds_per_block":12,
      "name":"<?php echo $GLOBALS['coin_brand_name']; ?> Live",
      "num_voting_options":16,
      "max_voting_fraction":0.25,
      "last_block_id":7629,
      "current_round":77,
      "confirmed_votes":1339142421477498,
      "unconfirmed_votes":0,
      "block_within_round":30
   },
   "game_scores":[
      {
         "option_id":0,
         "name":"China",
         "rank":2,
         "confirmed_votes":343264178109758,
         "unconfirmed_votes":0
      },
      {
         "option_id":1,
         "name":"USA",
         "rank":1,
         "confirmed_votes":348585570317453,
         "unconfirmed_votes":0
      },
      {
         "option_id":2,
         "name":"India",
         "rank":4,
         "confirmed_votes":284247986344929,
         "unconfirmed_votes":0
      },
      {
         "option_id":3,
         "name":"Brazil",
         "rank":3,
         "confirmed_votes":333874393690120,
         "unconfirmed_votes":0
      },
      {
         "option_id":4,
         "name":"Indonesia",
         "rank":5,
         "confirmed_votes":29170293015238,
         "unconfirmed_votes":0
      },
      {
         "option_id":5,
         "name":"Japan",
         "rank":6,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":6,
         "name":"Russia",
         "rank":7,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":7,
         "name":"Germany",
         "rank":8,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":8,
         "name":"Mexico",
         "rank":9,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":9,
         "name":"Nigeria",
         "rank":10,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":10,
         "name":"France",
         "rank":11,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":11,
         "name":"UK",
         "rank":12,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":12,
         "name":"Pakistan",
         "rank":13,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":13,
         "name":"Italy",
         "rank":14,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":14,
         "name":"Turkey",
         "rank":15,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":15,
         "name":"Iran",
         "rank":16,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      }
   ],
   "user_info":false
}
</pre>
			<p>
				/api/status/?api_access_code=ACCESS_CODE_GOES_HERE &nbsp;&nbsp;&nbsp; <a href="" onclick="$('#api_status_user_example').toggle('fast'); return false;">See Example</a><br/>
				Supply your API access code to get relevant info on your user account in addition to general blockchain information.
				<br/><br/>
			</p>
<pre id="api_status_user_example" style="display: none;">
{
   "status_code":1,
   "status_message":"Successful",
   "game":{
      "game_id":30,
      "maturity":1,
      "pos_reward":100000000000,
      "pow_reward":2000000000,
      "round_length":50,
      "game_type":"simulation",
      "payout_weight":"coin_block",
      "seconds_per_block":6,
      "name":"<?php echo $GLOBALS['coin_brand_name']; ?> Testnet",
      "num_voting_options":16,
      "max_voting_fraction":0.25,
      "last_block_id":"8905",
      "current_round":179,
      "confirmed_votes":0,
      "unconfirmed_votes":0,
      "block_within_round":6
   },
   "game_scores":[
      {
         "option_id":0,
         "name":"China",
         "rank":1,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":1,
         "name":"USA",
         "rank":2,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":2,
         "name":"India",
         "rank":3,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":3,
         "name":"Brazil",
         "rank":4,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":4,
         "name":"Indonesia",
         "rank":5,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":5,
         "name":"Japan",
         "rank":6,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":6,
         "name":"Russia",
         "rank":7,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":7,
         "name":"Germany",
         "rank":8,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":8,
         "name":"Mexico",
         "rank":9,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":9,
         "name":"Nigeria",
         "rank":10,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":10,
         "name":"France",
         "rank":11,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":11,
         "name":"UK",
         "rank":12,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":12,
         "name":"Pakistan",
         "rank":13,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":13,
         "name":"Italy",
         "rank":14,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":14,
         "name":"Turkey",
         "rank":15,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      },
      {
         "option_id":15,
         "name":"Iran",
         "rank":16,
         "confirmed_votes":0,
         "unconfirmed_votes":0
      }
   ],
   "user_info":{
      "username":"freecoins",
      "balance":1219439580239,
      "mature_balance":1219439580239,
      "immature_balance":0,
      "votes_available":116797665503784,
      "my_utxos":[
         {
            "utxo_id":198973,
            "coins":373146560080,
            "create_block_id":8804
         },
         {
            "utxo_id":198974,
            "coins":373146560080,
            "create_block_id":8804
         },
         {
            "utxo_id":198991,
            "coins":215033553248,
            "create_block_id":8812
         },
         {
            "utxo_id":198992,
            "coins":158112906832,
            "create_block_id":8812
         },
         {
            "utxo_id":199059,
            "coins":34950683332,
            "create_block_id":8850
         },
         {
            "utxo_id":199060,
            "coins":65049316667,
            "create_block_id":8850
         }
      ]
   }
}
</pre>
			<p>
			API clients must return JSON formatted recommendations. 
			Any errors in your API response will result in your voting recommendations being ignored.<br/>
			Here's an example of a valid API response:
			</p>
<pre>
{
   "input_utxo_ids":[
      166193,
      166194,
      166195,
      166196,
      166197
   ],
   "recommendation_unit":"coin",
   "recommendations":[
      {
         "option_id":0,
         "name":"China",
         "recommended_amount":80000000000
      },
      {
         "option_id":1,
         "name":"USA",
         "recommended_amount":60000000000
      },
      {
         "option_id":2,
         "name":"India",
         "recommended_amount":40000000000
      },
      {
         "option_id":3,
         "name":"Brazil",
         "recommended_amount":20000000000
      }
   ]
}
</pre>
			<p>
				Recommendations can also be denominated in percentage points rather than satoshis:
			</p>
<pre>
{
   "input_utxo_ids":[
      166193,
      166194,
      166195,
      166196,
      166197
   ],
   "recommendation_unit":"coin",
   "recommendations":[
      {
         "option_id":0,
         "name":"China",
         "recommended_amount":40
      },
      {
         "option_id":1,
         "name":"USA",
         "recommended_amount":30
      },
      {
         "option_id":2,
         "name":"India",
         "recommended_amount":20
      },
      {
         "option_id":3,
         "name":"Brazil",
         "recommended_amount":10
      }
   ]
}
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