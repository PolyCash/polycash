<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if ($thisuser) {
	$game_id = intval($_REQUEST['game_id']);

	$q = "SELECT payment_required FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game_id."';";
	$r = $GLOBALS['app']->run_query($q);
	
	if (mysql_numrows($r) > 0) {
		$user_game = mysql_fetch_array($r);
		
		if ($user_game['payment_required'] == 0) $status_code = 1;
		else $status_code = 2;
		
		$invoice_id = intval($_REQUEST['invoice_id']);
		
		if ($invoice_id > 0) {
			$q = "UPDATE currency_invoices SET expire_time=".(time()+$GLOBALS['invoice_expiration_seconds'])." WHERE invoice_id='".$invoice_id."';";
			$r = $GLOBALS['app']->run_query($q);
		}
		
		$GLOBALS['app']->output_message($status_code, "", $user_game);
	}
	else $GLOBALS['app']->output_message(2, "", array('payment_required'=>1));
}
else $GLOBALS['app']->output_message(2, "Please log in", false);
?>
