<?php
include("../includes/connect.php");

if ($_REQUEST['key'] == "oiwreu2490f98") {
	$q = "DELETE FROM webwallet_transactions WHERE transaction_desc != 'giveaway';";
	$r = run_query($q);
	
	$q = "UPDATE webwallet_transactions SET block_id=2;";
	$r = run_query($q);
	
	$q = "DELETE FROM blocks WHERE block_id > 1;";
	$r = run_query($q);
	
	$q = "DELETE FROM cached_rounds;";
	$r = run_query($q);
	
	$q = "ALTER TABLE blocks AUTO_INCREMENT=2;";
	$r = run_query($q);
	
	$q = "UPDATE nations SET cached_force_multiplier=16, relevant_wins=1;";
	$r = run_query($q);
	
	echo "Great, the game has been reset!";
}
?>