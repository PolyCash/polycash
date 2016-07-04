<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if ($thisuser && $game) {
	$round_id = intval($_REQUEST['round_id']);
	
	$amount_sum = 0;
	$option_id_csv = "";
	
	$no_winner_included = false;
	
	$outcomes = array();
	
	$q = "SELECT SUM(i.amount), n.* FROM transaction_ios i JOIN addresses a ON i.address_id=a.address_id LEFT JOIN game_voting_options gvo ON a.bet_option_id=gvo.option_id WHERE i.game_id='".$game['game_id']."' AND a.bet_round_id = ".$round_id." AND i.create_block_id <= ".round_to_last_betting_block($game, $round_id)." GROUP BY a.bet_option_id ORDER BY SUM(i.amount) DESC;";
	$r = run_query($q);
	
	while ($bet_outcome = mysql_fetch_array($r)) {
		if ($bet_outcome['name'] == "") {
			$bet_outcome['name'] = "No Winner";
			$bet_outcome['option_id'] = 0;
		}
		$outcome_id = count($outcomes);
		$outcomes[$outcome_id]['option_id'] = $bet_outcome['option_id'];
		$outcomes[$outcome_id]['name'] = $bet_outcome['name'];
		$outcomes[$outcome_id]['amount'] = $bet_outcome['SUM(i.amount)'];
		$amount_sum += $bet_outcome['SUM(i.amount)'];
		
		if ($bet_outcome['option_id'] == 0) $no_winner_included = true;
		else $option_id_csv .= $bet_outcome['option_id'].",";
	}
	
	if ($option_id_csv != "") $option_id_csv = substr($option_id_csv, 0, strlen($option_id_csv)-1);
	
	$html = "<div class=\"row\"><b>Round #".$round_id." Betting Odds</b><br/><div class=\"col-md-6\">";
	$disp_count = 0;
	
	for ($i=0; $i<count($outcomes); $i++) {
		$html .= "<font style=\"display: inline-block; width: 120px;\">".$outcomes[$i]['name']."</font> &nbsp;&nbsp; ";
		$html .= "<font id=\"bet_option_pct_".$outcomes[$i]['option_id']."\">".round(100*$outcomes[$i]['amount']/$amount_sum, 2)."%</font> &nbsp;&nbsp; ";
		$html .= "<font id=\"bet_option_mult_".$outcomes[$i]['option_id']."\">&#215;".round($amount_sum/$outcomes[$i]['amount'], 2)."</font><br/>\n";
		$disp_count++;
		if ($disp_count == 9) $html .= "</div><div class=\"col-md-6\">";
	}
	
	$q = "SELECT * FROM game_voting_options";
	if ($nation_id_csv != "") $q .= " WHERE option_id NOT IN (".$option_id_csv.")";
	$q .= ";";
	$r = run_query($q);
	
	while ($option = mysql_fetch_array($r)) {
		$html .= "<font style=\"display: inline-block; width: 120px;\">".$option['name']."</font> &nbsp;&nbsp; ";
		$html .= "<font id=\"bet_option_pct_".$option['option_id']."\">0.00%</font> &nbsp;&nbsp; ";
		$html .= "<font id=\"bet_option_mult_".$option['option_id']."\"></font><br/>\n";
		
		$outcome_id = count($outcomes);
		$outcomes[$outcome_id]['option_id'] = $option['option_id'];
		$outcomes[$outcome_id]['name'] = $option['name'];
		$outcomes[$outcome_id]['amount'] = 0;
		
		$disp_count++;
		if ($disp_count == 9) $html .= "</div><div class=\"col-md-6\">";
	}
	
	if (!$no_winner_included) {
		$html .= "<font style=\"display: inline-block; width: 120px;\">No Winner</font> &nbsp;&nbsp; ";
		$html .= "<font id=\"bet_option_pct_0\">0.00%</font> &nbsp;&nbsp; ";
		$html .= "<font id=\"bet_option_mult_0\"></font>";
		$html .= "<br/>\n";
		
		$outcome_id = count($outcomes);
		$outcomes[$outcome_id]['option_id'] = 0;
		$outcomes[$outcome_id]['name'] = "No Winner";
		$outcomes[$outcome_id]['amount'] = 0;
	}
	
	$html .= "</div></div>\n";
	
	$output[0] = $outcomes;
	$output[1] = $html;
	
	echo json_encode($output);
}
?>