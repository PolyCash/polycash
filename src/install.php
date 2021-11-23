<?php
ini_set('memory_limit', '1024M');

$src_path = realpath(dirname(dirname(__FILE__)))."/src";
require_once($src_path."/classes/AppSettings.php");

$skip_select_db = TRUE;
require($src_path."/includes/connect.php");

if ($app->running_as_admin()) {
	set_time_limit(0);
	
	// For CSRF protection on this page, operator_key is used instead of synchronizer_token
	// The admin needs to visit this page before the db is installed so we can't require a user account here
	
	if (AppSettings::getParam('database_name') != "") {
		if (strpos(AppSettings::getParam('database_name'), "'") === false && AppSettings::getParam('database_name') === strip_tags(AppSettings::getParam('database_name'))) {
			$db_exists = false;

			if (!empty(AppSettings::getParam('sqlite_db'))) {}
			else {
				$list_of_dbs = $app->run_query("SHOW DATABASES;")->fetchAll();
				
				foreach ($list_of_dbs as $db_info) {
					if ($db_info['Database'] == AppSettings::getParam('database_name')) $db_exists = true;
				}
				
				if (strpos(AppSettings::getParam('mysql_password'), "'") !== false) {
					echo "Your mysql password includes an apostrophe. ".AppSettings::getParam('site_name')." may not be able to install or complete migrations to your DB.<br/>\n";
				}
				
				if ($db_exists) {
					$app->select_db(AppSettings::getParam('database_name'));
				}
				else {
					$app->run_query("CREATE DATABASE ".AppSettings::getParam('database_name'));
					$app->select_db(AppSettings::getParam('database_name'));
				}
				
				$app->flush_buffers();
				
				$table_exists = count($app->run_query("SHOW TABLES;")->fetchAll()) > 0;
				
				if (!$table_exists) {
					$cmd = $app->mysql_binary_location()." -u ".AppSettings::getParam('mysql_user')." -h ".AppSettings::getParam('mysql_server');
					if (AppSettings::getParam('mysql_password') != "") $cmd .= " -p'".AppSettings::getParam('mysql_password')."'";
					
					if (is_file(AppSettings::srcPath()."/sql/schema-base.sql")) $base_schema_path = AppSettings::srcPath()."/sql/schema-base.sql";
					else $base_schema_path = AppSettings::srcPath()."/sql/schema-initial.sql";
					
					$cmd .= " ".AppSettings::getParam('database_name')." < ".$base_schema_path;
					exec($cmd);
				}
				
				$table_exists = count($app->run_query("SHOW TABLES;")->fetchAll()) > 0;
				if (!$table_exists) {
					echo "Database tables failed to be created, please install manually by importing all files in the \"sql\" folder via phpMyAdmin or any other MySQL interface.<br/>\n";
					die();
				}
				
				$app->load_module_classes();
				$app->update_schema();
			}
			
			if (empty(AppSettings::getParam('identifier_case_sensitive'))) die('Please set the variable "identifier_case_sensitive" in your config file.');
			if (empty(AppSettings::getParam('identifier_first_char'))) die('Please set the variable "identifier_first_char" in your config file.');
			
			$app->blockchain_ensure_currencies();
			
			if (empty($app->get_site_constant("reference_currency_id"))) {
				$btc_currency = $app->fetch_currency_by_abbreviation("BTC");
				if ($btc_currency) {
					$app->set_reference_currency($btc_currency['currency_id']);
				}
			}
			
			$general_entity_type = $app->check_set_entity_type("general entity");
			
			require(AppSettings::srcPath()."/includes/get_session.php");
			
			$pagetitle = AppSettings::getParam('site_name')." - Installing...";
			$nav_tab_selected = "install";
			include(AppSettings::srcPath()."/includes/html_start.php");
			?>
			<div class="container-fluid">
				<?php
				if (empty($thisuser)) {
					$redirect_url = $app->get_redirect_url($_SERVER['REQUEST_URI']);
					$redirect_key = $redirect_url['redirect_key'];
					include(AppSettings::srcPath()."/includes/html_login.php");
				}
				else {
					$install_messages = $app->install_configured_games_and_blockchains($thisuser);
					
					if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "install_module") {
						$module_name = $_REQUEST['module_name'];
						
						echo "<br/><b>Installing module $module_name</b><br/>\n";
						
						if ($existing_module = $app->check_module($module_name)) {
							$module_class = $module_name.'GameDefinition';
							$game_def = new $module_class($app);
							
							$conflicting_game = $app->fetch_game_by_identifier($game_def->game_def->url_identifier);
							
							if ($conflicting_game) {
								echo "<p>There's already a game using this URL key.</p>\n";
							}
							else {
								$blockchain = false;
								$db_blockchain = $app->fetch_blockchain_by_identifier($game_def->game_def->blockchain_identifier);
								
								if ($db_blockchain) {
									$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
									$new_game_def_txt = GameDefinition::game_def_to_text($game_def->game_def);
									
									$error_message = "";
									$db_game = false;
									list($new_game, $is_new_game) = GameDefinition::set_game_from_definition($app, $new_game_def_txt, $thisuser, $error_message, $db_game, false);
									
									if ($new_game) {
										$error_message = "Import was successful. Next please <a href=\"/manage/".$new_game->db_game['url_identifier']."/?next=internal_settings\">visit this page and start the game</a>.";
									}
									else if (empty($error_message)) $error_message = "Error: failed to create the game.";
									
									if (!empty($error_message)) {
										if (is_string($error_message)) echo $error_message."<br/>\n";
										else echo "<pre>".json_encode($error_message, JSON_PRETTY_PRINT)."</pre>\n";
									}
									
									if ($is_new_game) {
										list($new_game_start_error, $new_game_start_error_message) = $new_game->start_game();
										
										if ($new_game_start_error) echo $new_game_start_error_message."<br/>\n";
									}
								}
								else echo "<p>Failed to find the blockchain.</p>\n";
							}
						}
						else echo "<p>The module must already exist in DB before you can install it.</p>\n";
					}
					?>
					<p style="margin-top: 20px;">
						Welcome to the PolyCash install page. The information below may help you install games and resolve problems with your installation.
					</p>
					<?php
					if (!empty($install_messages) && count($install_messages) > 0) {
						echo "Installing games and blockchains from your config directory:<br/>\n";
						echo "<pre>".implode("\n", $install_messages)."</pre>\n";
					}
					?>
					<h3>Run <?php echo AppSettings::getParam('site_name'); ?></h3>
					Make sure this line has been added to your /etc/crontab:<br/>
<pre>
* * * * * root <?php echo $app->php_binary_location(); ?> <?php echo str_replace("\\", "/", AppSettings::srcPath())."/cron/minutely.php"; ?>
</pre>
					If you can't use cron, please run this app in a new tab or run the command below.<br/>
					<a class="btn btn-success" target="_blank" href="cron/minutely.php?key=<?php echo AppSettings::getParam('operator_key'); ?>">Start process in a new tab</a>
					<br/>
					<pre><?php echo $app->php_binary_location(); ?> <?php echo str_replace("\\", "/", AppSettings::srcPath()."/scripts/main.php"); ?></pre>
					
					<?php
					if (AppSettings::getParam('server') == "Apache") {
						?>
						<h3>Configure Apache for symlinked URLs</h3>
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
						<?php
					}
					?>
					<h3>Blockchains</h3>
					To configure &amp; install blockchains, go to the <a href="/manage_blockchains/">blockchain manager</a>.
					<br/>
					
					<h3>Modules</h3>
					<?php
					$installed_modules = $app->run_query("SELECT * FROM modules m JOIN games g ON m.primary_game_id=g.game_id;")->fetchAll();
					if (count($installed_modules) > 0) {
						foreach ($installed_modules as $installed_module) {
							echo '<a href="/'.$installed_module['url_identifier'].'/">'.$installed_module['name']."</a> is already installed.<br/>\n";
						}
						echo "<br/>\n";
					}
					
					$open_modules = $app->run_query("SELECT * FROM modules WHERE primary_game_id IS NULL;")->fetchAll();
					$module_html = '<option value="">-- Select a module to install --</option>';
					foreach ($open_modules as $open_module) {
						$module_html .= '<option value="'.$open_module['module_name'].'">'.$open_module['module_name']."</option>\n";
					}
					
					echo '<select class="form-control" id="select_install_module" onchange="thisPageManager.start_install_module(\''.AppSettings::getParam('operator_key').'\');">'.$module_html."</select>\n";
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
		echo 'Please set the "database_name" variable in includes/config.php'."\n";
	}
}
else {
	echo 'Please set the correct value for "key" in the URL.<br/>';
	echo 'To find the correct key value, look for "operator_key" in your config file.'."\n";
}
?>
