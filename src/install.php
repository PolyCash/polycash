<?php
ini_set('memory_limit', '1024M');
$skip_select_db = TRUE;
require(AppSettings::srcPath()."/includes/connect.php");

if ($app->running_as_admin()) {
	if (AppSettings::getParam('mysql_database') != "") {
		if (strpos(AppSettings::getParam('mysql_database'), "'") === false && AppSettings::getParam('mysql_database') === strip_tags(AppSettings::getParam('mysql_database'))) {
			$db_exists = false;

			$list_of_dbs = $app->run_query("SHOW DATABASES;");
			while ($dbname = $list_of_dbs->fetch()) {
				if ($dbname['Database'] == AppSettings::getParam('mysql_database')) $db_exists = true;
			}
			
			if (strpos(AppSettings::getParam('mysql_password'), "'") !== false) {
				echo "Your mysql password includes an apostrophe. ".AppSettings::getParam('site_name')." may not be able to install or complete migrations to your DB.<br/>\n";
			}
			
			if ($db_exists) {
				$app->select_db(AppSettings::getParam('mysql_database'));
			}
			else {
				$app->run_query("CREATE DATABASE ".AppSettings::getParam('mysql_database'));
				$app->select_db(AppSettings::getParam('mysql_database'));
				
				$cmd = $app->mysql_binary_location()." -u ".AppSettings::getParam('mysql_user')." -h ".AppSettings::getParam('mysql_server');
				if (AppSettings::getParam('mysql_password') != "") $cmd .= " -p'".AppSettings::getParam('mysql_password')."'";
				$cmd .= " ".AppSettings::getParam('mysql_database')." < ".AppSettings::srcPath()."/sql/schema-initial.sql";
				echo exec($cmd);
			}
			$app->load_module_classes();
			
			$table_exists = $app->run_query("SHOW TABLES;")->rowCount() > 0;
			if (!$table_exists) {
				echo "Database tables failed to be created, please install manually by importing all files in the \"sql\" folder via phpMyAdmin or any other MySQL interface.<br/>\n";
				die();
			}
			
			$app->update_schema();
			
			if (empty(AppSettings::getParam('identifier_case_sensitive'))) die('Please set the variable "identifier_case_sensitive" in your config file.');
			if (empty(AppSettings::getParam('identifier_first_char'))) die('Please set the variable "identifier_first_char" in your config file.');
			if (empty($app->get_site_constant("reference_currency_id"))) $app->set_reference_currency(6);
			
			if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "save_blockchain_params") {
				$blockchain_id = (int) $_REQUEST['blockchain_id'];
				$existing_blockchain = $app->fetch_blockchain_by_id($blockchain_id);
				
				if ($existing_blockchain) {
					$app->run_query("UPDATE blockchains SET rpc_host=:rpc_host, rpc_username=:rpc_username, rpc_password=:rpc_password, rpc_port=:rpc_port, first_required_block=:first_required_block WHERE blockchain_id=:blockchain_id;", [
						'rpc_host' => $_REQUEST['rpc_host'],
						'rpc_username' => $_REQUEST['rpc_username'],
						'rpc_password' => $_REQUEST['rpc_password'],
						'rpc_port' => $_REQUEST['rpc_port'],
						'first_required_block' => $_REQUEST['first_required_block'],
						'blockchain_id' => $existing_blockchain['blockchain_id']
					]);
				}
				else die("Error, please manually save RPC parameters in the database.");
			}
			
			$app->blockchain_ensure_currencies();
			$general_entity_type = $app->check_set_entity_type("general entity");
			
			require(AppSettings::srcPath()."/includes/get_session.php");
			
			$pagetitle = AppSettings::getParam('site_name')." - Installing...";
			$nav_tab_selected = "install";
			include(AppSettings::srcPath()."/includes/html_start.php");
			?>
			<div class="container-fluid">
				<h2>Install the MySQL database</h1>
				Great, the database was installed.<br/>
				If there was an error installing the database please use mysql to delete the database, then try again.<br/>
				<?php
				if (empty($thisuser)) {
					$redirect_url = $app->get_redirect_url($_SERVER['REQUEST_URI']);
					$redirect_key = $redirect_url['redirect_key'];
					include(AppSettings::srcPath()."/includes/html_login.php");
				}
				else {
					if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "install_module") {
						$module_name = $_REQUEST['module_name'];
						$games_by_module = $app->run_query("SELECT * FROM games WHERE module=:module;", ['module' => $module_name]);
						
						echo "<br/><b>Installing module $module_name</b><br/>\n";
						
						if ($games_by_module->rowCount() > 0) {
							echo "<p>This module is already installed.</p>\n";
						}
						else {
							if ($existing_module = $app->check_module($module_name)) {
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
									
									echo "<p>Next please <a href=\"/scripts/load_game_reset.php?key=".AppSettings::getParam('operator_key')."&game_id=".$new_game->db_game['game_id']."\">reset this game</a></p>\n";
								}
								else echo "<p>Failed to find the blockchain.</p>\n";
							}
							else echo "<p>The module must already exist in DB before you can install it.</p>\n";
						}
					}
					?>
					<h2>Run <?php echo AppSettings::getParam('site_name'); ?></h1>
					Make sure this line has been added to your /etc/crontab:<br/>
<pre>
* * * * * root <?php echo $app->php_binary_location(); ?> <?php echo str_replace("\\", "/", AppSettings::srcPath())."/cron/minutely.php"; ?>
</pre>
					If you can't use cron, please run this app in a new tab or run the command below.<br/>
					<a class="btn btn-success" target="_blank" href="cron/minutely.php?key=<?php echo AppSettings::getParam('operator_key'); ?>">Start process in a new tab</a>
					<br/>
					<pre><?php echo $app->php_binary_location(); ?> <?php echo str_replace("\\", "/", AppSettings::srcPath()."/scripts/main.php"); ?></pre>
					
					<h2>Configure Apache for symlinked URLs</h1>
					Please run this command:<br/>
					<pre>a2enmod rewrite</pre>
					
					Then enter "AllowOverride All" in your apache configuration file (/etc/apache2/apache2.conf or /etc/httpd/httpd.conf or /etc/httpd/conf/httpd.conf)<br/>
					Example:
<pre>
&lt;Directory <?php echo AppSettings::publicPath(); ?>&gt;
	Options FollowSymLinks
	AllowOverride All
	Require all granted
&lt;/Directory&gt;
</pre>
					
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
									echo "<a class=\"btn btn-primary\" target=\"_blank\" href=\"/scripts/sync_blockchain_initial.php?key=".AppSettings::getParam('operator_key')."&blockchain_id=".$db_blockchain['blockchain_id']."\">Reset & synchronize ".$db_blockchain['blockchain_name']."</a>\n";
									echo "<br/>\n";
								}
								echo "<a href=\"\" onclick=\"$('#display_rpc_".$db_blockchain['blockchain_id']."').hide(); $('#edit_rpc_".$db_blockchain['blockchain_id']."').show('fast'); return false;\">Set new RPC params for ".$db_blockchain['blockchain_name']."</a>\n";
								echo "</div>\n";
							}
							?>
							<div id="edit_rpc_<?php echo $db_blockchain['blockchain_id']; ?>"<?php if ($tried_rpc) echo ' style="display: none;"'; ?>>
								Please enter the RPC username and password for connecting to the <b><?php echo $db_blockchain['blockchain_name']; ?></b> daemon:<br/>
								<form method="post" action="install.php">
									<input type="hidden" name="key" value="<?php echo AppSettings::getParam('operator_key'); ?>" />
									<input type="hidden" name="action" value="save_blockchain_params" />
									<input type="hidden" name="blockchain_id" value="<?php echo $db_blockchain['blockchain_id']; ?>" />
									<input class="form-control" name="rpc_host" placeholder="RPC hostname (default 127.0.0.1)" />
									<input class="form-control" name="rpc_username" placeholder="RPC username" />
									<input class="form-control" name="rpc_password" placeholder="RPC password" autocomplete="off" />
									<input class="form-control" name="rpc_port" value="<?php echo $db_blockchain['default_rpc_port']; ?>" placeholder="RPC port" />
									<input class="form-control" name="first_required_block" value="<?php echo $db_blockchain['first_required_block']; ?>" placeholder="First required block" />
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
							echo "<a target=\"_blank\" href=\"/scripts/sync_blockchain_initial.php?key=".AppSettings::getParam('operator_key')."&blockchain_id=".$db_blockchain['blockchain_id']."\">Reset & synchronize ".$db_blockchain['blockchain_name']."</a></p>\n";
						}
					}
					
					?>
					<h2>Modules</h2>
					<?php
					$installed_modules = $app->run_query("SELECT * FROM modules m JOIN games g ON m.primary_game_id=g.game_id;");
					if ($installed_modules->rowCount() > 0) {
						while ($installed_module = $installed_modules->fetch()) {
							echo '<a href="/'.$installed_module['url_identifier'].'/">'.$installed_module['name']."</a> is already installed.<br/>\n";
						}
						echo "<br/>\n";
					}
					
					$open_modules = $app->run_query("SELECT * FROM modules WHERE primary_game_id IS NULL;");
					$module_html = '<option value="">-- Select a module to install --</option>';
					while ($open_module = $open_modules->fetch()) {
						$module_html .= '<option value="'.$open_module['module_name'].'">'.$open_module['module_name']."</option>\n";
					}
					
					echo '<select class="form-control" id="select_install_module" onchange="start_install_module(\''.AppSettings::getParam('operator_key').'\');">'.$module_html."</select>\n";
					?>
					<br/>
					<br/>
					<?php
				}
				?>
			</div>
			<?php
			include(AppSettings::srcPath()."/includes/html_stop.php");
		}
		else echo "An invalid database name was specified in includes/config.php\n";
	}
	else {
		echo 'Please set the "mysql_database" variable in includes/config.php'."\n";
	}
}
else {
	echo 'Please set the correct value for "key" in the URL.<br/>';
	echo 'To find the correct key value, look for "operator_key" in your config file.'."\n";
}
?>
