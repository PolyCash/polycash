<?php
$skip_select_db = TRUE;
include("includes/connect.php");
include("includes/jsonRPCClient.php");

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	if ($GLOBALS['mysql_database'] != "") {
		if ($GLOBALS['mysql_database'] === mysql_real_escape_string($GLOBALS['mysql_database']) && $GLOBALS['mysql_database'] === strip_tags($GLOBALS['mysql_database'])) {
			$db_exists = false;

			$r = mysql_query("SHOW DATABASES;");
			while ($dbname = mysql_fetch_assoc($r)) {
				if ($dbname['Database'] == $GLOBALS['mysql_database']) $db_exists = true;
			}

			if (!$db_exists) {
				$r = mysql_query("CREATE DATABASE ".$GLOBALS['mysql_database']);
				
				$cmd = "mysql -u ".$GLOBALS['mysql_user']." -h ".$GLOBALS['mysql_server']." -p".$GLOBALS['mysql_password']." ".$GLOBALS['mysql_database']." < ".realpath(dirname(__FILE__))."/sql/schema-initial.sql";
				echo exec($cmd);
				
				mysql_select_db($GLOBALS['mysql_database']) or die ("There was an error accessing the \"".$GLOBALS['mysql_database']."\" database");
				
				$q = "INSERT INTO games SET game_type='real', block_timing='realistic', payout_weight='coin', seconds_per_block=120, name='EmpireCoin Live', num_voting_options=16, maturity=8, round_length=10, max_voting_fraction=0.25;";
				$r = run_query($q);
				$game_id = mysql_insert_id();
				
				ensure_game_nations($game_id);
				
				set_site_constant("primary_game_id", $game_id);
				set_site_constant("game_loop_seconds", 2);
			}
			else {
				mysql_select_db($GLOBALS['mysql_database']) or die ("There was an error accessing the \"".$GLOBALS['mysql_database']."\" database");
			}
			?>
			Great, the database was installed.<br/>
			If there was an error installing the database please use mysql to delete the database, then try again.<br/>
			<br/>
			Make sure this line has been added to your /etc/crontab:<br/>
			* * * * * root /usr/bin/php <?php echo realpath(dirname(__FILE__))."/cron/minutely.php ".$GLOBALS['cron_key_string']; ?><br/>
			<br/>
			Please run "a2enmod rewrite"<br/>
			Then make sure the line "AllowOverride All" is included in your apache configuration file (/etc/apache2/apache2.conf or /etc/httpd/httpd.conf)<br/>
			Example:
<pre>
	&lt;Directory <?php echo realpath(dirname(__FILE__)); ?>&gt;
		Options Indexes FollowSymLinks
		AllowOverride All
		Require all granted
	&lt;/Directory&gt;
</pre>
			<br/>
			<?php
			try {
				$empirecoin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');
				$getinfo = $empirecoin_rpc->getinfo();
				echo "Great, you're connected to EmpireCoin core.<br/>\n";
				echo "<pre>getinfo()\n";
				print_r($getinfo);
				echo "\n\ngetgenerate()\n";
				print_r($empirecoin_rpc->getgenerate());
				echo "</pre>";
				
				echo "Next, please run <a target=\"_blank\" href=\"/scripts/sync_empirecoind.php?key=".$GLOBALS['cron_key_string']."\">scripts/sync_empirecoind.php</a><br/>\n";
			}
			catch (Exception $e) {
				echo "Failed to establish a connection to EmpireCoin core, please check coin parameters in includes/config.php<br/>";
			}
			?>
			<a href="/">Check if installation was successful.</a>
			<?php
		}
		else echo "An invalid database name was specified in includes/config.php";
	}
	else {
		echo 'Please set the $GLOBALS[\'mysql_database\'] variable in includes/config.php';
	}
}
else {
	echo "Please provide the correct key.";
}
?>