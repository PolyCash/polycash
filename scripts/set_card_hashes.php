<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if ($argv) $_REQUEST['key'] = $argv[1];

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$q = "SELECT * FROM cards WHERE secret_hash IS NULL;";
	$r = $app->run_query($q);
	$counter = 0;
	while ($card = $r->fetch()) {
		$qq = "UPDATE cards SET secret_hash=".$app->quote_escape(hash("sha256", $card['secret']))." WHERE card_id='".$card['card_id']."';";
		$rr = $app->run_query($qq);
		$counter++;
	}
	echo "Updated ".$counter." cards.";
}
else echo "Incorrect key.";
?>
