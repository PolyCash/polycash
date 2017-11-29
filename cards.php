<?php
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$pagetitle = "My Cards";
$nav_tab_selected = "cards";
$nav_subtab_selected = "cards";

if (!empty($_REQUEST['action'])) {
	$action = $_REQUEST['action'];
	
	$this_issuer = $app->get_issuer_by_server_name($GLOBALS['site_domain']);
	
	if ($action == "create") {
		$nav_subtab_selected = "create";
	}
	else if ($action == "manage") {
		$nav_subtab_selected = "manage";
	}
	
	if ($action == "try_print") {
		$denomination_id = (int) $_REQUEST['cards_denomination_id'];
		
		$q = "SELECT * FROM card_currency_denominations WHERE denomination_id='".$denomination_id."';";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$denomination = $r->fetch();
			
			$db_currency = $app->run_query("SELECT * FROM currencies WHERE currency_id='".$denomination['currency_id']."';")->fetch();
			
			$how_many = intval($_REQUEST['cards_howmany']);
			if ($_REQUEST['cards_howmany'] == "other") $how_many = intval($_REQUEST['cards_howmany_other_val']);
			
			$name = $_REQUEST['cards_name'];
			$title = $_REQUEST['cards_title'];
			$email = $_REQUEST['cards_email'];
			$pnum = $_REQUEST['cards_pnum'];
			$purity = $_REQUEST['cards_purity'];
			
			$payment_amount = $_REQUEST['cards_payment_amount'];
			
			$check_payment_amount = 0;//$app->calculate_cards_cost($btc_usd_price['price'], $denomination, $purity, $how_many);
			
			$q = "INSERT INTO card_designs SET issuer_id='".$this_issuer['issuer_id']."', image_id='".$db_currency['default_design_image_id']."', denomination_id=".$denomination['denomination_id'].", purity=".$app->quote_escape($purity).", display_name=".$app->quote_escape($name).", display_title=".$app->quote_escape($title).", display_email=".$app->quote_escape($email).", display_pnum=".$app->quote_escape($pnum).", time_created='".time()."', user_id='".$thisuser->db_user['user_id']."', redeem_url=".$app->quote_escape($GLOBALS['base_url']).";";
			$r = $app->run_query($q);
			$design_id = $app->last_insert_id();
			
			$q = "INSERT INTO card_printrequests SET issuer_id='".$this_issuer['issuer_id']."', secrets_present=1, design_id='".$design_id."', user_id='".$thisuser->db_user['user_id']."', how_many='".$how_many."', print_status='not-printed', pay_status='not-received', time_created='".time()."', lockedin_price='".$check_payment_amount."';";
			$r = $app->run_query($q);
			$request_id = $app->last_insert_id();
			
			$action = "approve_design";
		}
	}
	
	if ($action == "approve_design") {
		if (empty($design_id)) $design_id = (int) $_REQUEST['design_id'];
		
		$q = "SELECT * FROM card_designs d JOIN card_printrequests r ON r.design_id=d.design_id JOIN users u ON d.user_id=u.user_id JOIN card_currency_denominations de ON de.denomination_id=d.denomination_id WHERE d.design_id='".$design_id."';";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$design = $r->fetch();
			
			if ($design['user_id'] == $thisuser->db_user['user_id']) {
				$denom_amount = floatval(str_replace(",", "", $design['denomination']));
				
				$paper_width = "";
				if (!empty($_REQUEST['paper_width'])) $paper_width = $_REQUEST['paper_width'];
				if (empty($paper_width)) $paper_width = "standard";
				else if ($paper_width == "small") {}
				
				if ($design['how_many'] > 0) { // && in_array($design['denomination'], $ok_denoms)
					$q = "SELECT MAX(issuer_card_id), MAX(group_id) FROM cards c JOIN card_designs d ON c.design_id=d.design_id WHERE d.issuer_id='".$this_issuer['issuer_id']."';";
					$r = $app->run_query($q);
					$max_id = $r->fetch();
					
					$card_group_id = $max_id[1]+1;
					
					$first_id = 1;
					if ($max_id[0] > 0) $first_id = $max_id[0]+1;
					
					for ($i=0; $i<$design['how_many']; $i++) {
						$card_id = $i+$first_id;
						$secret = $app->random_number(16);
						$secret_hash = $app->card_secret_to_hash($secret);
						$qq = "INSERT INTO cards SET design_id='".$design['design_id']."', purity='".$design['purity']."', group_id='".$card_group_id."', secret='".$secret."', secret_hash=".$app->quote_escape($secret_hash).", issuer_card_id='".$card_id."', mint_time='".time()."', currency_id='".$design['currency_id']."', fv_currency_id='".$design['fv_currency_id']."', amount='".$denom_amount."', status='issued';";
						$rr = $app->run_query($qq);
					}
					
					$q = "UPDATE card_printrequests SET card_group_id='".$card_group_id."' WHERE request_id='".$design['request_id']."';";
					$r = $app->run_query($q);
					
					$q = "UPDATE card_designs SET status='printed' WHERE design_id='".$design['design_id']."';";
					$r = $app->run_query($q);
					
					$from = $first_id;
					$to = $first_id + $design['how_many'] - 1;
				
					$error_message = $design['how_many']." cards have been created, next please <a href=\"/?action=print_design&design_id=".$design['design_id']."\">download the PDFs</a>.<br/>\n";
					$error_class = "nostyle";
				}
				else {
					$error_message = "Error, invalid denomination or number of cards.";
					$error_class = "error";
				}
			}
			else {
				$error_message = "Error, you don't have permission to perform this action.";
				$error_class = "error";
			}
		}
	}
	else if ($action == "print_design") {
		$design_id = (int) $_REQUEST['design_id'];
		
		$q = "SELECT * FROM card_designs d JOIN card_printrequests r ON r.design_id=d.design_id JOIN users u ON d.user_id=u.user_id WHERE d.design_id='".$design_id."';";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$design = $r->fetch();
			
			if ($design['user_id'] == $thisuser->db_user['user_id']) {
				$paper_width = "";
				if (!empty($_REQUEST['paper_width'])) $paper_width = $_REQUEST['paper_width'];
				if (empty($paper_width)) $paper_width = "standard";
				else if ($paper_width == "small") {}
				
				$card_group_id = $design['card_group_id'];
				$q = "SELECT MIN(card_id) FROM cards WHERE design_id='".$design['design_id']."';";
				$r = $app->run_query($q);
				$min_card_id = $r->fetch();
				$from = $min_card_id[0];
				
				if ($from > 0) {}
				else die("Error, the cards haven't been created yet.");
				
				$to = $from + $design['how_many'] - 1;
				
				require_once(dirname(__FILE__).'/lib/card-render/fpdf.php');
				
				$perpage = 10;
				
				$numcards = $to-$from+1;
				$numpages = ceil($numcards/$perpage);
				
				if ($paper_width == "small") {
					$pdf = new FPDF('P','in',array(2.4,3.5*5+0.5));
					$orient = "tall";
					$card_print_width = 2;
				}
				else {
					$pdf = new FPDF('P','in',array(8.5,11));
					$orient = "fat";
					$card_print_width = 3.5;
				}
				
				$q = "SELECT * FROM cards WHERE card_id >= $from AND card_id <= $to;";
				$r = $app->run_query($q);
				$count = 0;
				
				$res = "";
				if (!empty($_REQUEST['res'])) {
					$res = $_REQUEST['res'];
					if ($res != "low") $res = "high";
				}
				
				if ($res == "low") $extension = "jpg";
				else $extension = "png";
				
				for ($dubpage=1; $dubpage<=$numpages; $dubpage++) {
					$numthispage = 10;
					
					if ($count+$numthispage > $numcards) $numthispage = $numcards-$count;
					
					$cardarr = array();
					
					for ($pos=1; $pos <= $numthispage; $pos++) {
						$cardarr[$pos-1] = $r->fetch();
						
						$count++;
					}
					
					if ($paper_width != "small") {
						$pdf->AddPage();
						
						$pdf->Line(0, 0.5, 0.25, 0.5);
						$pdf->Line(8.25, 0.5, 8.5, 0.5);
						
						$pdf->Line(0.75, 0, 0.75, 0.25);
						$pdf->Line(7.75, 0, 7.75, 0.25);
						
						$pdf->Line(4.25, 0, 4.25, 0.25);
						
						for ($pos=1; $pos <= $numthispage; $pos++) {
							$front_coords = $app->position_by_pos($pos, 'front', $paper_width);
							
							$side = "front";
							$temp_render_url = "http://".$_SERVER['SERVER_NAME']."/lib/card-render/render".$side.".php?key=".$GLOBALS['cron_key_string']."&card_id=".$cardarr[$pos-1]['card_id']."&orient=".$orient."&res=".$res;
							
							$pdf->Image($temp_render_url, $front_coords[0], $front_coords[1], $card_print_width, false, 'png');
						}
						$pdf->Line(0, 10.5, 0.25, 10.5);
						$pdf->Line(8.25, 10.5, 8.5, 10.5);
						
						$pdf->Line(0.75, 11, 0.75, 10.75);
						$pdf->Line(7.75, 11, 7.75, 10.75);
						
						$pdf->Line(4.25, 10.75, 4.25, 11);
					}
					
					$pdf->AddPage();
					
					$pdf->Line(0, 0.5, 0.25, 0.5);
					$pdf->Line(8.25, 0.5, 8.5, 0.5);
					
					$pdf->Line(0.75, 0, 0.75, 0.25);
					$pdf->Line(7.75, 0, 7.75, 0.25);
					
					$pdf->Line(4.25, 0, 4.25, 0.25);
					
					for ($pos=1; $pos <= $numthispage; $pos++) {
						$back_coords = $app->position_by_pos($pos, 'back', $paper_width);
						
						$side = "back";
						$img_png_url = "http://".$_SERVER['SERVER_NAME']."/lib/card-render/render".$side.".php?key=".$GLOBALS['cron_key_string']."&card_id=".$cardarr[$pos-1]['card_id']."&orient=".$orient."&res=".$res;
						
						$pdf->Image($img_png_url, $back_coords[0], $back_coords[1], $card_print_width, false, 'png');
					}
					$pdf->Line(0, 10.5, 0.25, 10.5);
					$pdf->Line(8.25, 10.5, 8.5, 10.5);
					
					$pdf->Line(0.75, 11, 0.75, 10.75);
					$pdf->Line(7.75, 11, 7.75, 10.75);
					
					$pdf->Line(4.25, 10.75, 4.25, 11);
				}
				
				$q = "UPDATE card_printrequests SET print_status='printed' WHERE request_id='".$design['request_id']."';";
				$r = $app->run_query($q);
				
				$pdf->Output('cards'.$from.'_'.$to.'.pdf', "D");
				die();
			}
			else {
				$error_message = "Error, you don't have permission to perform this action.";
				$error_class = "error";
			}
		}
		else {
			$error_message = "Error, invalid denomination or number of cards.";
			$error_class = "error";
		}
	}
	else if ($action == "activate_cards") {
		$printrequest_id = (int) $_REQUEST['printrequest_id'];
		
		$printrequest_q = "SELECT * FROM card_printrequests pr JOIN card_designs d ON pr.design_id=d.design_id WHERE pr.request_id='".$printrequest_id."';";
		$printrequest_r = $app->run_query($printrequest_q);
		
		if ($printrequest_r->rowCount() > 0) {
			$printrequest = $printrequest_r->fetch();
			
			if ($printrequest['user_id'] == $thisuser->db_user['user_id']) {
				$card_q = "SELECT * FROM card_printrequests pr JOIN card_designs d ON pr.design_id=d.design_id JOIN cards c ON c.design_id=d.design_id WHERE pr.request_id='".$printrequest['request_id']."' ORDER BY c.card_id ASC;";
				$card_r = $app->run_query($card_q);
				
				$change_count = 0;
				
				if ($card_r->rowCount() > 0) {
					while ($card = $card_r->fetch()) {
						if (in_array($card['status'], array('issued','printed','assigned'))) {
							$app->change_card_status($card, 'sold');
							$change_count++;
						}
					}
					$error_message = $change_count." cards have been activated.";
					$error_class = "success";
				}
			}
		}
	}
	else if ($action == "wipe_secrets") {
		$printrequest_id = (int) $_REQUEST['printrequest_id'];
		
		$printrequest_q = "SELECT * FROM card_printrequests pr JOIN card_designs d ON pr.design_id=d.design_id WHERE pr.request_id='".$printrequest_id."';";
		$printrequest_r = $app->run_query($printrequest_q);
		
		if ($printrequest_r->rowCount() > 0) {
			$printrequest = $printrequest_r->fetch();
			
			if ($printrequest['secrets_present'] == 1) {
				$q = "UPDATE cards SET secret=NULL WHERE group_id=".$printrequest['card_group_id'].";";
				$r = $app->run_query($q);
				
				$q = "UPDATE card_printrequests SET secrets_present=0 WHERE request_id='".$printrequest['request_id']."';";
				$r = $app->run_query($q);
				
				$error_message = "Secrets have been successfully wiped for this card group!";
				$error_class = "success";
			}
			else {
				$error_message = "Action canceled: secrets have already been wiped.";
				$error_class = "error";
			}
		}
		else {
			$error_message = "Error: invalid print request ID.";
			$error_class = "error";
		}
	}
	else if ($action == "change_card_status") {
		$card_id = (int) $_REQUEST['card_id'];
		$to_status = $_REQUEST['to_status'];
		
		$card_q = "SELECT *, pr.user_id AS user_id FROM card_printrequests pr JOIN card_designs d ON pr.design_id=d.design_id JOIN cards c ON c.design_id=d.design_id WHERE c.card_id='".$card_id."';";
		$card_r = $app->run_query($card_q);
		
		if ($card_r->rowCount() > 0) {
			$card = $card_r->fetch();
			
			if ($card['user_id'] == $thisuser->db_user['user_id']) {
				$ok = false;
				if ($card['status'] == "sold" && ($to_status == "canceled" || $to_status == "printed")) $ok = true;
				else if (in_array($card['status'], array("printed", "issued")) && in_array($to_status, array('canceled','sold'))) $ok = true;
				
				if ($ok) {
					$app->change_card_status($card, $to_status);
					$error_message = "Successfully changed status of <a href=\"/redeem/".$card['card_id']."\">card #".$card['card_id']."</a>";
					$error_class = "success";
				}
				else {
					$error_message = "You can't switch this card to that status.";
					$error_class = "error";
				}
			}
			else {
				$error_message = "Permission denied.";
				$error_class = "error";
			}
		}
		else {
			$error_message = "Failed to change card status: invalid card ID.";
			$error_class = "error";
		}
	}
}

