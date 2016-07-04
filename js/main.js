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
		
		var check_activity_url = "/ajax/check_new_activity.php?refresh_page="+refresh_page+"&last_block_id="+last_block_id+"&last_transaction_id="+last_transaction_id+"&my_last_transaction_id="+my_last_transaction_id+"&mature_io_ids_csv="+mature_io_ids_csv;
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
					if (json_result['new_block'] == "1") {
						last_block_id = parseInt(json_result['last_block_id']);
						
						if (refresh_page == "wallet") {
							if ((last_block_id+1)%16 == 0) {
								$('#vote_popups').slideUp('medium');
								$('#vote_popups_disabled').slideDown('fast');
							}
							else {
								$('#vote_popups').show('fast');
								$('#vote_popups_disabled').hide('fast');
							}
							
							if (last_block_id%10 == 0) {
								for (var i=1; i<performance_history_sections; i++) {
									$('#performance_history_'+i).html("");
								}
								$('#performance_history_0').html(json_result['performance_history']);
								$('#performance_history_0').hide();
								$('#performance_history_0').fadeIn('fast');
								
								performance_history_start_round = json_result['performance_history_start_round'];
								
								tab_clicked(2);
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
		console.log(amount);
		$('#withdraw_btn').html("Withdrawing...");
		$.get("/ajax/withdraw.php?amount="+encodeURIComponent(amount)+"&address="+encodeURIComponent(address), function(result) {
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
var io_id2input_index = {};
var mature_ios = new Array();

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
				console.log(result);
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
