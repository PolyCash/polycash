<?php
include("../includes/connect.php");
include("../includes/get_session.php");
$viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$from_round_id = intval($_REQUEST['from_round_id']);
	$to_round_id = intval($_REQUEST['to_round_id']);
	
	echo performance_history($thisuser, $from_round_id, $to_round_id);
}
?>