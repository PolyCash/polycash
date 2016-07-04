function Image(id) {
	this.imageId = id;
	this.imageSrc = '/img/carousel/'+id+'.jpg';
}
function ImageCarousel(containerElementId) {
	this.numPhotos = 16;
	this.currentPhotoId = -1;
	this.slideTime = 10000;
	this.widthToHeight = Math.round(1800/570, 6);
	this.containerElementId = containerElementId;
	this.images = new Array();
	var _this = this;
	
	this.initialize = function() {
		for (var imageId=0; imageId<this.numPhotos; imageId++) {
			this.images[imageId] = new Image(imageId);
			$('<img />').attr('src',this.images[imageId].imageSrc).appendTo('body').css('display','none');
			$('#'+this.containerElementId).append('<div id="'+this.containerElementId+'_image'+imageId+'" class="carouselImage" style="background-image: url(\''+this.images[imageId].imageSrc+'\');"></div>');
		}
		
		this.nextPhoto();
	};
	
	this.nextPhoto = function() {
		var prevPhotoId = this.currentPhotoId;
		var curPhotoId = prevPhotoId + 1;
		if (curPhotoId == this.numPhotos) curPhotoId = 0;
		
		if (prevPhotoId == -1) {}
		else $('#'+this.containerElementId+'_image'+prevPhotoId).fadeOut('slow');
		
		$('#'+this.containerElementId+'_image'+curPhotoId).fadeIn('slow');
		this.currentPhotoId = curPhotoId;
		
		setTimeout(function() {_this.nextPhoto()}, this.slideTime);
	};
}
function tab_clicked(index_id) {
	if (current_tab !== false) {
		$('#tabcell'+current_tab).removeClass("tabcell_sel");
		$('#tabcontent'+current_tab).hide();
	}
	
	$('#tabcell'+index_id).addClass("tabcell_sel");
	$('#tabcontent'+index_id).show();
	
	current_tab = index_id;
}
function claim_coin_giveaway() {
	$('#giveaway_btn').html("Loading...");
	
	$.get("/ajax/coin_giveaway.php?do=claim", function(result) {
		$('#giveaway_btn').html("Claim 1,000 EmpireCoins");
		
		if (result == "1") alert("Great, 1,000 EmpireCoins have been added to your account!");
		else alert("Your free coins have already been claimed.");
		
		$('#giveaway_div').html("We're currently in beta; this feature isn't available right now.");
		
		refresh_if_needed();
	});
}
function start_vote(nation_id) {
	if ((last_block_id+1)%10 != 0) {
		$('#vote_confirm_'+nation_id).modal('toggle');
		$('#vote_details_'+nation_id).html($('#vote_details_general').html());
		$('#vote_amount_'+nation_id).focus();
		
		setTimeout("$('#vote_amount_"+nation_id+"').focus();", 500);
		
		// Line below is needed to reselect the nation button which has accidentally been unselected by the modal
		setTimeout('nation_selected('+$('#nation_id2rank_'+nation_id).val()+');', 100);
	}
	else {
		alert('Voting is currently disabled.');
	}
}
function confirm_vote(nation_id) {
	$('#vote_confirm_btn_'+nation_id).html("Loading...");
	$.get("/ajax/place_vote.php?nation_id="+nation_id+"&amount="+encodeURIComponent($('#vote_amount_'+nation_id).val()), function(result) {
		$('#vote_confirm_btn_'+nation_id).html("Confirm Vote");
		var result_parts = result.split("=====");
		if (result_parts[0] == "0") {
			refresh_if_needed();
			$('#vote_confirm_'+nation_id).modal('hide');
			$('#vote_amount_'+nation_id).val("");
			alert("Great, your vote has been submitted!");
		}
		else {
			$('#vote_error_'+nation_id).html(result_parts[1]);
			$('#vote_error_'+nation_id).slideDown('slow');
			setTimeout("$('#vote_error_"+nation_id+"').slideUp('fast');", 2500);
		}
	});
}
function rank_check_all_changed() {
	var set_checked = false;
	if ($('#rank_check_all').is(":checked")) set_checked = true;
	for (var i=1; i<=16; i++) {
		$('#by_rank_'+i).prop("checked", set_checked);
	}
}
function vote_on_block_all_changed() {
	var set_checked = false;
	if ($('#vote_on_block_all').is(":checked")) set_checked = true;
	for (var i=1; i<=9; i++) {
		$('#vote_on_block_'+i).prop("checked", set_checked);
	}
}
function by_nation_reset_pct() {
	for (var nation_id=1; nation_id<=16; nation_id++) {
		$('#nation_pct_'+nation_id).val("0");
	}
}
function loop_event() {
	var nation_pct_sum = 0;
	for (var i=1; i<=16; i++) {
		var temp_pct = parseInt($('#nation_pct_'+i).val());
		if (temp_pct && !$('#nation_pct_'+i).is(":focus") && temp_pct != $('#nation_pct_'+i).val()) {
			$('#nation_pct_'+i).val(temp_pct);
		}
		if (temp_pct) nation_pct_sum += temp_pct;
	}
	if (nation_pct_sum <= 100 && nation_pct_sum >= 0) {
		$('#nation_pct_subtotal').html("<font class='greentext'>"+nation_pct_sum+"/100 allocated, "+(100-nation_pct_sum)+"% left</font>");
	}
	else {
		$('#nation_pct_subtotal').html("<font class='redtext'>"+nation_pct_sum+"/100 allocated</font>");
	}
	
	setTimeout("loop_event();", 1000);
}
function game_loop_event() {
	refresh_if_needed();
	game_loop_index++;
	setTimeout("game_loop_event();", 2000);
}
var reset_next_block_text = false;
function next_block() {
	if ($('#next_block_btn').html() == "Next Block") {
		$('#next_block_btn').html("Loading...");
		
		$.get("/ajax/next_block.php", function(result) {
			reset_next_block_text = true;
			refresh_if_needed();
		});
	}
}
function refresh_if_needed() {
	if (!refresh_in_progress || last_refresh_time < new Date().getTime() - 1000*5) {
		last_refresh_time = new Date().getTime();
		refresh_in_progress = true;
		
		var check_activity_url = "/ajax/check_new_activity.php?refresh_page="+refresh_page+"&last_block_id="+last_block_id+"&last_transaction_id="+last_transaction_id+"&my_last_transaction_id="+my_last_transaction_id+"&mature_io_ids_csv="+mature_io_ids_csv+"&game_loop_index="+game_loop_index+"&min_bet_round="+min_bet_round;
		if (refresh_page == "wallet") check_activity_url += "&performance_history_sections="+performance_history_sections;
		
		$.ajax({
			url: check_activity_url,
			success: function(result) {
				if (reset_next_block_text) { $('#next_block_btn').html("Next Block"); reset_next_block_text = false;}
				
				if (refresh_page == "wallet" && result == "0") {
					window.location = '/wallet/?do=logout';
				}
				else {
					refresh_in_progress = false;
					var json_result = $.parseJSON(result);
					
					if (json_result['game_loop_index'] > last_game_loop_index_applied) {
						if (json_result['new_block'] == "1") {
							last_block_id = parseInt(json_result['last_block_id']);
							
							if (refresh_page == "wallet") {
								if ((last_block_id+1)%10 == 0) {
									$('#vote_popups').slideUp('medium');
									$('#vote_popups_disabled').slideDown('fast');
								}
								else {
									$('#vote_popups').show('fast');
									$('#vote_popups_disabled').hide('fast');
								}
								
								if (json_result['new_performance_history'] == 1) {
									for (var i=1; i<performance_history_sections; i++) {
										$('#performance_history_'+i).html("");
									}
									$('#performance_history_0').html(json_result['performance_history']);
									$('#performance_history_0').hide();
									$('#performance_history_0').fadeIn('fast');
									
									performance_history_start_round = json_result['performance_history_start_round'];
									
									tab_clicked(2);
								}
								
								if (parseInt(json_result['min_bet_round']) != min_bet_round) {
									min_bet_round = parseInt(json_result['min_bet_round']);
									var selected_bet_round = $('#bet_round').val();
									$('#select_bet_round').html(json_result['select_bet_round']);	
									$('#bet_round').val(selected_bet_round);
								}
								
								if (json_result['new_mature_ios'] == 1) {
									mature_io_ids_csv = json_result['mature_io_ids_csv'];
									reload_compose_vote();
								}
								refresh_mature_io_btns();
								set_input_amount_sums();
							}
						}
						if (json_result['new_transaction'] == "1") {
							last_transaction_id = parseInt(json_result['last_transaction_id']);
							if (user_logged_in) $('#vote_details_general').html(json_result['vote_details_general']);
						}
						if (json_result['new_my_transaction'] == "1") {
							$('#select_input_buttons').html(json_result['select_input_buttons']);
							$('#my_bets').html(json_result['my_bets']);
							my_last_transaction_id = parseInt(json_result['my_last_transaction_id']);
							reload_compose_vote();
						}
						if (json_result['new_block'] == "1" || json_result['new_transaction'] == "1") {
							$('#current_round_table').html(json_result['current_round_table']);
							
							$('#account_value').html(json_result['account_value']);
							$('#account_value').hide();
							$('#account_value').fadeIn('medium');
							
							if (refresh_page == "wallet") var lockedfunds_details_shown = $('#lockedfunds_details').is(":visible");
							$('#wallet_text_stats').html(json_result['wallet_text_stats']);
							if (refresh_page == "wallet" && lockedfunds_details_shown) $('#lockedfunds_details').show();
							
							$('#current_round_table').hide();
							$('#current_round_table').fadeIn('fast');
							
							$('#wallet_text_stats').hide();
							$('#wallet_text_stats').fadeIn('fast');
							
							var vote_nation_details = json_result['vote_nation_details'];
							
							if (user_logged_in) {
								$('#my_current_votes').html(json_result['my_current_votes']);
								$('#my_current_votes').hide();
								$('#my_current_votes').fadeIn('fast');
							}
							
							for (var nation_id=1; nation_id<=16; nation_id++) {
								$('#vote_nation_details_'+nation_id).html(vote_nation_details[nation_id]);
							}
						}
						last_game_loop_index_applied = json_result['game_loop_index'];
					}
				}
			},
			error: function(XMLHttpRequest, textStatus, errorThrown) {
				refresh_in_progress = false;
				console.log("Game loop web request failed.");
				if (reset_next_block_text) { $('#next_block_btn').html("Next Block"); reset_next_block_text = false;}
			}
		});
	}
}
function nation_selected(nation_id) {
	if (selected_nation_id !== false) nation_deselected(selected_nation_id);
	$('#vote_nation_'+nation_id).addClass('vote_nation_box_sel');
	selected_nation_id = nation_id;
}
function nation_deselected(nation_id) {
	$('#vote_nation_'+nation_id).removeClass('vote_nation_box_sel');
	selected_nation_id = false;
}
function notification_pref_changed() {
	var notification_pref = $('#notification_preference').val();
	if (notification_pref == "email") {
		$('#notification_email').show('fast');
		$('#notification_email').focus();
	}
	else {
		$('#notification_email').hide();
	}
}
function notification_focused() {
	if (!started_checking_notification_settings) {
		check_notification_settings();
		started_checking_notification_settings = true;
	}
}
function check_notification_settings() {
	if ($('#notification_preference').val() != initial_notification_pref || $('#notification_email').val() != initial_notification_email) {
		$('#notification_save_btn').show();
	}
	else {
		$('#notification_save_btn').hide();
	}
	setTimeout("check_notification_settings();", 800);
}
function save_notification_preferences() {
	if ($('#notification_save_btn').html() == "Save Notification Settings") {
		var notification_pref = $('#notification_preference').val();
		var notification_email = $('#notification_email').val();
		$('#notification_save_btn').html("Saving...");
		$.get("/ajax/set_notification_preference.php?preference="+encodeURIComponent(notification_pref)+"&email="+encodeURIComponent(notification_email), function(result) {
			$('#notification_save_btn').html("Save Notification Settings");
			initial_notification_pref = notification_pref;
			initial_notification_email = notification_email;
			alert(result);
		});
	}
}
function alias_pref_changed() {
	var alias_pref = $('#alias_preference').val();
	if (alias_pref == "public") {
		$('#alias').show('fast');
		$('#alias').focus();
	}
	else {
		$('#alias').hide();
	}
}
function alias_focused() {
	if (!started_checking_alias_settings) {
		check_alias_settings();
		started_checking_alias_settings = true;
	}
}
function check_alias_settings() {
	if ($('#alias_preference').val() != initial_alias_pref || $('#alias').val() != initial_alias) {
		$('#alias_save_btn').show();
	}
	else {
		$('#alias_save_btn').hide();
	}
	setTimeout("check_alias_settings();", 800);
}
function save_alias_preferences() {
	if ($('#alias_save_btn').html() == "Save Privacy Settings") {
		var alias_pref = $('#alias_preference').val();
		var alias = $('#alias').val();
		$('#notification_save_btn').html("Saving...");
		$.get("/ajax/set_alias_preference.php?preference="+encodeURIComponent(alias_pref)+"&alias="+encodeURIComponent(alias), function(result) {
			$('#notification_save_btn').html("Save Privacy Settings");
			initial_alias_pref = alias_pref;
			initial_alias = alias;
			alert(result);
		});
	}
}
function show_more_performance_history() {
	if (!performance_history_loading) {
		performance_history_loading = true;
		performance_history_start_round -= 10;
		$('#performance_history').append('<div id="performance_history_'+performance_history_sections+'"></div>');
		$('#performance_history_'+performance_history_sections).html("Loading...");
		
		$.get("/ajax/performance_history.php?from_round_id="+performance_history_start_round+"&to_round_id="+(performance_history_start_round+9), function(result) {
			$('#performance_history_'+performance_history_sections).html(result);
			performance_history_sections++;
			performance_history_loading = false;
		});
	}
}
function attempt_withdrawal() {
	if ($('#withdraw_btn').html() == "Withdraw") {
		var amount = $('#withdraw_amount').val();
		var address = $('#withdraw_address').val();
		
		$('#withdraw_btn').html("Withdrawing...");
		$.get("/ajax/withdraw.php?amount="+encodeURIComponent(amount)+"&address="+encodeURIComponent(address)+"&remainder_address_id="+$('#withdraw_remainder_address_id').val(), function(result) {
			$('#withdraw_btn').html("Withdraw");
			$('#withdraw_amount').val("");
			var result_obj = JSON.parse(result);
			alert(result_obj['message']);
			refresh_if_needed();
		});
	}
}
function switch_to_game(game) {
	$.get("/ajax/switch_to_game.php?game="+game, function(result) {
		if (result == "1") {
			window.location = window.location;
		}
		else alert(result);
	});
}
function toggle_block_timing() {
	$('#toggle_timing_btn').html("Loading...");
	$.get("/ajax/toggle_block_timing.php", function(result) {
		window.location = window.location;
	});
}

