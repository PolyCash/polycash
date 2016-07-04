<?php
include('../includes/connect.php');

die("This script is disabled.");

$quantity = 100;
for ($i=0; $i<$quantity; $i++) {
	$q = "INSERT INTO invitations SET inviter_id=27, invitation_key='".strtolower(random_string(32))."', time_created='".time()."';";
	$r = run_query($q);
}
echo "$quantity invitations have been generated.";
?>