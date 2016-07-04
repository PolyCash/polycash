<?php
declare(ticks = 1);

pcntl_signal(SIGINT, 'script_shutdown');
pcntl_signal(SIGTERM, 'script_shutdown');
pcntl_signal(SIGHUP,  'script_shutdown');
pcntl_signal(SIGABRT,  'script_shutdown');
pcntl_signal(SIGQUIT,  'script_shutdown');
pcntl_signal(SIGTSTP,  'script_shutdown');

function script_shutdown($lock_name){
	echo "script terminating...\n";
	if ($lock_name != "") {
		$dbh = new PDO("mysql:host=".$GLOBALS['mysql_server'].";charset=utf8", $GLOBALS['mysql_user'], $GLOBALS['mysql_password']) or die("Error, failed to connect to the database.");
		$dbh->query("USE ".$GLOBALS['mysql_database']) or die ("Please <a href=\"/install.php?key=\">install the database</a>");
		$shutdown_app = new App($dbh);
		$shutdown_app->set_site_constant($lock_name, 0);
	}
}
?>
