<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

$variation_id = intval($_REQUEST['variation_id']);

$q = "SELECT * FROM game_type_variations WHERE variation_id='".$variation_id."';";
$r = run_query($q);

if (mysql_numrows($r) > 0) {
	$variation = mysql_fetch_array($r);
	
	if ($thisuser) {
		$q = "INSERT INTO game_join_requests SET user_id='".$thisuser['user_id']."', variation_id='".$variation_id."', request_status='outstanding', time_requested='".time()."';";
		$r = run_query($q);
		$request_id = mysql_insert_id();

		process_join_requests($variation_id);
		
		$q = "SELECT *, g.url_identifier AS url_identifier FROM game_join_requests r LEFT JOIN games g ON r.game_id=g.game_id WHERE r.join_request_id='".$request_id."';";
		$r = run_query($q);
		$join_request = mysql_fetch_array($r);
		
		if ($join_request['request_status'] == "complete") {
			output_message(1, "/wallet/".$join_request['url_identifier'], false);
		}
		else {
			output_message(2, "There was an error adding you to this game. Please try again.", false);
		}
	}
	else {
		$redirect_url = get_redirect_url("/".$variation['url_identifier']."/?action=join");
		output_message(3, '/wallet/?redirect_id='.$redirect_url['redirect_url_id'], false);
	}
}
else output_message(4, "Invalid ID", false);
?>