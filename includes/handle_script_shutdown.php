<?php
declare(ticks = 1);

if (PHP_OS != "WINNT") {
	pcntl_signal(SIGINT, 'script_shutdown');
	pcntl_signal(SIGTERM, 'script_shutdown');
	pcntl_signal(SIGHUP,  'script_shutdown');
	pcntl_signal(SIGABRT,  'script_shutdown');
	pcntl_signal(SIGQUIT,  'script_shutdown');
	pcntl_signal(SIGTSTP,  'script_shutdown');
}

function script_shutdown(){
	if (!empty($GLOBALS['shutdown_lock_name'])) {
		$GLOBALS['app']->set_site_constant($GLOBALS['shutdown_lock_name'], 0);
	}
	die();
}
?>
