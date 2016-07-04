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
		
		window.location = '/wallet/';
	});
}
function start_vote(nation_id) {
	if ((last_block_id+1)%10 != 0) {
		$('#vote_confirm_'+nation_id).modal('toggle');
		$('#vote_details_'+nation_id).html($('#vote_details_general').html());
		$('#vote_amount_'+nation_id).focus();
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
function refresh_if_needed() {
	if (!refresh_in_progress) {
		refresh_in_progress = true;
		
		var check_activity_url = "/ajax/check_new_activity.php?last_block_id="+last_block_id+"&last_transaction_id="+last_transaction_id;
		if (refresh_page == "wallet") check_activity_url += "&performance_history_sections="+performance_history_sections;
		
		$.get(check_activity_url, function(result) {
			if (refresh_page == "wallet" && result == "0") {
				window.location = '/wallet/?do=logout';
			}
			else {
				refresh_in_progress = false;
				var json_result = $.parseJSON(result);
				if (json_result['new_block'] == "1") {
					last_block_id = parseInt(json_result['last_block_id']);
					
					$('#account_value').html(json_result['account_value']);
					$('#account_value').hide();
					$('#account_value').fadeIn('medium');
					
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
					}
				}
				if (json_result['new_transaction'] == "1") {
					last_transaction_id = parseInt(json_result['last_transaction_id']);
					if (user_logged_in) $('#vote_details_general').html(json_result['vote_details_general']);
				}
				if (json_result['new_block'] == "1" || json_result['new_transaction'] == "1") {
					$('#current_round_table').html(json_result['current_round_table']);
					
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
		});
	}
	setTimeout("refresh_if_needed();", 2000);
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
		$.get("/ajax/withdraw.php?amount="+encodeURIComponent(amount)+"&address="+encodeURIComponent(address), function(result) {
			$('#withdraw_btn').html("Withdraw");
			var result_obj = JSON.parse(result);
			alert(result_obj['message']);
			refresh_if_needed();
		});
	}
}