var vote_inputs = new Array();
var vote_nations = new Array();
var output_amounts_need_update = false;
var nation_bet_amounts_need_update = false;
var io_id2input_index = {};
var mature_ios = new Array();
var nations = new Array();
var nation_bets = new Array();
var bet_sum = 0;

function nation(nation_id, name) {
	this.nation_id = nation_id;
	this.name = name;
	this.existing_bet_sum = 0;
	this.bet_index = false;
}
function nation_bet(bet_index, nation_id) {
	this.bet_index = bet_index;
	this.nation_id = nation_id;
	this.slider_val = 50;
	this.amount = 0
}
function mature_io(io_index, io_id, amount, create_block_id) {
	this.io_index = io_index;
	this.io_id = io_id;
	this.amount = amount;
	this.create_block_id = create_block_id;
}
function vote_input(input_index, io_id, amount, create_block_id) {
	this.input_index = input_index;
	this.io_id = io_id;
	this.amount = amount;
	this.create_block_id = create_block_id;
}
function vote_nation(nation_index, name, nation_id) {
	this.nation_index = nation_index;
	this.name = name;
	this.nation_id = nation_id;
	this.slider_val = 100;
	this.amount = 0;
}
function input_amount_sums() {
	var amount_sum = 0;
	var vote_sum = 0;
	for (var i=0; i<vote_inputs.length; i++) {
		amount_sum += vote_inputs[i].amount;
		vote_sum += (1 + last_block_id - vote_inputs[i].create_block_id)*vote_inputs[i].amount;
	}
	return [amount_sum, vote_sum];
}
function set_input_amount_sums() {
	var amount_sums = input_amount_sums();
	
	$('#input_amount_sum').html((Math.round(amount_sums[0]/Math.pow(10,6))/Math.pow(10,2)).toLocaleString()+" coins");
	
	if (payout_weight == 'coin_block') {
		$('#input_vote_sum').html((Math.round(amount_sums[1]/Math.pow(10,6))/Math.pow(10,2)).toLocaleString()+" votes");
	}
}
function render_selected_utxo(index_id) {
	var score_qty = 0;
	if (payout_weight == "coin") score_qty = vote_inputs[index_id].amount;
	else score_qty = (1 + last_block_id - vote_inputs[index_id].create_block_id)*vote_inputs[index_id].amount;
	var render_text = (Math.round(score_qty/Math.pow(10,6))/Math.pow(10,2)).toLocaleString();
	if (payout_weight == "coin") render_text += ' coins';
	else render_text += ' votes';
	render_text += ' &nbsp;&nbsp; <font style="cursor: pointer" onclick="remove_utxo_from_vote('+index_id+');">&#215;</font>';
	return render_text;
}
function render_nation_output(index_id, name) {
	var html = "";
	html += name+'&nbsp;&nbsp; <div id="output_amount_disp_'+index_id+'" style="display: inline-block;"></div> <font style="float: right; cursor: pointer" onclick="remove_nation_from_vote('+index_id+');">&#215;</font>';
	html += '<div><div id="output_threshold_'+index_id+'" class="noUiSlider"></div></div>';
	return html;
}
function render_nation_bet(bet_index, nation_id) {
	var html = nations[nation_id].name+'&nbsp;&nbsp; <div id="nation_bet_amount_disp_'+bet_index+'" style="display: inline-block;"></div> <font style="float: right; cursor: pointer" onclick="remove_nation_bet('+bet_index+');">&#215;</font>';
	html += '<div><div id="nation_bet_threshold_'+bet_index+'" class="noUiSlider"></div></div>';
	return html;
}
function add_utxo_to_vote(io_id, amount, create_block_id) {
	var already_in = false;
	for (var i=0; i<vote_inputs.length; i++) {
		if (vote_inputs[i].io_id == io_id) already_in = true;
	}
	if (!already_in) {
		var index_id = vote_inputs.length;
		vote_inputs.push(new vote_input(index_id, io_id, amount, create_block_id));
		$('#select_utxo_'+io_id).hide();
		$('#compose_vote_inputs').append('<div id="selected_utxo_'+index_id+'" class="select_utxo">'+render_selected_utxo(index_id)+'</div>');
		io_id2input_index[io_id] = index_id;
		refresh_compose_vote();
		set_input_amount_sums();
		refresh_output_amounts();
	}
}
function add_nation_to_vote(nation_id, name) {
	var index_id = vote_nations.length;
	vote_nations.push(new vote_nation(index_id, name, nation_id));
	$('#compose_vote_outputs').append('<div id="compose_vote_output_'+index_id+'" class="select_utxo">'+render_nation_output(index_id, name)+'</div>');
	
	load_nation_slider(index_id);
	
	$('#vote_confirm_'+nation_id).modal('hide');
	
	refresh_compose_vote();
	refresh_output_amounts();
}
function load_nation_slider(index_id) {
	$('#output_threshold_'+index_id).noUiSlider({
		range: [0, 100]
	   ,start: 100, step: 1
	   ,handles: 1
	   ,connect: "lower"
	   ,serialization: {
			 to: [ false, false ]
			,resolution: 1
		}
	   ,slide: function(){
			vote_nations[index_id].slider_val = parseInt($('#output_threshold_'+index_id).val());
			output_amounts_need_update = true;
	   }
	});
}
function load_nation_bet_slider(bet_index) {
	$('#nation_bet_threshold_'+bet_index).noUiSlider({
		range: [0, 100]
	   ,start: 50, step: 1
	   ,handles: 1
	   ,connect: "lower"
	   ,serialization: {
			 to: [ false, false ]
			,resolution: 1
		}
	   ,slide: function(){
			nation_bets[bet_index].slider_val = parseInt($('#nation_bet_threshold_'+bet_index).val());
			nation_bet_amounts_need_update = true;
	   }
	});
}
function remove_utxo_from_vote(index_id) {
	$('#select_utxo_'+vote_inputs[index_id].io_id).show('fast');
	io_id2input_index[vote_inputs[index_id].io_id] = false;
	
	for (var i=index_id+1; i<vote_inputs.length; i++) {
		$('#selected_utxo_'+(i-1)).html(render_selected_utxo(i-1));
		$('#selected_utxo_'+i).html('');
		vote_inputs[i-1] = vote_inputs[i];
		io_id2input_index[vote_inputs[i-1].io_id] = i-1;
	}
	$('#selected_utxo_'+(vote_inputs.length-1)).remove();
	vote_inputs.length = vote_inputs.length-1;
	set_input_amount_sums();
	refresh_compose_vote();
	refresh_output_amounts()
}
function remove_nation_from_vote(index_id) {
	for (var i=index_id+1; i<vote_nations.length; i++) {
		$('#compose_vote_output_'+(i-1)).html(render_nation_output(i-1, vote_nations[i].name));
		$('#compose_vote_output_'+i).html('');
		vote_nations[i-1] = vote_nations[i];
		load_nation_slider(i-1);
		$('#output_threshold_'+(i-1)).val(vote_nations[i-1].slider_val);
	}
	$('#compose_vote_output_'+(vote_nations.length-1)).remove();
	vote_nations.length = vote_nations.length-1;
	
	refresh_output_amounts();
}
function refresh_compose_vote() {
	if (vote_inputs.length > 0 || vote_nations.length > 0) $('#compose_vote').show('fast');
	else $('#compose_vote').hide('fast');
}
function refresh_output_amounts() {
	if (vote_nations.length > 0) {
		var input_sums = input_amount_sums();
		var coin_sum = input_sums[0];
		var score_sum = input_sums[1];
		
		var slider_sum = 0;
		for (var i=0; i<vote_nations.length; i++) {
			slider_sum += vote_nations[i].slider_val;
		}
		var coins_per_slider_val;
		if (slider_sum > 0) coins_per_slider_val = Math.floor(coin_sum/slider_sum);
		else coins_per_slider_val = 0;
		
		var output_coins_sum = 0;
		for (var i=0; i<vote_nations.length; i++) {
			var output_coins = Math.floor(coins_per_slider_val*vote_nations[i].slider_val);
			var output_score;
			if (coin_sum > 0) output_score = output_coins*(score_sum/coin_sum);
			else output_score = 0;
			
			if (i == vote_nations.length - 1) output_coins = coin_sum - output_coins_sum;
			
			var output_val = 0;
			if (payout_weight == "coin") output_val = output_coins;
			else output_val = output_score;
			var output_val_disp = (Math.round(output_val/Math.pow(10,6))/Math.pow(10,2)).toLocaleString();
			if (payout_weight == "coin") output_val_disp += " coins";
			else output_val_disp += " votes";
			$('#output_amount_disp_'+i).html(output_val_disp);
			
			vote_nations[i].amount = output_coins;
			output_coins_sum += output_coins;
		}
	}
}
function refresh_mature_io_btns() {
	var select_btn_text = "";
	for (var i=0; i<mature_ios.length; i++) {
		if (payout_weight == "coin") select_btn_text = 'Add '+(Math.round(mature_ios[i].amount/Math.pow(10,6))/Math.pow(10,2)).toLocaleString()+' coins to my vote';
		else select_btn_text = 'Cast '+(Math.round(((1 + last_block_id - mature_ios[i].create_block_id)*mature_ios[i].amount)/Math.pow(10,6))/Math.pow(10,2)).toLocaleString()+' votes';
		$('#select_utxo_'+mature_ios[i].io_id).html(select_btn_text);
	}
	for (var i=0; i<vote_inputs.length; i++) {
		$('#selected_utxo_'+i).html(render_selected_utxo(i));
	}
}
function compose_vote_loop() {
	if (output_amounts_need_update) refresh_output_amounts();
	output_amounts_need_update = false;
	setTimeout("compose_vote_loop();", 400);
}
function confirm_compose_vote() {
	if (vote_inputs.length > 0) {
		if (vote_nations.length > 0) {
			$('#confirm_compose_vote_btn').html("Loading...");
			
			var place_vote_url = "/ajax/place_vote.php?io_ids=";
			for (var i=0; i<vote_inputs.length; i++) {
				place_vote_url += vote_inputs[i].io_id;
				if (i != vote_inputs.length-1) place_vote_url += ",";
			}
			
			place_vote_url += "&nation_ids=";
			var amounts_url = "&amounts=";
			
			for (var i=0; i<vote_nations.length; i++) {
				place_vote_url += vote_nations[i].nation_id;
				if (i != vote_nations.length-1) place_vote_url += ",";
				
				amounts_url += vote_nations[i].amount;
				if (i != vote_nations.length-1) amounts_url += ",";
			}
			place_vote_url += amounts_url;
			
			$.get(place_vote_url, function(result) {
				$('#confirm_compose_vote_btn').html("Submit Voting Transaction");
				
				var result_parts = result.split("=====");
				if (result_parts[0] == "0") {
					refresh_if_needed();
					$('#compose_vote_success').html(result_parts[1]);
					$('#compose_vote_success').slideDown('slow');
					setTimeout("$('#compose_vote_success').slideUp('fast');", 2500);
					
					for (var i=0; i<vote_nations.length; i++) {
						$('#compose_vote_output_'+i).remove();
					}
					vote_nations.length = 0;
					
					for (var i=0; i<vote_inputs.length; i++) {
						$('#selected_utxo_'+i).remove();
					}
					vote_inputs.length = 0;
					
					setTimeout("refresh_compose_vote();", 3000);
				}
				else {
					$('#compose_vote_errors').html(result_parts[1]);
					$('#compose_vote_errors').slideDown('slow');
					setTimeout("$('#compose_vote_errors').slideUp('fast');", 2500);
				}
			});
		}
		else {
			alert("First, please add the empires that you wish to vote for.");
		}
	}
	else {
		alert("First, please add coin inputs to your voting transaction.");
	}
}
function reload_compose_vote() {
	for (var i=0; i<vote_inputs.length; i++) {
		$('#selected_utxo_'+i).remove();
	}
	vote_inputs.length = 0;
	
	$('#select_input_buttons').find('.select_utxo').each(function() {
		$(this).hide();
	});
	if (mature_io_ids_csv == "") {
		$('#select_input_buttons_msg').html("You don't have any coins available to vote right now.");
	}
	else {
		$('#select_input_buttons_msg').html("To compose a voting transaction, please add money with the boxes below and then select the nations that you want to vote for.");
	}
	refresh_visible_inputs();
}
function refresh_visible_inputs() {
	var mature_io_ids = mature_io_ids_csv.split(",");
	for (var i=0; i<mature_io_ids.length; i++) {
		if (typeof io_id2input_index[mature_io_ids[i]] == 'undefined' || io_id2input_index[mature_io_ids[i]] === false) {
			$('#select_utxo_'+mature_io_ids[i]).show();
		}
	}
}
function bet_loop() {
	if (nation_bet_amounts_need_update || bet_sum != parseFloat($('#bet_amount').val())) {
		refresh_nation_bet_amounts();
		nation_bet_amounts_need_update = false;
		bet_sum = parseFloat($('#bet_amount').val());
	}
	setTimeout("bet_loop();", 400);
}
function refresh_nation_bet_amounts() {
	if (nation_bets.length > 0) {
		var coin_sum = $('#bet_amount').val()
		if (coin_sum == '') coin_sum = 0;
		else coin_sum = Math.floor(parseFloat(coin_sum)*Math.pow(10,8));
		
		var slider_sum = 0;
		for (var i=0; i<nation_bets.length; i++) {
			slider_sum += nation_bets[i].slider_val;
		}
		var coins_per_slider_val;
		if (slider_sum > 0) coins_per_slider_val = Math.floor(coin_sum/slider_sum);
		else coins_per_slider_val = 0;
		
		var bet_coins_sum = 0;
		for (var i=0; i<nation_bets.length; i++) {
			var bet_coins = Math.floor(coins_per_slider_val*nation_bets[i].slider_val);
			
			if (i == nation_bets.length - 1) bet_coins = coin_sum - bet_coins_sum;
			
			var output_val_disp = (Math.round(bet_coins/Math.pow(10,6))/Math.pow(10,2)).toLocaleString()+" coins";
			if (coin_sum > 0) output_val_disp += " ("+(Math.round(1000*bet_coins/coin_sum)/10)+"%)";
			$('#nation_bet_amount_disp_'+i).html(output_val_disp);
			
			nation_bets[i].amount = bet_coins;
			bet_coins_sum += bet_coins;
		}
		
		update_bet_chart();
	}
}
function place_bet() {
	if ($('#bet_confirm_btn').html() == "Place Bet") {
		var round = $('#bet_round').val();
		var amounts_csv = "";
		var nations_csv = "";
		
		if (parseInt(round) > 0) {
			$('#bet_confirm_btn').html("Loading...");
			
			for (var i=0; i<nation_bets.length; i++) {
				amounts_csv += nation_bets[i].amount;
				nations_csv += nation_bets[i].nation_id;
				if (i != nation_bets.length-1) {
					amounts_csv += ",";
					nations_csv += ",";
				}
			}
			
			$.get("/ajax/place_bets.php?nations="+nations_csv+"&amounts="+amounts_csv+"&round="+round, function(result) {
				$('#bet_confirm_btn').html("Place Bet");
				
				var json_result = JSON.parse(result);
				alert(json_result['message']);
				
				if (json_result['result_code'] == 11) {
					$('#bet_round').val("");
					$('#bet_amount').val("");
					$('#nation_bet_disp').html("");
					$('#round_odds_chart').html("");
					$('#round_odds_stats').html("");
					$('#bet_charts').hide();
					nation_bets.length = 0;
				}
			});
		}
		else alert('You need to select a round first.');
	}
}
function add_bet_nation() {
	var nation_id = $('#bet_nation').val();
	add_bet_nation_by_id(nation_id);
	$('#bet_nation').val("");
	refresh_nation_bet_amounts();
}
function add_bet_nation_by_id(nation_id) {
	if (nations[nation_id].bet_index === false) {
		var bet_index = nation_bets.length;
		$('#nation_bet_disp').append('<div id="nation_bet_'+bet_index+'" class="select_utxo">'+render_nation_bet(bet_index, nation_id)+'</div>');
		nation_bets.push(new nation_bet(bet_index, nation_id));
		nations[nation_id].bet_index = bet_index;
		load_nation_bet_slider(bet_index);
	}
}
function remove_nation_bet(bet_index) {
	nations[nation_bets[bet_index].nation_id].bet_index = false;
	
	for (var i=bet_index+1; i<nation_bets.length; i++) {
		$('#nation_bet_'+(i-1)).html(render_nation_bet(i-1, nation_bets[i].nation_id));
		$('#nation_bet_'+i).html('');
		nation_bets[i].bet_index = nation_bets[i].bet_index-1;
		nations[nation_bets[i].nation_id].bet_index = nations[nation_bets[i].nation_id].bet_index-1;
		nation_bets[i-1] = nation_bets[i];
		load_nation_bet_slider(i-1);
		$('#nation_bet_threshold_'+(i-1)).val(nation_bets[i-1].slider_val);
	}
	$('#nation_bet_'+(nation_bets.length-1)).remove();
	nation_bets.length = nation_bets.length-1;
	
	refresh_nation_bet_amounts();
}
function add_all_bet_nations() {
	for (var i=0; i<=16; i++) {
		add_bet_nation_by_id(i);
	}
	refresh_nation_bet_amounts();
}
var last_round_shown;
var round_sections_shown = 1;

