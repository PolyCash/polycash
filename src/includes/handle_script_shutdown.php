<?php
//declare(ticks = 1);

/*if (PHP_OS != "WINNT") {
	pcntl_signal(SIGINT, 'script_shutdown');
	pcntl_signal(SIGTERM, 'script_shutdown');
	pcntl_signal(SIGHUP,  'script_shutdown');
	pcntl_signal(SIGABRT,  'script_shutdown');
	pcntl_signal(SIGQUIT,  'script_shutdown');
	pcntl_signal(SIGTSTP,  'script_shutdown');
}

function script_shutdown(){
	global $app;
	global $process_lock_name;
	
	$app->unlock_process($process_lock_name);
}

register_shutdown_function('script_shutdown');*/
?>
