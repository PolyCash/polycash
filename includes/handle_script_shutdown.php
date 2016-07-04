<?php
declare(ticks = 1);

pcntl_signal(SIGINT, 'script_shutdown');
pcntl_signal(SIGTERM, 'script_shutdown');
pcntl_signal(SIGHUP,  'script_shutdown');
pcntl_signal(SIGABRT,  'script_shutdown');
pcntl_signal(SIGQUIT,  'script_shutdown');
pcntl_signal(SIGTSTP,  'script_shutdown');

function script_shutdown(){
	echo "script terminating...\n";
	$GLOBALS['app']->set_site_constant($GLOBALS['shutdown_lock_name'], 0);
	echo "set_site_constant(".$GLOBALS['shutdown_lock_name'].", 0);\n\n";
}
?>
