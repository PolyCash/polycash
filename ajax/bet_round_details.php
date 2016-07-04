<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if ($thisuser) {
	$round_id = intval($_REQUEST['round_id']);
	
	$amount_sum = 0;
	$nation_id_csv = "";
	
	$no_winner_included = false;
	
	$outcomes = array();
	
	$q = "SELECT SUM(i.amount), n.* FROM transaction_IOs i JOIN addresses a ON i.address_id=a.address_id LEFT JOIN nations n ON a.bet_nation_id=n.nation_id WHERE i.game_id='".$game['game_id']."' AND a.bet_round_id = ".$round_id." AND i.create_block_id <= ".round_to_last_betting_block($game, $round_id)." GROUP BY a.bet_nation_id ORDER BY SUM(i.amount) DESC;";
	$r = run_query($q);
	
	while ($bet_outcome = mysql_fetch_array($r)) {
		if ($bet_outcome['name'] == "") {
			$bet_outcome['name'] = "No Winner";
			$bet_outcome['nation_id'] = 0;
		}
		$outcome_id = count($outcomes);
		$outcomes[$outcome_id]['nation_id'] = $bet_outcome['nation_id'];
		$outcomes[$outcome_id]['name'] = $bet_outcome['name'];
		$outcomes[$outcome_id]['amount'] = $bet_outcome['SUM(i.amount)'];
		$amount_sum += $bet_outcome['SUM(i.amount)'];
		
		if ($bet_outcome['nation_id'] == 0) $no_winner_included = true;
		else $nation_id_csv .= $bet_outcome['nation_id'].",";
	}
	
	if ($nation_id_csv != "") $nation_id_csv = substr($nation_id_csv, 0, strlen($nation_id_csv)-1);
	
	$html = "<div class=\"row\"><b>Round #".$round_id." Betting Odds</b><br/><div class=\"col-md-6\">";
	$disp_count = 0;
	
	for ($i=0; $i<count($outcomes); $i++) {
		$html .= "<font style=\"display: inline-block; width: 120px;\">".$outcomes[$i]['name']."</font> &nbsp;&nbsp; ";
		$html .= "<font id=\"bet_nation_pct_".$outcomes[$i]['nation_id']."\">".round(100*$outcomes[$i]['amount']/$amount_sum, 2)."%</font> &nbsp;&nbsp; ";
		$html .= "<font id=\"bet_nation_mult_".$outcomes[$i]['nation_id']."\">&#215;".round($amount_sum/$outcomes[$i]['amount'], 2)."</font><br/>\n";
		$disp_count++;
		if ($disp_count == 9) $html .= "</div><div class=\"col-md-6\">";
	}
	
	$q = "SELECT * FROM nations";
	if ($nation_id_csv != "") $q .= " WHERE nation_id NOT IN (".$nation_id_csv.")";
	$q .= ";";
	$r = run_query($q);
	
	while ($nation = mysql_fetch_array($r)) {
		$html .= "<font style=\"display: inline-block; width: 120px;\">".$nation['name']."</font> &nbsp;&nbsp; ";
		$html .= "<font id=\"bet_nation_pct_".$nation['nation_id']."\">0.00%</font> &nbsp;&nbsp; ";
		$html .= "<font id=\"bet_nation_mult_".$nation['nation_id']."\"></font><br/>\n";
		
		$outcome_id = count($outcomes);
		$outcomes[$outcome_id]['nation_id'] = $nation['nation_id'];
		$outcomes[$outcome_id]['name'] = $nation['name'];
		$outcomes[$outcome_id]['amount'] = 0;
		
		$disp_count++;
		if ($disp_count == 9) $html .= "</div><div class=\"col-md-6\">";
	}
	
	if (!$no_winner_included) {
		$html .= "<font style=\"display: inline-block; width: 120px;\">No Winner</font> &nbsp;&nbsp; ";
		$html .= "<font id=\"bet_nation_pct_0\">0.00%</font> &nbsp;&nbsp; ";
		$html .= "<font id=\"bet_nation_mult_0\"></font>";
		$html .= "<br/>\n";
		
		$outcome_id = count($outcomes);
		$outcomes[$outcome_id]['nation_id'] = 0;
		$outcomes[$outcome_id]['name'] = "No Winner";
		$outcomes[$outcome_id]['amount'] = 0;
	}
	
	$html .= "</div></div>\n";
	
	$output[0] = $outcomes;
	$output[1] = $html;
	
	echo json_encode($output);
}
?>