<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

if ($app->running_as_admin()) {
	$app->delete_unconfirmable_transactions();
	echo "Done!\n";
}
else echo "You need admin privileges to run this script.\n";
?>