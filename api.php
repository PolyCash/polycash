<?php
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

$uri = $_SERVER['REQUEST_URI'];
$uri_parts = explode("/", $uri);

if ($uri_parts[1] == "api") {
	if ($uri_parts[2] != "" && strval(intval($uri_parts[2])) === strval($uri_parts[2])) {
		$game_id = intval($uri_parts[2]);
	
		$q = "SELECT game_id, maturity, pos_reward, pow_reward, round_length, game_type, payout_weight, seconds_per_block, name, num_voting_options, max_voting_fraction FROM games WHERE game_id='".$game_id."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) == 1) {
			$game = mysql_fetch_array($r, MYSQL_ASSOC);
			$last_block_id = last_block_id($game['game_id']);
			$current_round = block_to_round($game, $last_block_id+1);
			
			$intval_vars = array('game_id','pos_reward','pow_reward','round_length','seconds_per_block','num_voting_options');
			for ($i=0; $i<count($intval_vars); $i++) {
				$game[$intval_vars[$i]] = intval($game[$intval_vars[$i]]);
			}
			$game['max_voting_fraction'] = floatval($game['max_voting_fraction']);
			
			if ($uri_parts[3] == "status") {
				$api_user = FALSE;
				$api_user_info = FALSE;
				
				if ($_REQUEST['api_access_code'] != "") {
					$q = "SELECT * FROM users WHERE api_access_code='".mysql_real_escape_string($_REQUEST['api_access_code'])."';";
					$r = run_query($q);
					
					if (mysql_numrows($r) == 1) {
						$api_user = mysql_fetch_array($r);
						
						$account_value = account_coin_value($game, $api_user);
						$immature_balance = immature_balance($game, $api_user);
						$mature_balance = $account_value - $immature_balance;
						
						$api_user_info['username'] = $api_user['username'];
						$api_user_info['balance'] = $account_value;
						$api_user_info['mature_balance'] = $mature_balance;
						$api_user_info['immature_balance'] = $immature_balance;
						
						$mature_utxos = array();
						$mature_utxo_q = "SELECT * FROM transaction_IOs i JOIN addresses a ON i.address_id=a.address_id WHERE i.spend_status='unspent' AND i.spend_transaction_id IS NULL AND a.user_id='".$api_user['user_id']."' AND i.game_id='".$game['game_id']."' AND (i.create_block_id <= ".($last_block_id-$game['maturity'])." OR i.instantly_mature = 1) ORDER BY i.io_id ASC;";
						$mature_utxo_r = run_query($mature_utxo_q);
						$utxo_i = 0;
						while ($utxo = mysql_fetch_array($mature_utxo_r)) {
							$mature_utxos[$utxo_i] = array('utxo_id'=>intval($utxo['io_id']), 'coins'=>intval($utxo['amount']), 'create_block_id'=>intval($utxo['create_block_id']));
							$utxo_i++;
						}
						$api_user_info['my_utxos'] = $mature_utxos;
					}
				}
				$round_stats = round_voting_stats_all($game, $current_round);
				$total_vote_sum = $round_stats[0];
				$max_vote_sum = $round_stats[1];
				$ranked_stats = $round_stats[2];
				$nation_id_to_rank = $round_stats[3];
				
				$game['last_block_id'] = $last_block_id;
				$game['current_round'] = $current_round;
				$game['current_votes'] = $total_vote_sum;
				$game['block_within_round'] = block_id_to_round_index($game, $last_block_id+1);
				
				$game_scores = false;
				
				for ($nation_id=0; $nation_id < 16; $nation_id++) {
					$stat = $ranked_stats[$nation_id_to_rank[$nation_id+1]];
					$api_stat = false;
					$api_stat['empire_id'] = $nation_id;
					$api_stat['empire_name'] = $stat['name'];
					$api_stat['rank'] = $nation_id_to_rank[$nation_id+1]+1;
					$api_stat['votes'] = $stat['coins_currently_voted'];
					
					$game_scores[$nation_id] = $api_stat;
				}
				
				$api_output = array('status_code'=>1, 'status_message'=>"Successful", 'game'=>$game, 'game_scores'=>$game_scores, 'user_info'=>$api_user_info);
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
		if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);
		
		$pagetitle = "EmpireCoin API Documentation";
		$nav_tab_selected = "home";
		include('includes/html_start.php');
		?>
		<div class="container" style="max-width: 1000px;">
			<h1>EmpireCoin API Documentation</h1>
			
			EmpireCoin web wallets provide several strategies for automating your EmpireCoin voting behavior.  However, some users may wish to use custom logic in their voting strategies. The EmpireCoin API allows this functionality through a standardized format for sharing EmpireCoin voting recommendations. Using the EmpireCoin API can be as simple as finding a public recommendations URL and plugging it into your EmpireCoin user account.  Or you can set up your own voting recommendations client using the information below.<br/>
			<br/>
			<a target="_blank" href="/api/<?php echo get_site_constant('primary_game_id'); ?>/status/">/api/<?php echo get_site_constant('primary_game_id'); ?>/status/</a> &nbsp;&nbsp;&nbsp; <a href="" onclick="$('#api_status_example').toggle('fast'); return false;">See Example</a><br/>
			Yields information about current status of the blockchain.
			
<pre id="api_status_example" style="display: none;">
{
   "status":{
      "last_block_id":"208",
      "current_voting_round":21,
      "total_coins_voted":1732612284738,
      "empires":[
         {
            "empire_id":0,
            "empire_name":"China",
            "rank":13,
            "coins_voted":0
         },
         {
            "empire_id":1,
            "empire_name":"USA",
            "rank":14,
            "coins_voted":0
         },
         {
            "empire_id":2,
            "empire_name":"India",
            "rank":15,
            "coins_voted":0
         },
         {
            "empire_id":3,
            "empire_name":"Japan",
            "rank":1,
            "coins_voted":"945158139395"
         },
         {
            "empire_id":4,
            "empire_name":"Germany",
            "rank":16,
            "coins_voted":0
         },
         {
            "empire_id":5,
            "empire_name":"Russia",
            "rank":2,
            "coins_voted":"787454145343"
         },
         {
            "empire_id":6,
            "empire_name":"Brazil",
            "rank":3,
            "coins_voted":0
         },
         {
            "empire_id":7,
            "empire_name":"Indonesia",
            "rank":4,
            "coins_voted":0
         },
         {
            "empire_id":8,
            "empire_name":"France",
            "rank":5,
            "coins_voted":0
         },
         {
            "empire_id":9,
            "empire_name":"UK",
            "rank":6,
            "coins_voted":0
         },
         {
            "empire_id":10,
            "empire_name":"Mexico",
            "rank":7,
            "coins_voted":0
         },
         {
            "empire_id":11,
            "empire_name":"Italy",
            "rank":8,
            "coins_voted":0
         },
         {
            "empire_id":12,
            "empire_name":"South Korea",
            "rank":9,
            "coins_voted":0
         },
         {
            "empire_id":13,
            "empire_name":"Saudi Arabia",
            "rank":10,
            "coins_voted":0
         },
         {
            "empire_id":14,
            "empire_name":"Canada",
            "rank":11,
            "coins_voted":0
         },
         {
            "empire_id":15,
            "empire_name":"Spain",
            "rank":12,
            "coins_voted":0
         }
      ]
   },
   "user":false
}
</pre>
			<br/><br/>
			
			/api/status/?api_access_code=ACCESS_CODE_GOES_HERE &nbsp;&nbsp;&nbsp; <a href="" onclick="$('#api_status_user_example').toggle('fast'); return false;">See Example</a><br/>
			Supply your API access code to get relevant info on your user account in addition to general blockchain information.

<pre id="api_status_user_example" style="display: none;">
{
   "status":{
      "last_block_id":"209",
      "current_voting_round":21,
      "total_coins_voted":1732612284738,
      "empires":[
         {
            "empire_id":0,
            "empire_name":"China",
            "rank":13,
            "coins_voted":0
         },
         {
            "empire_id":1,
            "empire_name":"USA",
            "rank":14,
            "coins_voted":0
         },
         {
            "empire_id":2,
            "empire_name":"India",
            "rank":15,
            "coins_voted":0
         },
         {
            "empire_id":3,
            "empire_name":"Japan",
            "rank":1,
            "coins_voted":"945158139395"
         },
         {
            "empire_id":4,
            "empire_name":"Germany",
            "rank":16,
            "coins_voted":0
         },
         {
            "empire_id":5,
            "empire_name":"Russia",
            "rank":2,
            "coins_voted":"787454145343"
         },
         {
            "empire_id":6,
            "empire_name":"Brazil",
            "rank":3,
            "coins_voted":0
         },
         {
            "empire_id":7,
            "empire_name":"Indonesia",
            "rank":4,
            "coins_voted":0
         },
         {
            "empire_id":8,
            "empire_name":"France",
            "rank":5,
            "coins_voted":0
         },
         {
            "empire_id":9,
            "empire_name":"UK",
            "rank":6,
            "coins_voted":0
         },
         {
            "empire_id":10,
            "empire_name":"Mexico",
            "rank":7,
            "coins_voted":0
         },
         {
            "empire_id":11,
            "empire_name":"Italy",
            "rank":8,
            "coins_voted":0
         },
         {
            "empire_id":12,
            "empire_name":"South Korea",
            "rank":9,
            "coins_voted":0
         },
         {
            "empire_id":13,
            "empire_name":"Saudi Arabia",
            "rank":10,
            "coins_voted":0
         },
         {
            "empire_id":14,
            "empire_name":"Canada",
            "rank":11,
            "coins_voted":0
         },
         {
            "empire_id":15,
            "empire_name":"Spain",
            "rank":12,
            "coins_voted":0
         }
      ]
   },
   "user":{
      "username":"apitester@gmail.com",
      "balance":415407988104,
      "mature_balance":100000000000,
      "immature_balance":315407988104
   }
}
</pre>
			<br/><br/>
			
			Example voting recommendations API client.&nbsp;&nbsp;<a href="/api/download-client-example/">Click here to download (PHP)</a>
			<br/><br/>
			
			API clients must return JSON formatted recommendations. 
			Any errors in your API response such as invalid formatting, empire IDs which are not between 0 and 15, or amounts which are greater than your mature balance will cause your recommendations to be ignored.<br/>
			Here's an example of a valid API response:<br/>
<pre>
{
  "recommendation_unit" : "coin",
  "recommendations" :
    [
      {
        "empire_id" : 12,
        "empire_name" : "South Korea",
        "recommended_amount" : 8500000000
      },
      {
        "empire_id" : 14,
        "empire_name" : "Canada",
        "recommended_amount" : 3500000000
      }
    ]
}
</pre>
			Recommendations can also be denominated in percentage points rather than satoshis:<br/>
<pre>
{
  "recommendation_unit" : "percent",
  "recommendations" :
    [
      {
        "empire_id" : 12,
        "empire_name" : "South Korea",
        "recommended_amount" : 75
      },
      {
        "empire_id" : 14,
        "empire_name" : "Canada",
        "recommended_amount" : 25
      }
    ]
}
</pre>
			<br/><br/>
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
		header('Content-disposition: attachment; filename="EmpirecoinAPIClient.php"');
		header('Cache-Control: public, must-revalidate, max-age=0');
		header('Pragma: public');
		header('Content-Length: '.strlen($raw));
		header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
		echo $raw;
	}
	else {
		echo json_encode(array('status_code'=>0, 'status_message'=>"You've reached an invalid URL."));
	}
}
?>