include('includes/html_start.php');
?>
<div class="container-fluid">
	<?php
	if (!empty($error_message)) echo $app->render_error_message($error_message, $error_class);
	
	if ($thisuser) {
		$btc_currency = $app->get_currency_by_abbreviation("btc");
		$btc_usd_price = $app->latest_currency_price($btc_currency['currency_id']);
		
		if ($nav_subtab_selected == "create") {
			?>
			<script type="text/javascript">
			var card_printing_cost = 0.25;
			var cost_per_coin = 1;
			var coin_abbreviation = "";
			var usd_per_btc = <?php if ($btc_usd_price) echo $btc_usd_price['price']; else echo "false"; ?>;

			$(document).ready(function() {
				cards_howmany_changed();
				fv_currency_id_changed();
				set_fees();
			});
			</script>
			
			<div class="panel panel-info" style="margin-top: 15px;">
				<div class="panel-heading">
					<div class="panel-title">Create Cards</div>
				</div>
				<div class="panel-body">
					<form action="/cards/" method="post">
						<input type="hidden" name="action" value="try_print" />
						<input type="hidden" name="cards_payment_amount" id="payment_amount" value="" />
						
						<div class="form-group">
							<label for="cards_currency_id">Which currency should the cards convert to?</label>
							<select id="cards_currency_id" class="form-control" name="cards_currency" onchange="currency_id_changed();">
								<option value="">-- Please Select --</option>
								<?php
								$q = "SELECT * FROM currencies WHERE blockchain_id IS NOT NULL ORDER BY name ASC;";
								$r = $app->run_query($q);
								while ($currency = $r->fetch()) {
									echo "<option value=\"".$currency['currency_id']."\">".$currency['name']."</option>\n";
								}
								?>
							</select>
						</div>
						
						<div class="form-group">
							<label for="cards_fv_currency_id">Which currency should the cards hold?</label>
							
							<select id="cards_fv_currency_id" class="form-control" name="cards_fv_currency_id" onchange="fv_currency_id_changed(); set_fees();">
								<option value="">-- Please Select --</option>
							</select>
						</div>
						
						<div class="form-group">
							<label for="cards_denomination_id">What denomination should the cards be?</label>
							<select id="cards_denomination_id" class="form-control" name="cards_denomination_id" onchange="set_fees();">
								<option value="">-- Please Select --</option>
							</select>
						</div>
						
						<div class="form-group">
							<label for="cards_howmany">How many cards do you want to print?</label>
							<select id="cards_howmany" class="form-control" name="cards_howmany" onchange="cards_howmany_changed(); set_fees();">
								<?php
								$ops = explode(",", "10,20,50,100,other");
								
								for ($i=0; $i<count($ops); $i++) {
									echo "<option value=\"".$ops[$i]."\">";
									if ($ops[$i] == "other") echo "Other";
									else echo $ops[$i]." cards";
									echo "</option>\n";
								}
								?>
							</select>
						</div>
						
						<div class="form-group" style="display: none;" id="cards_howmany_other">
							<label for="cards_howmany_other_val">How many?</label>
							<input type="text" class="form-control" size="4" value="" id="cards_howmany_other_val" name="cards_howmany_other_val" placeholder="100" /> cards
						</div>
						
						<div class="form-group">
							<label for="card_purity">How much should be charged in fees to the person redeeming each card?</label>
							
							<div id="cards_purity_btc" style="display: none;">
								0.00%
							</div>
							
							<div id="cards_purity_usd">
								<select id="cards_purity" class="form-control" name="cards_purity" onchange="set_fees();">
								<?php
								$ops = explode(",", "100,95,92,90,88,85,80");
								for ($i=0; $i<count($ops); $i++) {
									echo "<option ";
									if ($ops[$i] == 100) echo "selected=\"selected\" ";
									echo "value=\"".$ops[$i]."\">";
									if ($ops[$i] == "unspecified") echo "Unspecified";
									else echo (100-$ops[$i])."% in fees";
									echo "</option>\n";
								}
								?>
								</select>
							</div>
						</div>
						
						<div class="form-group">
							<label for="cards_name">Please enter a name that you would like to appear on the cards:</label>
							<input type="text" size="50" id="cards_name" class="form-control" name="cards_name" />
						</div>
						
						<div class="form-group">
							<label for="cards_title">(Optional) Please enter a position & title to appear on the cards:</label>
							<input type="text" size="70" id="cards_title" class="form-control" name="cards_title" />
						</div>
						
						<div class="form-group">
							<label for="cards_email">(Optional) Please an email address that you would like to appear on the cards:</label>
							<input type="text" size="40" id="cards_email" class="form-control" name="cards_email" />
						</div>
						
						<div class="form-group">
							<label for="cards_pnum">(Optional) Please enter a phone number that you would like to appear on the cards:</label>
							<input type="text" size="40" id="cards_pnum" class="form-control" name="cards_pnum" />
						</div>
						
						<a href="" onclick="show_card_preview(); return false;">Preview my cards</a><br/>
						<div id="cards_preview" style="display: none; padding: 5px;">&nbsp;</div>
						<br/>
						
						<div id="fees_disp"></div>
						
						<input class="btn btn-primary" type="submit" value="Save &amp; Continue" />
					</form>
				</div>
			</div>
			<?php
		}
		else if ($nav_subtab_selected == "manage") {
			$q = "SELECT * FROM card_printrequests pr JOIN card_designs cd ON pr.design_id=cd.design_id JOIN card_currency_denominations denom ON cd.denomination_id=denom.denomination_id JOIN currencies c ON denom.currency_id=c.currency_id WHERE pr.user_id='".$thisuser->db_user['user_id']."' ORDER BY pr.time_created DESC;";
			$r = $app->run_query($q);
			
			echo '
			<div class="panel panel-info" style="margin-top: 15px;">
				<div class="panel-heading">
					<div class="panel-title">My Print Requests ('.$r->rowCount().')</div>
				</div>
				<div class="panel-body">';
			
			while ($printrequest = $r->fetch()) {
				$issuer = $app->get_issuer_by_id($printrequest['issuer_id']);
				
				$qq = "SELECT MIN(card_id), MAX(card_id), MIN(issuer_card_id), MAX(issuer_card_id) FROM cards WHERE group_id=".$printrequest['card_group_id'].";";
				$rr = $app->run_query($qq);
				$minmax = $rr->fetch();
				
				echo "<div class=\"row\">";
				echo "<div class=\"col-sm-4\">".$issuer['issuer_name']." cards ".$minmax['MIN(issuer_card_id)'].":".$minmax['MAX(issuer_card_id)']." &nbsp;&nbsp; ".$printrequest['how_many']." &cross; ".$printrequest['denomination']." ".$printrequest['short_name']." cards</div>";
				echo "<div class=\"col-sm-4\">".$printrequest['display_name'].", ".$printrequest['display_email']."</div>\n";
				echo "<div class=\"col-sm-4\">";
				echo "<a href=\"/cards/?action=activate_cards&printrequest_id=".$printrequest['request_id']."\">Activate Cards</a>\n";
				
				if ($printrequest['secrets_present'] == 1) {
					echo " &nbsp;&nbsp; <a href=\"/cards/?action=print_design&design_id=".$printrequest['design_id']."\">Download PDFs</a>\n";
					echo " &nbsp;&nbsp; <a href=\"/cards/?action=wipe_secrets&printrequest_id=".$printrequest['request_id']."\">Wipe Secrets</a>\n";
				}
				echo "</div>\n";
				echo "</div>\n";
			}
			echo "</div></div>\n";
			
			$card_q = "SELECT * FROM card_printrequests pr JOIN card_designs cd ON pr.design_id=cd.design_id JOIN card_currency_denominations denom ON cd.denomination_id=denom.denomination_id JOIN currencies c ON denom.currency_id=c.currency_id JOIN cards ON cards.design_id=cd.design_id WHERE pr.user_id='".$thisuser->db_user['user_id']."' ORDER BY cards.card_id ASC;";
			$card_r = $app->run_query($card_q);
			
			echo '
			<div class="panel panel-info" style="margin-top: 15px;">
				<div class="panel-heading">
					<div class="panel-title">My Cards ('.$card_r->rowCount().')</div>
				</div>
				<div class="panel-body">';
			
			while ($db_card = $card_r->fetch()) {
				echo '<div class="card_small">';
				echo '<a target="_blank" href="/redeem/'.$db_card['issuer_id'].'/'.$db_card['issuer_card_id'].'">'.$db_card['issuer_card_id']."</a><br/>\n";
				echo " ".$app->format_bignum($db_card['amount'])." ".$db_card['abbreviation'];
				echo "<br/>";
				echo $db_card['status'];
				echo "</div>\n";
			}
			echo "</div></div>\n";
		}
		else {
			$reference_currency = $app->get_reference_currency();
			$btc_currency = $app->get_currency_by_abbreviation('btc');
			$currency_prices = $app->fetch_currency_prices();
			$networth = $app->calculate_cards_networth($my_cards);
			?>
			<script type="text/javascript">
			var selected_card = -1;
			var selected_section = false;

			function open_page_section(section_id) {
				if (section_id == selected_section) {
					$('#section_link_'+selected_section).removeClass('active');
					$('#section_'+selected_section).hide('fast');
					selected_section = false;
				}
				else {
					if (selected_section !== false) {
						$('#section_'+selected_section).hide();
						$('#section_link_'+selected_section).removeClass('active');
					}
					$('#section_'+section_id).show();
					$('#section_link_'+section_id).addClass('active');
					selected_section = section_id;
				}
			}
			
			$(document).ready(function() {
				open_card(0);
				open_page_section("<?php if (empty($_REQUEST['start_section'])) echo "cards"; else echo $_REQUEST['start_section']; ?>");
				$('#card_login').show();
			});
			</script>
			
			<p style="margin-top: 15px;">
				Your money is stored in this online account. 
				If you're on mobile, <a target="_blank" href="/check/<?php echo $my_cards[0]['card_id']."/".$my_cards[0]['secret']; ?>?action=bookmark">bookmark this link</a> for your convenience.
				<br/>
				Account value: <font style="color: #0a0;">$<?php echo number_format($networth, 2); ?></font>
			</p>
			<div id="section_cards" style="display: none;">
				<div class="panel panel-info">
					<div class="panel-heading">
						<div class="panel-title">My Cards</div>
					</div>
					<div class="panel-body">
						<?php
						if (count($my_cards) > 1) {
							echo "You have ".(count($my_cards)-1)." other cards. ";
							//<a href="" onclick="$('#display_hotcards').toggle('fast'); return false;">See my cards</a>
							?>
							
							<div id="display_hotcards">
								<?php
								for ($i=0; $i<count($my_cards); $i++) {
									echo '<div class="card_small" id="card_btn'.$i.'" onclick="open_card('.$i.');">';
									echo $my_cards[$i]['issuer_card_id'];
									echo "<br/>\n";
									echo $app->format_bignum($my_cards[$i]['amount'])." ".$my_cards[$i]['abbreviation'];
									echo "</div>\n";
								}
								?>
							</div>
							<br/>
							<?php
							/*$profit = $networth - $dollarsum;
							
							if ($profit >= 0) {
								echo "Overall, you've made <font style=\"color: #0a0;\">$".number_format($profit, 2)."</font> in profit since opening this account.<br/>\n";
							}
							else if ($profit < 0) {
								echo "Overall, your account has lost <font class=\"redtext\">$".number_format($profit*(-1), 2)."</font> in value since you opened this account.<br/>\n";
							}
							*/
						}
						?>
						<div class="card_group_holder"><?php
							for ($i=0; $i<count($my_cards); $i++) {
								$q = "SELECT * FROM card_withdrawals WHERE withdraw_method='card_account' AND card_id='".$my_cards[$i]['card_id']."' ORDER BY withdrawal_id ASC LIMIT 1;";
								$r = $app->run_query($q);
								$withdrawal = $r->fetch();
								?>
								<div class="card_block" id="card_block<?php echo $i; ?>" style="display: none;">
									<?php
									/*if ($my_cards[$i]['currency'] == "usd") {
										echo 'You exchanged this <font style="color: #0a0; font-size: inherit;">$'.number_format($my_cards[$i]['amount'], 2).'</font>';
										echo ' card for '.number_format($withdrawal['btc'], 5).' bitcoins on '.date("n/j/Y", $withdrawal['withdraw_time']).'.';
									}
									else {
										echo "You redeemed this $".$my_cards[$i]['amount']." ".$my_cards[$i]['currency_abbrev']." card on ".date("n/j/Y", $withdrawal['withdraw_time']).'.';
									}*/
									?>
									<div style="display: block; overflow: hidden;">
										<div class="row">
											<div class="col-xs-4">Card ID</div><div class="col-xs-8">#<?php echo $my_cards[$i]['issuer_card_id']; ?></div>
										</div>
										<div class="row">
											<div class="col-xs-4">Minted</div><div class="col-xs-8"><?php echo $app->format_seconds(time() - $my_cards[$i]['mint_time']); ?> ago</div>
										</div>
										<div class="row">
											<div class="col-xs-4">Card denomination</div>
											<div class="col-xs-8">
												<?php
												echo $app->format_bignum($my_cards[$i]['amount'])." ".$my_cards[$i]['abbreviation'];
												?>
											</div>
										</div>
										<?php
										if ($my_cards[$i]['purity'] != 100) { ?>
											<div class="row">
												<div class="col-xs-4">Fees</div>
												<div class="col-xs-8" style="color: #<?php
													$fees = $app->get_card_fees($my_cards[$i]);
													if ($fees > 0) echo "f00"; else echo "0a0;";
													?>"><?php echo $app->format_bignum($fees)." ".$my_cards[$i]['abbreviation']; ?>
												</div>
											</div>
											<?php /*
											<div class="col-xs-4">Exchanged at</div><div class="col-xs-8"><font style="color: #0a0; font-size: inherit;">$<?php echo number_format($withdrawal['usd_per_btc'], 2); ?></font> / BTC</div><br/>
											
											<div class="col-xs-4">Bitcoins purchased</div><div class="col-xs-8"><div class="bitsym"></div><?php echo number_format($withdrawal['btc'], 5); ?></div><br/>
											<?php
											*/
										}
										?>
										<div class="row">
											<div class="col-xs-4">You have</div>
											<div class="col-xs-8">
												<?php
												$balances = $app->get_card_currency_balances($my_cards[$i]['card_id']);
												foreach ($balances as $j => $balance) {
													echo $app->format_bignum($balance['balance'])." ".$balance['abbreviation']." ";
												}
												?>
											</div>
										</div>
										<div class="row">
											<div class="col-xs-4">Initial Value</div>
											<div class="col-xs-8">
												<?php
												$conversion_rate = $app->currency_conversion_rate($reference_currency['currency_id'], $my_cards[$i]['fv_currency_id']);
												$init_value = $my_cards[$i]['amount'] - $app->get_card_fees($my_cards[$i]);
												$init_value_ref = round($init_value/$conversion_rate['conversion_rate'], 2);
												echo $app->format_bignum($init_value_ref)." ".$reference_currency['abbreviation'];
												?>
											</div>
										</div>
										<div class="row">
											<div class="col-xs-4">Current Value</div>
											<div class="col-xs-8">
												<?php
												$this_val = round($app->calculate_card_networth($my_cards[$i], $balances, $currency_prices), 2);
												echo $app->format_bignum($this_val)." ".$reference_currency['abbreviation'];
												?>
											</div>
										</div>
										<div class="row">
											<div class="col-xs-4">Total Gain or Loss</div>
											<div class="col-xs-8">
												<?php
												$this_prof = $this_val - $init_value_ref;
												echo $app->format_bignum($this_prof)." ".$reference_currency['abbreviation'];
												?>
											</div>
										</div>
										
										<div id="messages" style="margin-top: 15px; display: block;"></div>
									</div>
								</div>
								<?php
							}
							?>
						</div>
					</div>
				</div>
			</div>
			<div id="section_add_card" style="display: none;">
				<div class="panel panel-info">
					<div class="panel-heading">
						<div class="panel-title">Log into a card to add it to this account</div>
					</div>
					<div class="panel-body">
						<?php
						$ask4nameid = TRUE;
						$login_title = "";
						$card_login_card_id = "$('#issuer_card_id').val()";
						include(dirname(__FILE__)."/includes/html_card_login.php");
						?>
					</div>
				</div>
			</div>
			<div id="section_withdraw_btc" style="display: none;">
				<div class="panel panel-info">
					<div class="panel-heading">
						<div class="panel-title">Withdraw Bitcoins</div>
					</div>
					<div class="panel-body">
						<div class="form-group">Your withdrawal limit is <div class="coinsymbol"></div><?php
							$btc_withdraw_limit = round($networth*$currency_prices[$btc_currency['currency_id']]['price'], 8);
							
							echo $btc_withdraw_limit;
							//echo number_format($networth, 2);
							//echo "$".number_format($currency_prices[$btc_currency['currency_id']]['price'], 2);
							?> BTC
						</div>
						<div class="form-group">
							<label for="send_bitcoin_amount">How many BTC do you want to withdraw?</label>
							<input class="form-control" type="text" size="10" id="send_bitcoin_amount" />
						</div>
						<div class="form-group">
							<label for="send_bitcoin_address">Please enter a bitcoin address:</label>
							<input class="form-control" type="text" size="30" id="send_bitcoin_address" />
							
							<a href="" onclick="$('#os_options').show(); return false;">Scan a QR code address</a>
							<div style="display: none;" id="os_options">
								Which are you using?<br/>
								<a class="btn btn-default" href="" onclick="QRC.selectOS('iphone'); return false;">Mobile Phone</a>&nbsp;&nbsp;&nbsp;
								<a class="btn btn-default" href="" onclick="QRC.selectOS('pc'); return false;">Computer</a>
							</div>
							<div id="qrUpload" style="display: none; border: 1px solid #ccc; padding: 10px;">
								Please upload a picture of the QR code.
								<div id="qrfile">
									<canvas id="out-canvas" width="200" height="150"></canvas>
									<div id="imghelp">
										<input type="file" onchange="QRC.handleFiles(this.files)"/>
									</div>
								</div>
							</div>
							<div id="qrCam" style="display: none; border: 1px solid #ccc; padding: 10px;">
								To scan an address, please share your web cam with the browser, then hold the QR code up to your web cam.
								
								<div id="outdiv"></div>
								
								<canvas id="qrCanvas" width="800" height="600"></canvas>
							</div>
						</div>
						<button class="btn btn-success" onclick="deposit_coins();">Withdraw Bitcoins</button>
					</div>
				</div>
			</div>
			<?php
		}
	}
	else {
		$redirect_url = $app->get_redirect_url("/cards/");
		$redirect_key = $redirect_url['redirect_key'];
		include("includes/html_login.php");
	}
	?>
</div>
<?php
include('includes/html_stop.php');
?>