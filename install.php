<?php
ini_set('memory_limit', '1024M');
$skip_select_db = TRUE;
include("includes/connect.php");

if ($app->running_as_admin()) {
	if ($GLOBALS['mysql_database'] != "") {
		if (strpos($GLOBALS['mysql_database'], "'") === false && $GLOBALS['mysql_database'] === strip_tags($GLOBALS['mysql_database'])) {
			$db_exists = false;

			$r = $app->run_query("SHOW DATABASES;");
			while ($dbname = $r->fetch()) {
				if ($dbname['Database'] == $GLOBALS['mysql_database']) $db_exists = true;
			}
			
			if (strpos($GLOBALS['mysql_password'], "'") !== false) {
				echo "Your mysql password includes an apostrophe. ".$GLOBALS['site_name']." may not be able to install or complete migrations to your DB.<br/>\n";
			}
			
			if (!$db_exists) {
				$r = $app->run_query("CREATE DATABASE ".$GLOBALS['mysql_database']);
				$app->set_db($GLOBALS['mysql_database']);
				
				$cmd = $app->mysql_binary_location()." -u ".$GLOBALS['mysql_user']." -h ".$GLOBALS['mysql_server'];
				if ($GLOBALS['mysql_password'] != "") $cmd .= " -p'".$GLOBALS['mysql_password']."'";
				$cmd .= " ".$GLOBALS['mysql_database']." < ".realpath(dirname(__FILE__))."/sql/schema-initial.sql";
				echo exec($cmd);
			}
			else {
				$app->set_db($GLOBALS['mysql_database']);
			}
			$result = $app->run_query("SHOW TABLES;");
			$table_exists = $result->rowCount() > 0;
			if (!$table_exists) {
				echo "Database tables failed to be created, please install manually by importing all files in the \"sql\" folder via phpMyAdmin or any other MySQL interface.<br/>\n";
				die();
			}
			
			$app->update_schema();
			
			if (!isset($GLOBALS['identifier_case_sensitive'])) die('Please set the variable $GLOBALS[\'identifier_case_sensitive\'] in your config file.');
			if (!isset($GLOBALS['identifier_first_char'])) die('Please set the variable $GLOBALS[\'identifier_first_char\'] in your config file.');
			
			$q = "SELECT * FROM currency_prices WHERE currency_id=1 AND reference_currency_id=1;";
			$r = $app->run_query($q);
			
			if ($r->rowCount() == 0) {
				$q = "INSERT INTO currency_prices SET currency_id=1, reference_currency_id=1, price=1, time_added='".time()."';";
				$r = $app->run_query($q);
			}
			
			$app->set_site_constant("event_loop_seconds", 2);
			$app->set_site_constant("reference_currency_id", 1);
			
			if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "save_blockchain_params") {
				$blockchain_id = (int) $_REQUEST['blockchain_id'];
				$q = "SELECT * FROM blockchains WHERE blockchain_id=".$app->quote_escape($blockchain_id).";";
				$r = $app->run_query($q);
				
				if ($r->rowCount() == 1) {
					$temp_blockchain = $r->fetch();
					$rpc_host = $_REQUEST['rpc_host'];
					$rpc_username = $_REQUEST['rpc_username'];
					$rpc_password = $_REQUEST['rpc_password'];
					$rpc_port = (int) $_REQUEST['rpc_port'];
					$r = $app->run_query("UPDATE blockchains SET rpc_host=".$app->quote_escape($rpc_host).", rpc_username=".$app->quote_escape($rpc_username).", rpc_password=".$app->quote_escape($rpc_password).", rpc_port=".$app->quote_escape($rpc_port)." WHERE blockchain_id=".$temp_blockchain['blockchain_id'].";");
				}
				else die("Error, please manually save RPC parameters in the database.");
			}
			
			$app->blockchain_ensure_currencies();
			$general_entity_type = $app->check_set_entity_type("general entity");
			
			include("includes/get_session.php");
			
			$pagetitle = $GLOBALS['site_name']." - Installing...";
			$include_crypto_js = TRUE;
			$nav_tab_selected = "install";
			include("includes/html_start.php");
			?>
			<div class="container-fluid">
				<h2>Install the MySQL database</h1>
				Great, the database was installed.<br/>
				If there was an error installing the database please use mysql to delete the database, then try again.<br/>
				<?php
				if (empty($thisuser)) {
					$redirect_url = $app->get_redirect_url($_SERVER['REQUEST_URI']);
					$redirect_key = $redirect_url['redirect_key'];
					include("includes/html_login.php");
				}
				else {
					if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "install_module") {
						$module_name = $_REQUEST['module_name'];
						
						$q = "SELECT * FROM games WHERE module=".$app->quote_escape($module_name).";";
						$r = $app->run_query($q);
						
						echo "<br/><b>Installing module $module_name</b><br/>\n";
						if ($r->rowCount() > 0) {
							$db_game = $r->fetch();
							echo "<p>This module is already installed.</p>\n";
						}
						else {
							eval('$game_def = new '.$module_name.'GameDefinition($app);');
							
							$blockchain = false;
							$db_blockchain = $app->fetch_blockchain_by_identifier($game_def->game_def->blockchain_identifier);
							
							if ($db_blockchain) {
								$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
								$new_game_def_txt = $app->game_def_to_text($game_def->game_def);
								
								$error_message = "";
								$db_game = false;
								$new_game = $app->set_game_from_definition($new_game_def_txt, $thisuser, $module_name, $error_message, $db_game, false);
								
								if (!empty($new_game)) {
									if ($new_game->blockchain->db_blockchain['p2p_mode'] == "none") {
										if ($thisuser) {
											$user_game = $thisuser->ensure_user_in_game($new_game, false);
										}
										$log_text = "";
										$new_game->blockchain->new_block($log_text);
										$transaction_id = $new_game->add_genesis_transaction($user_game);
										if ($transaction_id < 0) $error_message = "Failed to add genesis transaction (".$transaction_id.").";
									}
									$new_game->blockchain->unset_first_required_block();
									$new_game->start_game();
									
									$ensure_block_id = $new_game->db_game['game_starting_block'];
									if ($new_game->db_game['finite_events'] == 1) $ensure_block_id = max($ensure_block_id, $new_game->max_gde_starting_block());
									$new_game->ensure_events_until_block($ensure_block_id);
								}
								else if (empty($error_message)) $error_message = "Error: failed to create the game.";
								
								if (!empty($error_message)) {
									if (is_string($error_message)) echo $error_message."<br/>\n";
									else echo "<pre>".json_encode($error_message, JSON_PRETTY_PRINT)."</pre>\n";
								}
								
								echo "<p>Next please <a href=\"/scripts/load_game_reset.php?key=".$GLOBALS['cron_key_string']."&game_id=".$new_game->db_game['game_id']."\">reset this game</a></p>\n";
							}
							else echo "<p>Failed to find the blockchain.</p>\n";
						}
					}
					?>
					<h2>Run <?php echo $GLOBALS['site_name']; ?></h1>
					Make sure this line has been added to your /etc/crontab:<br/>
<pre>
* * * * * root <?php echo $app->php_binary_location(); ?> <?php echo str_replace("\\", "/", realpath(dirname(__FILE__)))."/cron/minutely.php"; ?>
</pre>
					If you can't use cron, please run this app in a new tab or run the command below.<br/>
					<a class="btn btn-success" target="_blank" href="cron/minutely.php?key=<?php echo $GLOBALS['cron_key_string']; ?>">Start process in a new tab</a>
					<br/>
					<pre><?php echo $app->php_binary_location(); ?> <?php echo str_replace("\\", "/", realpath(dirname(__FILE__))."/scripts/main.php"); ?></pre>
					
					<h2>Configure Apache for symlinked URLs</h1>
					Please run this command:<br/>
					<pre>a2enmod rewrite</pre>
					
					Then enter "AllowOverride All" in your apache configuration file (/etc/apache2/apache2.conf or /etc/httpd/httpd.conf or /etc/httpd/conf/httpd.conf)<br/>
					Example:
<pre>
&lt;Directory <?php echo realpath(dirname(__FILE__)); ?>&gt;
	Options FollowSymLinks
	AllowOverride All
	Require all granted
&lt;/Directory&gt;
</pre>
					
					<h2>Configure Bitcoin for accepting payments</h1>
					<script type="text/javascript">
					function generate_keypair() {
						$('#keypair_details').slideDown('fast');

						var rsa = new RSAKey();
						var e = '10001';
						rsa.generate(1024, e);
					  
						n_value = rsa.n.toString(16);
						d_value = rsa.d.toString(16);
						p_value = rsa.p.toString(16);
						q_value = rsa.q.toString(16);
						dmp1_value = rsa.dmp1.toString(16);
						dmq1_value = rsa.dmq1.toString(16);
						coeff_value = rsa.coeff.toString(16);

						$('#pub_key_disp').val(n_value);
						$('#priv_key_disp').val(d_value+':'+p_value+':'+q_value+':'+dmp1_value+':'+dmq1_value+':'+coeff_value);
						$('#pub_key_config_line').html("$GLOBALS['rsa_keyholder_email'] = 'myname@myemailprovider.com';\n$GLOBALS['rsa_pub_key'] = '"+n_value+"';");
					}
					</script>
					<?php
					if (!empty($GLOBALS['rsa_pub_key']) && !empty($GLOBALS['bitcoin_port']) && !empty($GLOBALS['bitcoin_rpc_user']) && !empty($GLOBALS['bitcoin_rpc_password'])) { ?>
						Great, it looks like you've already configured an RSA key for accepting Bitcoin payments.
						<br/>
						<?php
					}
					else {
						if (empty($GLOBALS['rsa_pub_key'])) { ?>
							You have not yet specified an RSA keypair for accepting Bitcoin payments.<br/>
							To allow private event_types to accept Bitcoin payments, please generate an RSA key pair.<br/>
							<button class="btn btn-primary" onclick="generate_keypair();">Generate RSA Keypair</button>
							<br/>
							<div id="keypair_details" style="display: none; border: 1px solid #aaa; padding: 10px; margin-top: 10px;">
								<b>A new RSA keypair has just been generated.</b><br/>
								<br/>
								This is your <font class="greentext">public key</font>. Copy and save your public key into includes/config.php.
								<input type="text" id="pub_key_disp" class="form-control" /><br/>
								This is your <font class="redtext">private key</font>. Save it somewhere safe.
								<input type="text" id="priv_key_disp" class="form-control" />
								<br/>
								Add your public key into includes/config.php like this:<br/>
								<pre id="pub_key_config_line"></pre>
								But replace 'myname@myemailprovider.com' with an email address.  This email address will not be shown to anyone but will receive an email prompting you to enter your private key whenever a event that you administer finishes.<br/>
								<br/>
								After saving your public key in includes/config.php, save your private key somewhere safe. Your public key can be derived from your private key. Next <a href="" onclick="window.location=window.location;">click here</a> to reload this page.<br/>
								<br/>
								If you lose or leak your private key, all escrowed bitcoins on this site will be irrevocably lost.<br/>
							
							</div>
							<br/>
							<?php
						}
					}
					?>
					
					<h2>Install Blockchains</h2>
					<?php
					$blockchain_r = $app->run_query("SELECT * FROM blockchains ORDER BY blockchain_name ASC;");
					
					while ($db_blockchain = $blockchain_r->fetch()) {
						$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
						
						if ($db_blockchain['p2p_mode'] == "rpc") {
							if ($db_blockchain['rpc_username'] != "" && $db_blockchain['rpc_password'] != "") $tried_rpc = true;
							else $tried_rpc = false;
							
							if ($tried_rpc) {
								echo '<div id="display_rpc_'.$db_blockchain['blockchain_id'].'">';
								echo "<b>Connecting RPC client to ".$db_blockchain['blockchain_name']."...";
								
								$blockchain->load_coin_rpc();
								$getblockchaininfo = false;
								
								if ($blockchain->coin_rpc) {
									try {
										$getblockchaininfo = $blockchain->coin_rpc->getblockchaininfo();
									}
									catch (Exception $e) {}
								}
								
								if (!$getblockchaininfo) {
									echo " <font class=\"redtext\">Failed to connect on port ".$db_blockchain['rpc_port']."</font></b><br/>";
									echo "<pre>Make sure the coin daemon is running.</pre>\n";
									echo "<br/>\n";
								}
								else {
									echo " <font class=\"greentext\">Connected on port ".$db_blockchain['rpc_port']."</font></b><br/>\n";
									echo "<pre style=\"max-height: 300px; overflow-y: scroll;\">getblockchaininfo()\n";
									print_r($getblockchaininfo);
									echo "</pre>";
									
									echo "Next, please reset and synchronize this game.<br/>\n";
									echo "<a class=\"btn btn-primary\" target=\"_blank\" href=\"/scripts/sync_blockchain_initial.php?key=".$GLOBALS['cron_key_string']."&blockchain_id=".$db_blockchain['blockchain_id']."\">Reset & synchronize ".$db_blockchain['blockchain_name']."</a>\n";
									echo "<br/>\n";
								}
								echo "<a href=\"\" onclick=\"$('#display_rpc_".$db_blockchain['blockchain_id']."').hide(); $('#edit_rpc_".$db_blockchain['blockchain_id']."').show('fast'); return false;\">Set new RPC params for ".$db_blockchain['blockchain_name']."</a>\n";
								echo "</div>\n";
							}
							?>
							<div id="edit_rpc_<?php echo $db_blockchain['blockchain_id']; ?>"<?php if ($tried_rpc) echo ' style="display: none;"'; ?>>
								Please enter the RPC username and password for connecting to the <b><?php echo $db_blockchain['blockchain_name']; ?></b> daemon:<br/>
								<form method="post" action="install.php">
									<input type="hidden" name="key" value="<?php echo $GLOBALS['cron_key_string']; ?>" />
									<input type="hidden" name="action" value="save_blockchain_params" />
									<input type="hidden" name="blockchain_id" value="<?php echo $db_blockchain['blockchain_id']; ?>" />
									<input class="form-control" name="rpc_host" placeholder="RPC hostname (default 127.0.0.1)" />
									<input class="form-control" name="rpc_username" placeholder="RPC username" />
									<input class="form-control" name="rpc_password" placeholder="RPC password" autocomplete="off" />
									<input class="form-control" name="rpc_port" value="<?php echo $db_blockchain['default_rpc_port']; ?>" placeholder="RPC port" />
									<input type="submit" class="btn btn-primary" value="Save" />
									<?php if ($tried_rpc) echo ' &nbsp;&nbsp; or &nbsp;&nbsp; <a href="" onclick="$(\'#display_rpc_'.$db_blockchain['blockchain_id'].'\').show(\'fast\'); $(\'#edit_rpc_'.$db_blockchain['blockchain_id'].'\').hide(); return false;">Cancel</a>'; ?>
								</form>
								<br/>
							</div>
							<br/>
							<?php
						}
						else {
							echo "<p><h3>".$db_blockchain['blockchain_name']."</h3>\n";
							echo "<a target=\"_blank\" href=\"/scripts/sync_blockchain_initial.php?key=".$GLOBALS['cron_key_string']."&blockchain_id=".$db_blockchain['blockchain_id']."\">Reset & synchronize ".$db_blockchain['blockchain_name']."</a></p>\n";
						}
					}
					
					?>
					<h2>Modules</h2>
					<?php
					$installed_module_r = $app->run_query("SELECT * FROM modules m JOIN games g ON m.primary_game_id=g.game_id;");
					if ($installed_module_r->rowCount() > 0) {
						while ($installed_module = $installed_module_r->fetch()) {
							echo '<a href="/'.$installed_module['url_identifier'].'/">'.$installed_module['name']."</a> is already installed.<br/>\n";
						}
						echo "<br/>\n";
					}
					
					$open_module_r = $app->run_query("SELECT * FROM modules WHERE primary_game_id IS NULL;");
					$module_html = '<option value="">-- Select a module to install --</option>';
					while ($open_module = $open_module_r->fetch()) {
						$module_html .= '<option value="'.$open_module['module_name'].'">'.$open_module['module_name']."</option>\n";
					}
					
					echo '<select class="form-control" id="select_install_module" onchange="start_install_module(\''.$GLOBALS['cron_key_string'].'\');">'.$module_html."</select>\n";
					?>
					<br/>
					<br/>
					<?php
				}
				?>
			</div>
			<?php
			include("includes/html_stop.php");
		}
		else echo "An invalid database name was specified in includes/config.php\n";
	}
	else {
		echo 'Please set the $GLOBALS[\'mysql_database\'] variable in includes/config.php'."\n";
	}
}
else {
	echo 'Please set the correct value for "key" in the URL.<br/>';
	echo 'To find the correct key value, open includes/config.php and look for $GLOBALS[\'cron_key_string\'].'."\n";
}
?>