function show_more_rounds_complete() {
	if ($('#show_more_link').html() == "Show More") {
		$('#show_more_link').html("Loading...");
		$.get("/ajax/show_rounds_complete.php?from_round_id="+(last_round_shown-1), function(result) {
			$('#show_more_link').html("Show More");
			var json_result = JSON.parse(result);
			if (parseInt(json_result[0]) > 0) last_round_shown = parseInt(json_result[0]);
			$('#rounds_complete').append('<div id="rounds_complete_'+round_sections_shown+'">'+json_result[1]+'</div>');
			round_sections_shown++;
		});
	}
}

var nation_id2chart_index = {};
var existingBetChartData = false;

function bet_round_changed() {
	var round_id = $('#bet_round').val();
	
	$('#bet_charts').hide('fast');
	
	$.get("/ajax/bet_round_details.php?round_id="+round_id, function(result) {
		$('#bet_charts').slideDown('fast');
		
		var json_result = JSON.parse(result);
		existingBetChartData = json_result[0];
		
		for (var i=0; i<existingBetChartData.length; i++) {
			nations[existingBetChartData[i]['nation_id']].existing_bet_sum = parseInt(existingBetChartData[i]['amount']);
		}
		
		$('#round_odds_stats').html(json_result[1]);
		
		update_bet_chart();
	});
}
function update_bet_chart() {
	if (existingBetChartData.length > 0) {
		var chartData = new Array();
		chartData.push(['Empire', 'Coins Staked']);
		
		var all_bets_sum = 0;
		
		for (var i=0; i<existingBetChartData.length; i++) {
			var nation_id = existingBetChartData[i]['nation_id'];
			var my_bet_amount = 0;
			if (nations[nation_id].bet_index !== false) my_bet_amount = nation_bets[nations[nation_id].bet_index].amount;
			var this_bet_amount = parseInt(existingBetChartData[i]['amount'])+my_bet_amount;
			all_bets_sum += this_bet_amount;
			chartData.push([existingBetChartData[i]['name'], Math.round(this_bet_amount/Math.pow(10,6))/Math.pow(10,2)]);
			nation_id2chart_index[nation_id] = i;
		}
		
		for (var i=0; i<=16; i++) {
			var this_bet_amount = nations[i].existing_bet_sum;
			if (nations[i].bet_index !== false) this_bet_amount += nation_bets[nations[i].bet_index].amount;
			
			if (all_bets_sum > 0) $('#bet_nation_pct_'+i).html(Math.round(100*100*this_bet_amount/all_bets_sum)/100+"%");
			else $('#bet_nation_pct_'+i).html("0.00%");
			
			if (this_bet_amount > 0) $('#bet_nation_mult_'+i).html("&#215;"+Math.round(100*all_bets_sum/this_bet_amount)/100);
			else $('#bet_nation_mult_'+i).html("");
		}
		
		var data = google.visualization.arrayToDataTable(chartData);
		var options = {
			legend: {position: 'none'}
		};
		var chart = new google.visualization.PieChart(document.getElementById('round_odds_chart'));
		chart.draw(data, options);
	}
	else {
		$('#round_odds_chart').html("");
	}
}
