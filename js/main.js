// Class Definitions
var Image = function (id) {
	this.imageId = id;
	this.imageSrc = '/images/carousel/'+id+'.jpg';
};
var ImageCarousel = function (containerElementId) {
	this.numPhotos = 16;
	this.currentPhotoId = -1;
	this.slideTime = 5000;
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
};
var chatWindow = function(chatWindowId, toUserId) {
	this.chatWindowId = chatWindowId;
	this.toUserId = toUserId;
	
	this.initialize = function() {};
};
var option = function(parent_event, option_index, option_id, db_option_index, name, points, has_votingaddr, image_url) {
	this.parent_event = parent_event;
	this.option_index = option_index;
	this.option_id = option_id;
	this.db_option_index = db_option_index;
	this.name = name;
	this.points = points;
	this.has_votingaddr = has_votingaddr;
	this.image_url = image_url
	
	this.votes = 0;
	this.unconfirmed_votes = 0;
	this.hypothetical_votes = 0;
	
	this.effective_votes = 0;
	this.unconfirmed_effective_votes = 0;
	this.hypothetical_effective_votes = 0;
	
	this.burn_amount = 0;
	this.unconfirmed_burn_amount = 0;
	this.hypothetical_burn_amount = 0;
	
	this.effective_burn_amount = 0;
	this.unconfirmed_effective_burn_amount = 0;
	this.hypothetical_effective_burn_amount = 0;
	
	this.parent_event.option_id2option_index[option_id] = option_index;
	this.parent_event.game.option_has_votingaddr[option_id] = has_votingaddr;
	
	option_id2option_index[option_id] = option_index;
	option_index2option_id[option_index] = option_id;
};
var chain_io = function(chain_io_index, io_id, amount, create_block_id) {
	this.chain_io_index = chain_io_index;
	this.io_id = io_id;
	this.amount = amount;
	this.create_block_id = create_block_id;
	this.game_ios = new Array();
	
	this.votes_at_block = function(block_id) {
		var votes = 0;
		
		for (var i=0; i<this.game_ios.length; i++) {
			if (games[0].payout_weight == "coin") votes += this.game_ios[i].amount;
			else if (games[0].payout_weight == "coin_round") votes += (games[0].block_to_round(block_id) - games[0].block_to_round(this.game_ios[i].create_block_id))*this.game_ios[i].amount;
			else votes += (block_id - this.game_ios[i].create_block_id)*this.game_ios[i].amount;
		}
		return votes;
	};
	
	this.game_amount_sum = function() {
		var amount_sum = 0;
		
		for (var i=0; i<this.game_ios.length; i++) {
			amount_sum += this.game_ios[i].amount;
		}
		return amount_sum;
	};
};
var game_io = function(game_io_id, amount, create_block_id) {
	this.game_io_id = game_io_id;
	this.amount = amount;
	this.create_block_id = create_block_id;
};
var vote_input = function(input_index, ref_io) {
	this.input_index = input_index;
	this.ref_io = ref_io;
};
var vote_output = function(option_index, name, option_id, event_index) {
	this.option_index = option_index;
	this.name = name;
	this.option_id = option_id;
	this.event_index = event_index;
	this.slider_val = 50;
	this.amount = 0;
};

// Global Functions
function rtrim(str, charlist) {
  charlist = !charlist ? ' \\s\u00A0' : (charlist + '')
    .replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:])/g, '\\$1');
  var re = new RegExp('[' + charlist + ']+$', 'g');
  return (str + '')
    .replace(re, '');
}
function format_coins(amount) {
	if (amount > Math.pow(10, 10)) {
		return parseFloat((amount/Math.pow(10, 9)).toPrecision(5))+"B";
	}
	else if (amount > Math.pow(10, 7)) {
		return parseFloat((amount/Math.pow(10, 6)).toPrecision(5))+"M";
	}
	else if (amount > Math.pow(10, 4)) {
		return parseFloat((amount/Math.pow(10, 3)).toPrecision(5))+"k";
	}
	else if (amount == 0) return "0";
	else if (amount < 1) {
		return rtrim((amount).toPrecision(5), "0.");
	}
	else return parseFloat((amount).toPrecision(5));
}
function explorer_search() {
	var search_term = $('#explorer_search').val();
	var search_url = "/ajax/explorer_search.php?";
	if (typeof games !== "undefined" && games.length > 0) search_url += "game_id="+games[0].game_id+"&";
	if (typeof blockchain_id !== "undefined") search_url += "blockchain_id="+blockchain_id+"&";
	search_url += "search_term="+search_term;
	
	$.get(search_url, function(result) {
		var result_obj = JSON.parse(result);
		if (result_obj['status_code'] == 1) window.location = result_obj['message'];
		else alert(result_obj['message']);
	});
}

// Initialize Variables
var chatWindows = new Array();
var userId2ChatWindowId = new Array();
var visibleChatWindows = 0;
var option_id2option_index = {};
var option_index2option_id = {};

// OBJECT: Wallet
var last_event_index_shown;
var event_outcome_sections_shown = 1;

function openChatWindow(userId) {
	if (typeof userId2ChatWindowId[userId] === 'undefined' || userId2ChatWindowId[userId] === false) {
		var chatWindowId = chatWindows.length;
		newChatWindow(chatWindowId, userId);
	}
}
function newChatWindow(chatWindowId, userId) {
	chatWindows[chatWindowId] = new chatWindow(chatWindowId, userId);
	userId2ChatWindowId[userId] = chatWindowId;
	
	$('#chatWindows').append('<div id="chatWindow'+chatWindowId+'" class="chatWindow"></div>');
	$('#chatWindow'+chatWindowId).css("right", chatWindowId*230);
	$('#chatWindow'+chatWindowId).html(baseChatWindow(chatWindowId));
	renderChatWindow(chatWindowId);
	$('#chatWindowWriter'+chatWindowId).focus();
}
function closeChatWindow(chatWindowId) {
	userId2ChatWindowId[chatWindows[chatWindowId].toUserId] = false;
	for (var i=chatWindowId+1; i<chatWindows.length; i++) {
		userId2ChatWindowId[chatWindows[i].toUserId] = i-1;
		chatWindows[i-1] = chatWindows[i];
		$('#chatWindow'+(i-1)).html(baseChatWindow(i-1));
		renderChatWindow(i-1);
	}
	$('#chatWindow'+(chatWindows.length-1)).remove();
	chatWindows.length = chatWindows.length-1;
}
function baseChatWindow(chatWindowId) {
	return $('#chatWindowTemplate').html().replace(/CHATID/g, chatWindowId);
}
function renderChatWindow(chatWindowId) {
	$('#chatWindowTitle'+chatWindowId).html('Loading...');
	$('#chatWindowContent'+chatWindowId).html('');
	$.get("/ajax/chat.php?action=fetch&game_id="+games[0].game_id+"&user_id="+chatWindows[chatWindowId].toUserId, function(result) {
		var result_obj = JSON.parse(result);
		$('#chatWindowTitle'+chatWindowId).html(result_obj['username']);
		$('#chatWindowContent'+chatWindowId).html(result_obj['content']);
		$('#chatWindowContent'+chatWindowId).scrollTop($('#chatWindowContent'+chatWindowId)[0].scrollHeight);
	});
}
function sendChatMessage(chatWindowId) {
	var message = $('#chatWindowWriter'+chatWindowId).val();
	$('#chatWindowSendBtn'+chatWindowId).html("...");
	$('#chatWindowWriter'+chatWindowId).val("");
	$.get("/ajax/chat.php?action=send&game_id="+games[0].game_id+"&user_id="+chatWindows[chatWindowId].toUserId+"&message="+encodeURIComponent(message), function(result) {
		$('#chatWindowSendBtn'+chatWindowId).html("Send");
		var result_obj = JSON.parse(result);
		$('#chatWindowContent'+chatWindowId).html(result_obj['content']);
		$('#chatWindowContent'+chatWindowId).scrollTop($('#chatWindowContent'+chatWindowId)[0].scrollHeight);
	});
}
$(window).keydown(function(e){
	var key = (e.which) ? e.which : e.keyCode;
	if (key == 13) {
		if ($(":focus").attr("class") == "chatWindowWriter") {
			var chatWindowId = $(":focus").attr("id").replace(/chatWindowWriter/g, '');
			sendChatMessage(chatWindowId);
		}
	}
});
function tab_clicked(index_id) {
	if (current_tab !== false) {
		$('#tabcell'+current_tab).removeClass("active");
		$('#tabcontent'+current_tab).hide();
	}
	
	$('#tabcell'+index_id).addClass("active");
	$('#tabcontent'+index_id).show();
	
	current_tab = index_id;
	
	if (index_id == 1) {
		refresh_players();
	}
}
function refresh_players() {
	$('#tabcontent1').html("Loading...");
	$.get("/ajax/show_players.php?game_id="+games[0].game_id, function(result) {
		$('#tabcontent1').html(result);
	});
}
function claim_from_faucet() {
	var faucet_btn_txt = $('#faucet_btn').html();
	$('#faucet_btn').html("Loading...");
	
	$.get("/ajax/faucet.php?game_id="+games[0].game_id+"&action=claim", function(result) {
		$('#faucet_btn').html(faucet_btn_txt);
		var result_obj = JSON.parse(result);
		
		if (result_obj['status_code'] == "1") {
			window.location = '/wallet/'+games[0].game_url_identifier+'/';
			return false;
		}
		else alert(result_obj['message']);
		
		games[0].refresh_if_needed();
	});
}
function rank_check_all_changed() {
	var set_checked = false;
	if ($('#rank_check_all').is(":checked")) set_checked = true;
	for (var i=1; i<=games[0].num_voting_options; i++) {
		$('#by_rank_'+i).prop("checked", set_checked);
	}
}
function vote_on_block_all_changed() {
	var set_checked = false;
	if ($('#vote_on_block_all').is(":checked")) set_checked = true;
	for (var i=1; i<=games[0].game_round_length; i++) {
		$('#vote_on_block_'+i).prop("checked", set_checked);
	}
}
function by_entity_reset_pct() {
	for (var option_id=1; option_id<=games[0].num_voting_options; option_id++) {
		$('#option_pct_'+option_id).val("0");
	}
}
function loop_event() {
	/*var option_pct_sum = 0;
	for (var i=0; i<games[0].num_voting_options; i++) {
		var temp_pct = parseInt($('#option_pct_'+i).val());
		if (temp_pct && !$('#option_pct_'+i).is(":focus") && temp_pct != $('#option_pct_'+i).val()) {
			$('#option_pct_'+i).val(temp_pct);
		}
		if (temp_pct) option_pct_sum += temp_pct;
	}
	if (option_pct_sum <= 100 && option_pct_sum >= 0) {
		$('#option_pct_subtotal').html("<font class='greentext'>"+option_pct_sum+"/100 allocated, "+(100-option_pct_sum)+"% left</font>");
	}
	else {
		$('#option_pct_subtotal').html("<font class='redtext'>"+option_pct_sum+"/100 allocated</font>");
	}*/
	
	setTimeout("loop_event();", 1000);
}
function next_block() {
	if ($('#next_block_btn').html() == "Next Block") {
		$('#next_block_btn').html("Loading...");
		
		$.get("/ajax/next_block.php?game_id="+games[0].game_id, function(result) {
			games[0].refresh_if_needed();
		});
	}
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
function save_notification_preferences() {
	var btn_text = '<i class="fas fa-check-circle"></i> &nbsp; Save Notification Settings';
	
	if ($('#notification_save_btn').html() == btn_text) {
		var notification_pref = $('#notification_preference').val();
		var notification_email = $('#notification_email').val();
		$('#notification_save_btn').html("Saving...");
		
		$.get("/ajax/set_notification_preference.php?game_id="+games[0].game_id+"&preference="+encodeURIComponent(notification_pref)+"&email="+encodeURIComponent(notification_email), function(result) {
			$('#notification_save_btn').html(btn_text);
			initial_notification_pref = notification_pref;
			initial_notification_email = notification_email;
			alert(result);
		});
	}
}
function attempt_withdrawal() {
	var amount = $('#withdraw_amount').val();
	var address = $('#withdraw_address').val();
	
	var initial_btn_text = $('#withdraw_btn').html();
	var loading_btn_text = "Sending...";
	
	if (initial_btn_text != loading_btn_text) {
		$('#withdraw_btn').html(loading_btn_text);
		
		$.get("/ajax/withdraw.php?game_id="+games[0].game_id+"&amount="+encodeURIComponent(amount)+"&address="+encodeURIComponent(address)+"&remainder_address_id="+$('#withdraw_remainder_address_id').val()+"&fee="+encodeURIComponent($('#withdraw_fee').val()), function(result) {
			var result_obj = JSON.parse(result);
			
			$('#withdraw_btn').html(initial_btn_text);
			$('#withdraw_amount').val("");
			
			$('#withdraw_message').removeClass("redtext");
			$('#withdraw_message').removeClass("greentext");
			
			$('#withdraw_message').show('fast');
			$('#withdraw_message').html(result_obj['message']);
			
			if (result_obj['status_code'] == 1) $('#withdraw_message').addClass("greentext");
			else $('#withdraw_message').addClass("redtext");
			
			setTimeout("$('#withdraw_message').slideUp('fast');", 5000);
		});
	}
	else alert("Already sending");
}
function input_amount_sums() {
	var io_sum = 0;
	var amount_sum = 0;
	var vote_sum = 0;
	
	for (var i=0; i<vote_inputs.length; i++) {
		io_sum += vote_inputs[i].ref_io.amount;
		vote_sum += vote_inputs[i].ref_io.votes_at_block(games[0].last_block_id+1);
		amount_sum += vote_inputs[i].ref_io.game_amount_sum();
	}
	return [io_sum, amount_sum, vote_sum];
}

var set_usable_coins = false;
function set_input_amount_sums() {
	var amount_sums = input_amount_sums();
	var burn_amount = 0;
	if ($('#compose_burn_amount').val() != "") burn_amount += parseFloat($('#compose_burn_amount').val())*Math.pow(10,games[0].decimal_places);
	
	var input_disp = format_coins(amount_sums[1]/Math.pow(10,games[0].decimal_places));
	if (input_disp == '1') input_disp += ' '+games[0].coin_name;
	else input_disp += ' '+games[0].coin_name_plural;
	$('#input_amount_sum').html(input_disp);
	
	if (games[0].payout_weight == 'coin') $('#input_amount_sum').show();
	else {
		if (games[0].inflation == "exponential") {
			var coin_equiv = Math.round(amount_sums[2]*games[0].coins_per_vote);
			var coin_disp = format_coins((coin_equiv+burn_amount)/Math.pow(10,games[0].decimal_places));
			
			var render_text = coin_disp+" ";
			if (coin_disp == '1') render_text += games[0].coin_name;
			else render_text += games[0].coin_name_plural;
			$('#input_vote_sum').html(render_text);
			
			if ($('#compose_burn_amount').val() == "0" || $('#compose_burn_amount').val() == "" || $('#compose_burn_amount').val() == set_usable_coins) {
				var usable_coins = Math.floor(amount_sums[1]*0.9/Math.pow(10,games[0].decimal_places));
				set_usable_coins = usable_coins;
				$('#compose_burn_amount').val(usable_coins);
			}
		}
		else {
			$('#input_vote_sum').html(format_coins(amount_sums[2]/Math.pow(10,games[0].decimal_places))+" votes");
		}
	}
}
function render_vote_input(index_id) {
	if (games[0].logo_image_url != "") {
		return "";
	}
	else {
		var votes = vote_inputs[index_id].ref_io.votes_at_block(games[0].last_block_id);
		var render_text;
		
		if (games[0].inflation == "exponential") {
			var coin_equiv = Math.round(votes*games[0].coins_per_vote);
			var disp_coins = coin_equiv;
			if (games[0].default_betting_mode == "principal") disp_coins += vote_inputs[index_id].ref_io.game_amount_sum();
			var coin_disp = format_coins(disp_coins/Math.pow(10,games[0].decimal_places));
			
			render_text = coin_disp+" ";
			if (coin_disp == '1') render_text += games[0].coin_name;
			else render_text += games[0].coin_name_plural;
		}
		else {
			render_text = format_coins(votes/Math.pow(10,games[0].decimal_places))+' ';
			if (games[0].payout_weight == "coin") {
				if (render_text == '1') render_text += games[0].coin_name;
				else render_text += games[0].coin_name_plural;
			}
			else render_text += ' votes';
		}
		return render_text;
	}
}
function render_option_output(index_id, name) {
	var html = "";
	html += name+'&nbsp;&nbsp; <div id="output_amount_disp_'+index_id+'" class="output_amount_disp"></div> <font class="output_removal_link" onclick="remove_option_from_vote('+index_id+');">&#215;</font>';
	html += '<div><div id="output_threshold_'+index_id+'" class="noUiSlider"></div></div>';
	return html;
}
function add_utxo_to_vote(io_index) {
	var index_id = vote_inputs.length;

	var already_in = false;
	for (var i=0; i<vote_inputs.length; i++) {
		if (vote_inputs[i].io_id == chain_ios[io_index].io_id) already_in = true;
	}
	if (!already_in) {
		vote_inputs.push(new vote_input(index_id, chain_ios[io_index]));
		
		$('#select_utxo_'+chain_ios[io_index].io_id).hide('fast');
		
		var select_btn_html = '<div id="selected_utxo_'+index_id+'" onclick="remove_utxo_from_vote('+index_id+');" class="select_utxo';
		if (games[0].logo_image_url != "") select_btn_html += ' select_utxo_image';
		select_btn_html += ' btn btn-primary btn-sm">'+render_vote_input(index_id)+'</div>';
		$('#compose_vote_inputs').append(select_btn_html);
		
		io_id2input_index[chain_ios[io_index].io_id] = index_id;
		
		refresh_compose_vote();
		set_input_amount_sums();
		refresh_output_amounts();
	}
}
function add_all_utxos_to_vote() {
	for (var i=0; i<chain_ios.length; i++) {
		setTimeout("add_utxo_to_vote("+i+");", i*50);
	}
	setTimeout("refresh_compose_vote(); set_input_amount_sums(); refresh_output_amounts();", chain_ios.length*50);
}
function load_option_slider(index_id) {
	$('#output_threshold_'+index_id).noUiSlider({
		range: [0, 100]
	   ,start: 50, step: 1
	   ,handles: 1
	   ,connect: "lower"
	   ,serialization: {
			 to: [ false, false ]
			,resolution: 1
		}
	   ,slide: function(){
			vote_outputs[index_id].slider_val = parseInt($('#output_threshold_'+index_id).val());
			output_amounts_need_update = true;
	   }
	});
}
function remove_all_utxos_from_vote() {
	for (var i=0; i<vote_inputs.length; i++) {
		setTimeout("remove_utxo_from_vote(0);", i*50);
	}
}
function remove_utxo_from_vote(index_id) {
	var io_id = vote_inputs[index_id].ref_io.io_id;
	$('#select_utxo_'+io_id).show('fast');
	
	io_id2input_index[io_id] = false;
	
	for (var i=index_id; i<vote_inputs.length-1; i++) {
		vote_inputs[i] = vote_inputs[i+1];
		
		io_id2input_index[vote_inputs[i].ref_io.io_id] = i;
		
		$('#selected_utxo_'+i).html(render_vote_input(i));
	}
	$('#selected_utxo_'+(vote_inputs.length-1)).remove();
	vote_inputs.length = vote_inputs.length-1;
	set_input_amount_sums();
	refresh_compose_vote();
	refresh_output_amounts();
}
function remove_option_from_vote(index_id) {
	for (var i=index_id+1; i<vote_outputs.length; i++) {
		$('#compose_vote_output_'+(i-1)).html(render_option_output(i-1, vote_outputs[i].name));
		$('#compose_vote_output_'+i).html('');
		vote_outputs[i-1] = vote_outputs[i];
		load_option_slider(i-1);
		$('#output_threshold_'+(i-1)).val(vote_outputs[i-1].slider_val);
	}
	$('#compose_vote_output_'+(vote_outputs.length-1)).remove();
	vote_outputs.length = vote_outputs.length-1;
	
	refresh_output_amounts();
}
function refresh_compose_vote() {
	if (vote_inputs.length > 0 || vote_outputs.length > 0) $('#compose_vote').show('fast');
	else $('#compose_vote').hide('fast');
}
function refresh_all_inputs() {
	var my_effective_votes=0;
	var utxo_max_effective_votes=0;
	var effectiveness_factor = games[0].block_id_to_effectiveness_factor(games[0].last_block_id+1);

	for (var i=0; i<chain_ios.length; i++) {
		var votes = chain_ios[i].votes_at_block(games[0].last_block_id+1);
		var effective_votes = Math.round(votes*effectiveness_factor);
		
		if (effective_votes > utxo_max_effective_votes) {
			utxo_max_effective_votes = effective_votes;
		}
		my_effective_votes += effective_votes;
	}

	for (var i=0; i<vote_inputs.length; i++) {
		//var votes = games[0].votes_from_io(vote_inputs[i].amount, vote_inputs[i].create_block_id);
		//var height = Math.round(Math.sqrt(effectiveness_factor)*games[0].utxo_max_height*votes/games[0].utxo_max_effective_votes);
		//$('#selected_utxo_'+i).css("height", height+"px");
		//$('#selected_utxo_'+i).css("width", height+"px");
	}
	games[0].my_effective_votes = my_effective_votes;
	games[0].utxo_max_effective_votes = utxo_max_effective_votes;
	refresh_mature_io_btns();
}

var burn_io_amount = false;

function finish_refresh_output_amounts() {
	if (vote_outputs.length > 0) {
		var input_sums = input_amount_sums();
		
		var io_amount = input_sums[0];
		var game_amount = input_sums[1];
		var votes = input_sums[2];
		var inflation_amount = votes*games[0].coins_per_vote;
		var nonfee_amount = io_amount - (games[0].fee_amount*Math.pow(10, games[0].decimal_places));
		var effectiveness_factor = games[0].block_id_to_effectiveness_factor(games[0].last_block_id+1);
		var effective_votes = Math.round(votes*effectiveness_factor);
		
		var burn_amount = 0;
		burn_io_amount = 0;
		if ($('#compose_burn_amount').val() != "") {
			burn_amount = parseInt(parseFloat($('#compose_burn_amount').val())*Math.pow(10, games[0].decimal_places));
			burn_io_amount = Math.ceil(nonfee_amount*burn_amount/game_amount);
		}
		var effective_burn_amount = Math.round(burn_amount*effectiveness_factor);
		
		var io_nondestroy_amount = nonfee_amount-burn_io_amount;
		var game_amount_bet = burn_amount+inflation_amount;
		
		var slider_sum = 0;
		for (var i=0; i<vote_outputs.length; i++) {
			slider_sum += vote_outputs[i].slider_val;
		}
		
		for (var i=0; i<games[0].events.length; i++) {
			games[0].events[i].sum_hypothetical_votes = 0;
			games[0].events[i].sum_hypothetical_effective_votes = 0;
			games[0].events[i].sum_hypothetical_burn_amount = 0;
			games[0].events[i].sum_hypothetical_effective_burn_amount = 0;
			
			for (var j=0; j<games[0].events[i].options.length; j++) {
				games[0].events[i].options[j].hypothetical_votes = 0;
				games[0].events[i].options[j].hypothetical_effective_votes = 0;
				games[0].events[i].options[j].hypothetical_burn_amount = 0;
				games[0].events[i].options[j].hypothetical_effective_burn_amount = 0;
			}
		}
		
		var output_io_sum = 0;
		var this_event;
		
		for (var i=0; i<vote_outputs.length; i++) {
			var output_io_amount = Math.round(io_nondestroy_amount*vote_outputs[i].slider_val/slider_sum);
			if (i == vote_outputs.length-1) output_io_amount = io_nondestroy_amount - output_io_sum;
			
			var output_votes = Math.round(votes*vote_outputs[i].slider_val/slider_sum);
			var output_effective_votes = Math.round(effective_votes*vote_outputs[i].slider_val/slider_sum);
			var output_burn_amount = Math.round(burn_amount*vote_outputs[i].slider_val/slider_sum);
			var output_effective_burn_amount = Math.round(burn_amount*vote_outputs[i].slider_val/slider_sum);
			
			this_event = games[0].events[vote_outputs[i].event_index];
			
			this_event.sum_hypothetical_votes += output_votes;
			this_event.sum_hypothetical_effective_votes += output_effective_votes;
			this_event.sum_hypothetical_burn_amount += output_burn_amount;
			this_event.sum_hypothetical_effective_burn_amount += output_effective_burn_amount;
			
			this_event.options[this_event.option_id2option_index[vote_outputs[i].option_id]].hypothetical_votes += output_votes;
			this_event.options[this_event.option_id2option_index[vote_outputs[i].option_id]].hypothetical_effective_votes += output_effective_votes;
			this_event.options[this_event.option_id2option_index[vote_outputs[i].option_id]].hypothetical_burn_amount += output_burn_amount;
			this_event.options[this_event.option_id2option_index[vote_outputs[i].option_id]].hypothetical_effective_votes += output_effective_burn_amount;
			
			output_io_sum += output_io_amount;
		}
		
		var output_io_sum = 0;
		
		for (var i=0; i<vote_outputs.length; i++) {
			var output_io_amount = Math.round(io_nondestroy_amount*vote_outputs[i].slider_val/slider_sum);
			if (i == vote_outputs.length-1) output_io_amount = io_nondestroy_amount - output_io_sum;
			
			var output_votes = Math.round(votes*vote_outputs[i].slider_val/slider_sum);
			var output_effective_votes = Math.round(effective_votes*vote_outputs[i].slider_val/slider_sum);
			var output_burn_amount = Math.round(burn_amount*vote_outputs[i].slider_val/slider_sum);
			var output_effective_burn_amount = Math.round(burn_amount*vote_outputs[i].slider_val/slider_sum);
			
			var output_cost = output_votes*games[0].coins_per_vote + output_burn_amount;
			var output_effective_coins = output_effective_votes*games[0].coins_per_vote + output_effective_burn_amount;
			
			var this_event = games[0].events[vote_outputs[i].event_index];
			var this_option = this_event.options[this_event.option_id2option_index[vote_outputs[i].option_id]];
			
			var event_votes = this_event.sum_votes + this_event.sum_unconfirmed_votes + this_event.sum_hypothetical_votes;
			var event_payout = event_votes*games[0].coins_per_vote + this_event.sum_burn_amount + this_event.sum_unconfirmed_burn_amount + this_event.sum_hypothetical_burn_amount;
			
			var event_effective_votes = this_event.sum_effective_votes + this_event.sum_unconfirmed_effective_votes + this_event.sum_hypothetical_effective_votes;
			var event_effective_coins = event_effective_votes*games[0].coins_per_vote + this_event.sum_effective_burn_amount + this_event.sum_unconfirmed_effective_burn_amount + this_event.sum_hypothetical_burn_amount;
			
			var option_effective_votes = this_option.effective_votes + this_option.unconfirmed_effective_votes + this_option.hypothetical_effective_votes;
			var option_effective_coins = option_effective_votes*games[0].coins_per_vote + this_option.effective_burn_amount + this_option.unconfirmed_effective_burn_amount + this_option.hypothetical_burn_amount;
			
			var expected_payout = Math.floor(event_payout*(output_effective_coins/option_effective_coins));
			var payout_factor = expected_payout/output_cost;
			
			output_val_disp = format_coins(output_cost/Math.pow(10,games[0].decimal_places));
			output_val_disp += " &rarr; "+format_coins(expected_payout/Math.pow(10,games[0].decimal_places));
			output_val_disp += " "+games[0].coin_name_plural+" (x"+format_coins(payout_factor)+")";
			
			$('#output_amount_disp_'+i).html(output_val_disp);
			
			vote_outputs[i].amount = output_io_amount;
			
			output_io_sum += output_io_amount;
		}
	}
}
function refresh_output_amounts() {
	refresh_all_inputs();
	finish_refresh_output_amounts();
}
function select_add_output_changed() {
	var option_id = $('#select_add_output').val();
	
	if (option_id != "") {
		var event_index = false;
		for (var i=0; i<games[0].events.length; i++) {
			if (games[0].events[i].db_id2option_index(option_id) !== false) {
				event_index = i;
				event_option_index = games[0].events[i].db_id2option_index(option_id);
			}
		}
		if (event_index !== false) {
			var this_option = games[0].events[event_index].options[event_option_index];
			games[0].add_option_to_vote(event_index, option_id, this_option.name);
			$('#select_add_output').val("");
		}
	}
}
function add_all_options() {
	for (var i=0; i<games[0].events.length; i++) {
		for (var j=0; j<games[0].events[i].options.length; j++) {
			var this_option = games[0].events[i].options[j];
			var already_in = false;
			for (k=0; k<vote_outputs.length; k++) {
				if (vote_outputs[k].option_id == this_option.option_id) already_in = true;
			}
			if (!already_in) games[0].add_option_to_vote(i, this_option.option_id, this_option.name);
		}
	}
}
function remove_all_outputs() {
	for (var i=0; i<vote_outputs.length; i++) {
		$('#compose_vote_output_'+i).remove();
	}
	vote_outputs.length = 0;
}
function show_intro_message() {
	$('#intro_message').modal('show');
}
function show_planned_votes() {
	$('#planned_votes').modal('show');
}
function show_featured_strategies() {
	$('#featured_strategies').modal('show');
	$('#featured_strategies_inner').html("Loading...");
	
	$.get("/ajax/featured_strategies.php?game_id="+games[0].game_id, function(result_html) {
		$('#featured_strategies_inner').html(result_html);
	});
}

// OBJECT: GameForm
var game_form_vars = "blockchain_id,event_rule,option_group_id,event_entity_type_id,events_per_round,event_type_name,maturity,name,payout_weight,round_length,pos_reward,pow_reward,inflation,exponential_inflation_rate,exponential_inflation_minershare,final_round,coin_name,coin_name_plural,coin_abbreviation,start_condition,buyin_policy,game_buyin_cap,default_vote_effectiveness_function,default_effectiveness_param1,default_max_voting_fraction,game_starting_block,escrow_address,genesis_tx_hash,genesis_amount,default_betting_mode".split(",");

function create_new_game() {
	$('#new_game_save_btn').html("Loading...");
	
	var url_string = "/ajax/manage_game.php?action=new&name="+$('#new_game_name').val()+"&blockchain_id="+$('#new_game_blockchain_id').val();

	var genesis_type = $('#new_game_genesis_type').val();
	url_string += "&genesis_type="+genesis_type;
	if (genesis_type == "existing") url_string += "&genesis_tx_hash="+$('#new_game_genesis_tx_hash').val();
	else {
		url_string += "&genesis_io_id="+$('#new_game_genesis_io_id').val();
		url_string += "&escrow_amount="+$('#new_game_genesis_escrow_amount').val();
	}
	
	$.get(url_string, function(result) {
		$('#new_game_save_btn').html("Save &amp; Continue");
		var json_result = JSON.parse(result);
		if (json_result['status_code'] == 1) window.location = '/manage/'+json_result['message'];
		else alert(json_result['message']);
	});
}

function new_game_genesis_type_changed() {
	var type = $('#new_game_genesis_type').val();
	var blockchain_id = $('#new_game_blockchain_id').val();
	
	$.get("/ajax/select_accounts_by_blockchain.php?blockchain_id="+blockchain_id, function(result) {
		var json_result = JSON.parse(result);
		
		$('#new_game_genesis_account_id').html(json_result['html']);
	});
	
	if (type == "existing") {
		$('#new_game_genesis_tx_hash_holder').show('fast');
		$('#new_game_existing_genesis').hide();
	}
	else {
		$('#new_game_genesis_tx_hash_holder').hide();
		$('#new_game_existing_genesis').show('fast');
	}
}

function new_game_genesis_account_changed() {
	var account_id = $('#new_game_genesis_account_id').val();
	
	$.get("/ajax/select_io_by_account.php?account_id="+account_id, function(result) {
		var json_result = JSON.parse(result);
		
		$('#new_game_genesis_io_id').html(json_result['html']);
	});
}

function manage_game(game_id, action) {
	var fetch_link_text = $('#fetch_game_link_'+game_id).html();
	var switch_link_text = $('#switch_game_btn').html();
	
	if (action == "fetch") $('#fetch_game_link_'+game_id).html("Loading...");
	if (action == "switch") $('#switch_game_btn').html("Switching...");
	
	$.get("/ajax/manage_game.php?game_id="+game_id+"&action="+action, function(result) {
		var json_result = JSON.parse(result);
		
		if (action == "fetch") {
			editing_game_id = game_id;
			$('#fetch_game_link_'+game_id).html(fetch_link_text);
			
			$('#game_form_has_final_round').prop('disabled', true);
			if (json_result['game_status'] == "editable") $('#game_form_has_final_round').prop('disabled', false);

			if (json_result['my_game'] == true && json_result['game_status'] == "editable") {
				$('#save_game_btn').show();
				$('#publish_game_btn').show();
			}
			else {
				$('#save_game_btn').hide();
				$('#publish_game_btn').hide();
			}
			
			if (json_result['giveaway_status'] == "invite_free" || json_result['giveaway_status'] == "invite_pay") {
				if (json_result['my_game'] == true) {
					$('#game_invitations_game_btn').show();
				}
				else $('#game_invitations_game_btn').hide();
			}
			else if (json_result['giveaway_status'] == "public_pay" || json_result['giveaway_status'] == "public_free") $('#game_invitations_game_btn').show();
			else $('#game_invitations_game_btn').hide();
			
			$('#game_form').modal('show');
			$('#game_form_name_disp').html("Settings: "+json_result['name_disp']);
			
			for (var i=0; i<game_form_vars.length; i++) {
				if (game_form_vars[i] == "pos_reward" || game_form_vars[i] == "pow_reward" || game_form_vars[i] == "giveaway_amount" || game_form_vars[i] == "genesis_amount") {
					json_result[game_form_vars[i]] = parseInt(json_result[game_form_vars[i]])/Math.pow(10,games[0].decimal_places);
				}
				else if (game_form_vars[i] == "exponential_inflation_minershare" || game_form_vars[i] == "exponential_inflation_rate" || game_form_vars[i] == "default_max_voting_fraction") {
					json_result[game_form_vars[i]] = Math.round(json_result[game_form_vars[i]]*100*Math.pow(10,games[0].decimal_places))/Math.pow(10,games[0].decimal_places);
				}
				else if (game_form_vars[i] == "per_user_buyin_cap" || game_form_vars[i] == "game_buyin_cap") {
					if (json_result[game_form_vars[i]].indexOf('.') != -1) {
						json_result[game_form_vars[i]] = rtrim(json_result[game_form_vars[i]], '0');
					}
					json_result[game_form_vars[i]] = rtrim(json_result[game_form_vars[i]], '.');
				}
				
				$('#game_form_'+game_form_vars[i]).val(json_result[game_form_vars[i]]);
				
				if (json_result['my_game'] && json_result['game_status'] == "editable") $('#game_form_'+game_form_vars[i]).prop('disabled', false);
				else $('#game_form_'+game_form_vars[i]).prop('disabled', true);
			}
			
			if (json_result['my_game'] && json_result['game_status'] == "editable") $('#game_form_has_final_round').prop('disabled', false);
			else $('#game_form_has_final_round').prop('disabled', true);
			
			$('#game_form_game_status').html(json_result['game_status']);

			json_result['start_date']
			if (json_result['inflation'] == "exponential") {
				$('#game_form_inflation_exponential').show();
				$('#game_form_inflation_linear').hide();
			}
			else {
				$('#game_form_inflation_exponential').hide();
				$('#game_form_inflation_linear').show();
			}
			
			if (json_result['buyin_policy'] == "game_cap") {
				$('#game_form_game_buyin_cap_disp').show();
			}
			else $('#game_form_game_buyin_cap_disp').hide();
			
			if (json_result['final_round'] > 0) {
				$('#game_form_final_round_disp').show();
				$('#game_form_has_final_round').val(1);
			}
			else {
				$('#game_form_final_round_disp').hide();
				$('#game_form_has_final_round').val(0);
			}

			if (json_result['giveaway_status'] == "invite_pay" || json_result['giveaway_status'] == "public_pay") {
				$('#game_form_giveaway_status_pay').show();
			}
			else {
				$('#game_form_giveaway_status_pay').hide();
			}

			game_form_start_condition_changed();
			game_form_event_rule_changed();
		}
		else if (action == "switch" || action == "delete" || action == "reset") {
			if (action == "switch") $('#switch_game_btn').html(switch_link_text);
			
			if (json_result['status_code'] == 1) {
				window.location = json_result['redirect_url'];
			}
			else alert(json_result['message']);
		}
	});
}
function game_form_final_round_changed() {
	var final_round = parseInt($('#game_form_has_final_round').val());
	if (final_round == 1) {
		$('#game_form_final_round_disp').slideDown('fast');
		$('#game_form_final_round').focus();
	}
	else {
		$('#game_form_final_round_disp').slideUp('fast');
		$('#game_form_final_round').val(0);
	}
}
function game_form_inflation_changed() {
	var inflation_val = $('#game_form_inflation').val();
	if (inflation_val == "exponential") {
		$('#game_form_inflation_exponential').slideDown('fast');
		$('#game_form_inflation_linear').hide();
	}
	else {
		$('#game_form_inflation_exponential').hide();
		$('#game_form_inflation_linear').slideDown('fast');
	}
}
function game_form_giveaway_status_changed() {
	var giveaway_status = $('#game_form_giveaway_status').val();
	if (giveaway_status == "invite_pay" || giveaway_status == "public_pay") {
		$('#game_form_giveaway_status_pay').slideDown('fast');
	}
	else {
		$('#game_form_giveaway_status_pay').hide();
	}
}
function game_form_start_condition_changed() {
	var start_condition = $('#game_form_start_condition').val();

	$('#game_form_start_condition_fixed_time').hide();
	$('#game_form_start_condition_players_joined').hide();

	$('#game_form_start_condition_'+start_condition).show();
}
function game_form_buyin_policy_changed() {
	var buyin_policy = $('#game_form_buyin_policy').val();
	
	if (buyin_policy == "per_user_cap" || buyin_policy == "game_and_user_cap") {
		$('#game_form_per_user_buyin_cap_disp').show();
	}
	else $('#game_form_per_user_buyin_cap_disp').hide();
	
	if (buyin_policy == "game_cap" || buyin_policy == "game_and_user_cap") {
		$('#game_form_game_buyin_cap_disp').show();
	}
	else $('#game_form_game_buyin_cap_disp').hide();
}
function game_form_event_rule_changed() {
	var event_rule = $('#game_form_event_rule').val();
	if (event_rule == "entity_type_option_group") $('#game_form_event_rule_entity_type_option_group').show();
	else $('#game_form_event_rule_entity_type_option_group').hide();
}
function save_game(action) {
	var save_link_text = $('#save_game_btn').html();
	var save_url = "/ajax/save_game.php?game_id="+editing_game_id+'&action='+action;
	
	for (var i=0; i<game_form_vars.length; i++) {
		save_url += "&"+game_form_vars[i]+"="+encodeURIComponent($('#game_form_'+game_form_vars[i]).val());
	}
	
	$('#save_game_btn').html("Loading...");
	
	$.get(save_url, function(result) {
		$('#save_game_btn').html(save_link_text);
		var json_result = JSON.parse(result);
		if (parseInt(json_result['status_code']) == 1) {
			if (json_result['redirect_user'] == 1) {
				window.location = '/wallet/'+json_result['url_identifier']+'/';
			}
			else window.location = window.location;
		}
		else alert(json_result['message']);
	});
}
function refresh_mature_io_btns() {
	var effectiveness_factor = games[0].block_id_to_effectiveness_factor(games[0].last_block_id+1);
	
	for (var i=0; i<chain_ios.length; i++) {
		var select_btn_text = "";
		var votes = chain_ios[i].votes_at_block(games[0].last_block_id+1);
		
		if (games[0].logo_image_url == "") {
			if (games[0].inflation == "exponential") {
				var coin_equiv = Math.round(votes*games[0].coins_per_vote);
				var disp_coins = coin_equiv;
				if (games[0].default_betting_mode == "principal") disp_coins += chain_ios[i].game_amount_sum();
				if (disp_coins < 0) disp_coins = 0;
				var coin_disp = format_coins(disp_coins/Math.pow(10,games[0].decimal_places));
				
				select_btn_text += "Stake "+coin_disp+" ";
				if (coin_disp == '1') select_btn_text += games[0].coin_name;
				else select_btn_text += games[0].coin_name_plural;
			}
			else {
				select_btn_text += 'Add '+format_coins(votes/Math.pow(10,games[0].decimal_places));
				select_btn_text += ' votes';
				if (games[0].payout_weight != 'coin') {
					var coin_disp = format_coins(chain_ios[i].amount/Math.pow(10,games[0].decimal_places));
					select_btn_text += "<br/>("+coin_disp+" ";
					if (coin_disp == '1') select_btn_text += games[0].coin_name;
					else select_btn_text += games[0].coin_name_plural;
					select_btn_text += ")";
				}
			}
		}
		else {
			var height = Math.round(Math.sqrt(effectiveness_factor)*games[0].utxo_max_height*votes/games[0].utxo_max_effective_votes);
			$('#select_utxo_'+chain_ios[i].io_id).css("height", height+"px");
			$('#select_utxo_'+chain_ios[i].io_id).css("width", height+"px");
			$('#select_utxo_'+chain_ios[i].io_id).css("background-image", "url('"+games[0].logo_image_url+"')");
		}
		$('#select_utxo_'+chain_ios[i].io_id).html(select_btn_text);
	}
	for (var i=0; i<vote_inputs.length; i++) {
		$('#selected_utxo_'+i).html(render_vote_input(i));
	}
}
function compose_vote_loop() {
	if (output_amounts_need_update) refresh_output_amounts();
	output_amounts_need_update = false;
	setTimeout("compose_vote_loop();", 400);
}
var transaction_in_progress = false;

function confirm_compose_vote() {
	if (vote_inputs.length > 0) {
		if (vote_outputs.length > 0) {
			transaction_in_progress = true;
			utxo_spend_offset++;
			$('#confirm_compose_vote_btn').html("Loading...");
			
			var place_vote_url = "/ajax/place_vote.php?game_id="+games[0].game_id+"&burn_amount="+burn_io_amount+"&io_ids=";
			
			for (var i=0; i<vote_inputs.length; i++) {
				place_vote_url += vote_inputs[i].ref_io.io_id;
				place_vote_url += ",";
			}
			place_vote_url = place_vote_url.substr(0, place_vote_url.length-1);
			place_vote_url += "&option_ids=";
			var amounts_url = "&amounts=";
			
			for (var i=0; i<vote_outputs.length; i++) {
				place_vote_url += vote_outputs[i].option_id;
				if (i != vote_outputs.length-1) place_vote_url += ",";
				
				amounts_url += vote_outputs[i].amount;
				if (i != vote_outputs.length-1) amounts_url += ",";
			}
			place_vote_url += amounts_url;
			
			$.get(place_vote_url, function(result) {
				$('#confirm_compose_vote_btn').html('<i class="fas fa-check-circle"></i> &nbsp; Confirm & Stake');
				
				var result_obj = JSON.parse(result);
				
				if (result_obj['status_code'] == 1) {
					$('#compose_vote_success').html(result_obj['message']);
					$('#compose_vote_success').slideDown('slow');
					setTimeout("$('#compose_vote_success').slideUp('fast');", 5000);
					
					setTimeout(function() {
						remove_all_outputs();
						
						var num_inputs = vote_inputs.length;
						for (var i=0; i<num_inputs; i++) {
							remove_utxo_from_vote(0, false);
						}
					}, 1500);
				}
				else {
					$('#compose_vote_errors').html(result_obj['message']);
					$('#compose_vote_errors').slideDown('slow');
					setTimeout("$('#compose_vote_errors').slideUp('fast');", 10000);
				}
				transaction_in_progress = false;
			});
		}
		else {
			alert("First, please add the candidates that you wish to vote for.");
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
	
	if (games[0].mature_io_ids_csv == "") {
		$('#select_input_buttons_msg').html("");
	}
	else {
		$('#select_input_buttons_msg').html("");
	}
	refresh_visible_inputs();
}
function refresh_visible_inputs() {
	var show_count = 0;
	var mature_io_ids = games[0].mature_io_ids_csv.split(",");
	for (var i=0; i<mature_io_ids.length; i++) {
		if (typeof io_id2input_index[mature_io_ids[i]] == 'undefined' || io_id2input_index[mature_io_ids[i]] === false) {
			$('#select_utxo_'+mature_io_ids[i]).show();
			show_count++;
		}
		else {
			add_utxo_to_vote(i);
		}
	}
}
function show_more_event_outcomes(game_id) {
	var show_quantity = 50;
	if ($('#show_more_link').html() == "Show More") {
		var to_event_index = (last_event_index_shown-1);
		var from_event_index = to_event_index - show_quantity + 1;
		
		if (to_event_index < -1) to_event_index = -1;
		if (from_event_index < -1) from_event_index = -1;
		
		$('#show_more_link').html("Loading...");
		last_event_index_shown = from_event_index;
		
		$.get("/ajax/show_event_outcomes.php?game_id="+game_id+"&from_event_index="+from_event_index+"&to_event_index="+to_event_index, function(result) {
			$('#show_more_link').html("Show More");
			var json_result = JSON.parse(result);
			$('#render_event_outcomes').append('<div id="event_outcomes_'+event_outcome_sections_shown+'">'+json_result[1]+'</div>');
			event_outcome_sections_shown++;
		});
	}
}
function render_tx_fee() {
	$('#display_tx_fee').html("TX fee: "+format_coins(games[0].fee_amount)+" "+games[0].chain_coin_name_plural);
}
function manage_game_invitations(this_game_id) {
	$.get("/ajax/game_invitations.php?action=manage&game_id="+this_game_id, function(result) {
		$('#game_invitations_inner').html(result);
		$('#game_invitations').modal('show');
	});
}
function generate_invitation(this_game_id) {
	$.get("/ajax/game_invitations.php?action=generate&game_id="+this_game_id, function(result) {
		manage_game_invitations(this_game_id);
	});
}
function send_invitation(this_game_id, invitation_id, send_method) {
	var send_to = "";
	if (send_method == 'email') {
		send_to = prompt("Please enter the email address where you'd like to send this invitation.");
	}
	else send_to = prompt("Please enter the username of the account where the invitation should be sent.");
	
	if (send_to) {
		$.get("/ajax/game_invitations.php?action=send&send_method="+send_method+"&game_id="+this_game_id+"&invitation_id="+invitation_id+"&send_to="+encodeURIComponent(send_to), function(result) {
			var json_result = JSON.parse(result);
			if (json_result['status_code'] == 1) manage_game_invitations(this_game_id);
			else alert(json_result['message']);
		});
	}
}
var plan_round = function(round_id) {
	this.round_id = round_id;
	this.event_ids = new Array();
	this.sum_points = 0;
}
function set_plan_round_sums() {
	for (var i=0; i<plan_rounds.length; i++) {
		set_plan_round_sum(i);
	}
}
function set_plan_round_sum(round_index) {
	var round_points = 0;
	for (var e=0; e<plan_rounds[round_index].event_ids.length; e++) {
		var event_id = plan_rounds[round_index].event_ids[e];
		var event_index = games[0].all_events_db_id_to_index[event_id];
		if (typeof games[0].all_events[event_index] !== "undefined") {
			var this_event = games[0].all_events[event_index];
			for (var o=0; o<this_event.options.length; o++) {
				round_points += this_event.options[o].points;
			}
		}
	}
	plan_rounds[round_index].sum_points = round_points;
}
function render_plan_option(round_index, event_index, option_index, event_id, option_id) {
	var pct_points = 0;
	var round_id = plan_rounds[round_index].round_id;
	var row_sum = plan_rounds[round_index].sum_points;
	var this_option = games[0].all_events[event_index].options[option_index];
	if (row_sum > 0) pct_points = Math.round(100*this_option.points/row_sum);
	$('#plan_option_'+round_id+'_'+event_id+'_'+option_id).css("background-color", "rgba(0,0,255,"+(pct_points/100)+")");
	if (pct_points >= 50) $('#plan_option_'+round_id+'_'+event_id+'_'+option_id).css("color", "#fff");
	else $('#plan_option_'+round_id+'_'+event_id+'_'+option_id).css("color", "#000");
	$('#plan_option_amount_'+round_id+'_'+event_id+'_'+option_id).html(this_option.points+" ("+pct_points+"%)");
	$('#plan_option_input_'+round_id+'_'+event_id+'_'+option_id).val(this_option.points);
}
function plan_option_clicked(round_id, event_id, option_id) {
	var event_index = games[0].all_events_db_id_to_index[event_id];
	var this_event = games[0].all_events[event_index];
	var option_index = this_event.option_id2option_index[option_id];
	var new_points = (this_event.options[option_index].points+plan_option_increment)%(plan_option_max_points+1);
	this_event.options[option_index].points = new_points;
	var round_index = round_id2plan_round_id[round_id];
	set_plan_round_sums();
	render_plan_round(round_index);
}
function render_plan_round(round_index) {
	for (var i=0; i<plan_rounds[round_index].event_ids.length; i++) {
		var event_id = plan_rounds[round_index].event_ids[i];
		var event_index = games[0].all_events_db_id_to_index[event_id];
		if (typeof games[0].all_events[event_index] !== "undefined") {
			var temp_event = games[0].all_events[event_index];
			for (var option_i=0; option_i<temp_event.options.length; option_i++) {
				render_plan_option(round_index, event_index, option_i, temp_event.event_id, temp_event.options[option_i].option_id);
			}
		}
	}
}
// Right click sets a planned vote to 0
function set_plan_rightclicks() {
	$('.plan_option').contextmenu(function() {
		var id_parts = $(this).attr("id").split('_');
		var round_id = parseInt(id_parts[2]);
		var event_id = parseInt(id_parts[3]);
		var option_id = parseInt(id_parts[4]);
		
		var event_index = games[0].all_events_db_id_to_index[event_id];
		var option_index = games[0].all_events[event_index].option_id2option_index[option_id];
		var round_index = round_id2plan_round_id[round_id];
		
		games[0].all_events[event_index].options[option_index].points = 0;
		set_plan_round_sum(round_index);
		render_plan_round(round_index);
		
		return false;
	});
}
function save_plan_allocations() {
	var postvars = {game_id: games[0].game_id, action: "save", voting_strategy_id: parseInt($('#voting_strategy_id').val()), from_round: parseInt($('#from_round').val()), to_round: parseInt($('#to_round').val())};
	
	if (games[0].all_events_start_index !== false && games[0].all_events_stop_index !== false) {
		for (var i=games[0].all_events_start_index; i<=games[0].all_events_stop_index; i++) {
			for (var o=0; o<games[0].all_events[i].options.length; o++) {
				var points = games[0].all_events[i].options[o].points;
				if (points > 0) {
					postvars['poi_'+games[0].all_events[i].options[o].option_id] = points;
				}
			}
		}
	}
	
	$('#save_plan_btn').html("Saving...");
	$.ajax({
		type: "POST",
		url: "/ajax/planned_allocations.php",
		data: postvars,
		success: function(result) {
			$('#save_plan_btn').html("Save");
			$("input[name=voting_strategy][value='by_plan']").prop("checked",true);
		}
	});
}
function load_plan_rounds() {
	save_plan_allocations();
	refresh_plan_allocations();
}
function refresh_plan_allocations() {
	var from_round = parseInt($('#select_from_round').val());
	var to_round = parseInt($('#select_to_round').val());
	$.get("/ajax/planned_allocations.php?game_id="+games[0].game_id+"&action=fetch&voting_strategy_id="+$('#voting_strategy_id').val()+"&from_round="+from_round+"&to_round="+to_round, function(result) {
		$('#from_round').val(from_round);
		$('#to_round').val(to_round);
		var json_obj = JSON.parse(result);
		$('#plan_rows').html(json_obj['html']);
		set_plan_round_sums();
		render_plan_rounds();
	});
}
function render_plan_rounds() {
	for (var i=0; i<plan_rounds.length; i++) {
		render_plan_round(i);
	}
}
function initiate_buyin() {
	$.get("/ajax/buyin.php?game_id="+games[0].game_id, function(result) {
		$('#buyin_modal_content').html(result);
		$('#buyin_modal').modal('show');
		setTimeout("$('#buyin_amount').focus();", 1000);
	});
}
function initiate_sellout() {
	$.get("/ajax/sellout.php?game_id="+games[0].game_id, function(result) {
		$('#sellout_modal_content').html(result);
		$('#sellout_modal').modal('show');
		setTimeout("$('#sellout_amount').focus();", 1000);
	});
}
function confirm_sellout() {
	$.get("/ajax/sellout.php?game_id="+games[0].game_id+"&action=confirm&invoice_id="+sellout_invoice_id+"&sellout_amount="+$('#sellout_amount').val()+"&address="+$('#sellout_blockchain_address').val(), function(result) {
		var json_result = JSON.parse(result);
		console.log(json_result);
		alert(json_result['message']);
	});
}
function scramble_strategy(strategy_id) {
	var btn_default_text = $('#scramble_plan_btn').html();
	var btn_loading_text = "Randomizing...";
	if ($('#scramble_plan_btn').html() != btn_loading_text) {
		var user_confirmed = confirm('All of your votes in rounds '+$('#select_from_round').val()+' to '+$('#select_to_round').val()+' will be overwritten. Are you sure you want to randomize your votes?');
		if (user_confirmed) {
			$('#scramble_plan_btn').html(btn_loading_text);
			$.get("/ajax/planned_allocations.php?game_id="+games[0].game_id+"&voting_strategy_id="+strategy_id+"&action=scramble&from_round="+$('#select_from_round').val()+"&to_round="+$('#select_to_round').val(), function(result) {
				$('#scramble_plan_btn').html(btn_default_text);
				refresh_plan_allocations();
			});
		}
	}
}

var editing_game_id = false;
var vote_inputs = new Array();
var vote_outputs = new Array();
var output_amounts_need_update = false;
var io_id2input_index = {};
var chain_ios = new Array();
var utxo_spend_offset = 0;

// OBJECT: Event
var Event = function(game, game_event_index, event_id, real_event_index, num_voting_options, vote_effectiveness_function, effectiveness_param1, option_block_rule, event_name) {
	this.game = game;
	this.game_event_index = game_event_index;
	this.event_id = event_id;
	this.real_event_index = real_event_index;
	
	this.num_voting_options = num_voting_options;
	this.vote_effectiveness_function = vote_effectiveness_function;
	this.effectiveness_param1 = effectiveness_param1;
	this.option_block_rule = option_block_rule;
	this.event_name = event_name;
	
	this.sum_votes = 0;
	this.sum_unconfirmed_votes = 0;
	this.sum_hypothetical_votes = 0;
	
	this.sum_effective_votes = 0;
	this.sum_unconfirmed_effective_votes = 0;
	this.sum_hypothetical_effective_votes = 0;
	
	this.sum_burn_amount = 0;
	this.sum_unconfirmed_burn_amount = 0;
	this.sum_hypothetical_burn_amount = 0;
	
	this.sum_effective_burn_amount = 0;
	this.sum_unconfirmed_effective_burn_amount = 0;
	this.sum_hypothetical_effective_burn_amount = 0;
	
	this.selected_option_id = false;
	this.deleted = false;
	this.details_shown = true;
	
	this.options = new Array();
	this.option_id2option_index = {};
	
	this.start_vote = function(option_id) {
		var option_display_name = this.options[this.db_id2option_index(option_id)].name;
		option_display_name += ' (<a href="/explorer/games/'+games[this.game.instance_id].game_url_identifier+'/events/'+this.real_event_index+'">'+this.event_name+'</a>)';
		games[this.game.instance_id].add_option_to_vote(game_event_index, option_id, option_display_name);
	};
	this.db_id2option_index = function(db_option_id) {
		for (var i=0; i<this.options.length; i++) {
			if (this.options[i].option_id == db_option_id) return i;
		}
		return false;
	};
	this.toggle_details = function() {
		if (this.details_shown) {
			$('#game'+this.game.instance_id+'_event'+this.game_event_index+'_details').hide();
			this.details_shown = false;
		}
		else {
			$('#game'+this.game.instance_id+'_event'+this.game_event_index+'_details').show();
			this.details_shown = true;
		}
	};
	this.option_selected = function(option_id) {
		/*if (this.selected_option_id !== false) this.option_deselected(this.selected_option_id);
		$('#game'+this.game.instance_id+'_event'+this.game_event_index+'_vote_option_'+option_id).addClass('vote_option_box_sel');
		this.selected_option_id = option_id;
		this.game.sel_game_event_index = this.game_event_index;*/
	};
	this.option_deselected = function(option_id) {
		/*$('#game'+this.game.instance_id+'_event'+this.game_event_index+'_vote_option_'+option_id).removeClass('vote_option_box_sel');
		this.selected_option_id = false;*/
	};
	this.refresh_time_estimate = function() {
		if (this.deleted == false) {
			var block_in_round = this.game.block_id_to_round_index(this.game.last_block_id+1)-1;
			
			if (this.option_block_rule == "football_match") {
				var event_sim_time_sec = 90*60;
				var block_sim_time_sec = Math.round(event_sim_time_sec/this.game.game_round_length);
				var sec_into_game = block_in_round*block_sim_time_sec;
				
				var sec_since_block_loaded;
				if (this.game.time_last_block_loaded > 0) sec_since_block_loaded = ((new Date().getTime())/1000 - this.game.time_last_block_loaded);
				else sec_since_block_loaded = 0;
				
				var expected_sec_this_block = sec_since_block_loaded + this.game.seconds_per_block;
				var sim_sec_into_block = Math.round((sec_since_block_loaded/expected_sec_this_block)*block_sim_time_sec);
				sec_into_game += sim_sec_into_block;
				
				var min_disp = Math.floor(sec_into_game/60);
				var sec_disp = leftpad(sec_into_game - min_disp*60, 2, "0");
				$('#game'+this.game.instance_id+'_event'+this.game_event_index+'_timer').html(min_disp+":"+sec_disp);
			}
			var _this = this;
			setTimeout(function() {_this.refresh_time_estimate();}, 1000);
		}
	};
};

// OBJECT: Game
var Game = function(game_id, last_block_id, last_transaction_id, mature_io_ids_csv, payout_weight, game_round_length, fee_amount, game_url_identifier, coin_name, coin_name_plural, chain_coin_name, chain_coin_name_plural, refresh_page, event_ids, logo_image_url, vote_effectiveness_function, effectiveness_param1, seconds_per_block, inflation, exponential_inflation_rate, time_last_block_loaded, decimal_places, view_mode, initial_event_index, filter_date, default_betting_mode) {
	Game.numInstances = (Game.numInstances || 0) + 1;
	
	this.instance_id = Game.numInstances-1;
	this.game_id = game_id;
	this.last_block_id = last_block_id;
	this.last_transaction_id = last_transaction_id;
	this.mature_io_ids_csv = mature_io_ids_csv;
	this.mature_io_ids_hash = Sha256.hash(mature_io_ids_csv);
	this.payout_weight = payout_weight;
	this.game_round_length = game_round_length;
	this.fee_amount = fee_amount;
	this.game_url_identifier = game_url_identifier;
	this.coin_name = coin_name;
	this.coin_name_plural = coin_name_plural;
	this.chain_coin_name = chain_coin_name;
	this.chain_coin_name_plural = chain_coin_name_plural;
	this.refresh_page = refresh_page;
	this.event_ids = event_ids;
	this.event_ids_hash = Sha256.hash(event_ids);
	this.logo_image_url = logo_image_url;
	this.vote_effectiveness_function = vote_effectiveness_function;
	this.effectiveness_param1 = effectiveness_param1;
	this.seconds_per_block = parseInt(seconds_per_block);
	this.inflation = inflation;
	this.exponential_inflation_rate = parseFloat(exponential_inflation_rate);
	this.decimal_places = parseInt(decimal_places);
	this.view_mode = view_mode;
	this.selected_event_index = initial_event_index;
	this.filter_date = filter_date;
	this.default_betting_mode = default_betting_mode;
	
	if (filter_date) {
		$(document).ready(function() {
			$('#filter_by_date').val(filter_date);
		});
	}
	
	this.events = new Array();
	this.all_events = new Array();
	this.all_events_start_index = false;
	this.all_events_stop_index = false;
	this.option_has_votingaddr = [];
	this.sel_game_event_index = false;
	this.all_events_db_id_to_index = {};
	this.my_effective_votes = 0;
	this.utxo_max_effective_votes = 0;
	this.utxo_max_height = 150;
	this.time_last_block_loaded = parseInt(time_last_block_loaded);
	
	this.coins_per_vote = 0;
	if (inflation == "exponential") {
		if (payout_weight == "coin_round") this.coins_per_vote = exponential_inflation_rate;
		else this.coins_per_vote = exponential_inflation_rate/game_round_length;
	}
	
	this.game_loop_index = 1;
	this.last_game_loop_index_applied = -1;
	this.refresh_in_progress = false;
	this.last_refresh_time = 0;
	
	this.block_id_to_round_index = function(block_id) {
		return ((block_id-1) % this.game_round_length)+1;
	};
	this.block_to_round = function(block_id) {
		return Math.ceil(block_id/this.game_round_length);
	};
	this.round_index_to_effectiveness_factor = function(round_index) {
		if (this.vote_effectiveness_function == "linear_decrease") {
			var slope = -1*this.effectiveness_param1;
			var frac_complete = Math.floor(Math.pow(10,games[0].decimal_places)*(round_index/this.game_round_length))/Math.pow(10,games[0].decimal_places);
			var effectiveness = Math.floor(Math.pow(10,games[0].decimal_places)*frac_complete*slope)/Math.pow(10,games[0].decimal_places) + 1;
			return effectiveness;
		}
		else return 1;
	};
	this.block_id_to_effectiveness_factor = function(block_id) {
		return this.round_index_to_effectiveness_factor(this.block_id_to_round_index(block_id));
	};
	this.add_option_to_vote = function(event_index, option_id, name) {
		if (this.refresh_page != "wallet") {
			alert("To cast votes, first log in to your wallet.");
		}
		else {
			var index_id = vote_outputs.length;
			
			if (games[0].option_has_votingaddr[option_id]) {
				vote_outputs.push(new vote_output(index_id, name, option_id, event_index));
				$('#compose_vote_outputs').append('<div id="compose_vote_output_'+index_id+'" class="select_utxo">'+render_option_output(index_id, name)+'</div>');
				
				load_option_slider(index_id);
				
				refresh_compose_vote();
				refresh_output_amounts();
			}
			else {
				alert("You can't vote for this candidate yet, you don't have a voting address for it.");
			}
		}
	};
	this.set_user_game_event_index = function() {
		$.get("/ajax/set_user_game_event_index.php?game_id="+games[0].game_id+"&event_index="+this.selected_event_index, function(result) {});
	};
	this.show_selected_event = function(skip_set_event_index) {
		if (this.selected_event_index > this.events.length-1) {
			this.selected_event_index = 0;
		}
		var event_nav_txt = "Viewing "+(this.selected_event_index+1)+" of "+this.events.length;
		event_nav_txt += " &nbsp;&nbsp;&nbsp; <a href='' onclick='games["+this.instance_id+"].show_previous_event(); return false;'>Previous</a> &nbsp; <a href='' onclick='games["+this.instance_id+"].show_next_event(); return false;'>Next</a> &nbsp;&nbsp;&nbsp; Jump to: <input id=\"jump_to_event_index_"+this.selected_event_index+"\" class=\"form-control input-sm\" style=\"width: 80px; display: inline-block;\" /><button class=\"btn btn-primary btn-sm\" onclick=\"games["+this.instance_id+"].hide_selected_event(); games["+this.instance_id+"].selected_event_index=parseInt($('#jump_to_event_index_"+this.selected_event_index+"').val())-1; games["+this.instance_id+"].show_selected_event(false);\">Go</button>";
		
		$('#game'+this.instance_id+'_event'+this.selected_event_index).fadeIn('fast');
		$('#game'+this.instance_id+'_event'+this.selected_event_index+'_event_nav').html(event_nav_txt);
		
		if (!skip_set_event_index) this.set_user_game_event_index();
		
		this.render_event_images(this.event_index_to_previous(this.selected_event_index));
		this.render_event_images(this.selected_event_index);
		this.render_event_images(this.event_index_to_next(this.selected_event_index));
	};
	this.render_event_images = function(event_index) {
		if (typeof this.events[event_index] !== "undefined") {
			for (var i=0; i<this.events[event_index].options.length; i++) {
				var this_option = this.events[event_index].options[i];
				$('#option'+this_option.option_id+'_image').attr("src", this_option.image_url);
			}
		}
	};
	this.event_index_to_previous = function(event_index) {
		if (event_index > 0) return event_index-1;
		else return this.events.length-1;
	};
	this.event_index_to_next = function(event_index) {
		if (event_index < this.events.length-1) return event_index+1;
		else return 0;
	};
	this.show_previous_event = function() {
		this.hide_selected_event();
		this.selected_event_index = this.event_index_to_previous(this.selected_event_index);
		this.show_selected_event(false);
	};
	this.show_next_event = function() {
		this.hide_selected_event();
		this.selected_event_index = this.event_index_to_next(this.selected_event_index);
		this.show_selected_event(false);
	};
	this.hide_selected_event = function() {
		$('#game'+this.instance_id+'_event'+this.selected_event_index).hide();
	};
	this.refresh_if_needed = function() {
		if (!this.refresh_in_progress) {
			this.last_refresh_time = new Date().getTime();
			this.refresh_in_progress = true;
			
			var check_activity_url = "/ajax/check_new_activity.php?instance_id="+this.instance_id+"&game_id="+this.game_id+"&event_ids_hash="+this.event_ids_hash+"&refresh_page="+this.refresh_page+"&last_block_id="+this.last_block_id+"&last_transaction_id="+this.last_transaction_id+"&mature_io_ids_hash="+this.mature_io_ids_hash+"&game_loop_index="+this.game_loop_index;
			if (this.filter_date) check_activity_url += "&filter_date="+this.filter_date;
			
			var _this = this;
			$.ajax({
				url: check_activity_url,
				success: function(result) {
					if (_this.refresh_page == "wallet" && result == "0") {
						window.location = '/wallet/'+_this.game_url_identifier+'/?action=logout';
					}
					else {
						_this.refresh_in_progress = false;
						var json_result = $.parseJSON(result);
						
						if (json_result['game_loop_index'] > _this.last_game_loop_index_applied) {
							if (json_result['new_block'] == "1") {
								_this.last_block_id = parseInt(json_result['last_block_id']);
								_this.time_last_block_loaded = parseInt(json_result['time_last_block_loaded']);
								
								if (_this.refresh_page == "wallet") {
									if (parseInt(json_result['new_performance_history']) == 1) {
										$('#performance_history_new').html(json_result['performance_history']);
									}
								}
							}
							
							if (parseInt(json_result['new_event_ids']) == 1) {
								eval(json_result['new_event_js']);
								
								console.log("applying new event IDs");
								
								_this.event_ids = json_result['event_ids'];
								_this.event_ids_hash = Sha256.hash(json_result['event_ids']);
								
								set_select_add_output();
								
								if (_this.view_mode == "simple") {
									_this.hide_selected_event();
									_this.selected_event_index = 0;
									_this.show_selected_event(false);
								}
							}
							
							if (_this.refresh_page == "wallet") {
								$('#game_status_explanation').html(json_result['game_status_explanation']);
								if (json_result['game_status_explanation'] == '') $('#game_status_explanation').hide();
								else $('#game_status_explanation').show();
								
								if (parseInt(json_result['new_mature_ios']) == 1 || parseInt(json_result['new_transaction']) == 1 || json_result['new_block'] == 1) {
									if (typeof json_result['mature_io_ids_csv'] == "undefined") _this.mature_io_ids_csv = "";
									else _this.mature_io_ids_csv = json_result['mature_io_ids_csv'];
									_this.mature_io_ids_hash = Sha256.hash(_this.mature_io_ids_csv);
									$('#select_input_buttons').html(json_result['select_input_buttons']);
									reload_compose_vote();
									utxo_spend_offset = 0;
								}
								
								set_input_amount_sums();
								refresh_mature_io_btns();
								
								if (parseInt(json_result['new_messages']) == 1) {
									var new_message_user_ids = json_result['new_message_user_ids'].split(",");
									for (var i=0; i<new_message_user_ids.length; i++) {
										openChatWindow(new_message_user_ids[i]);
									}
								}
							}
							
							if (typeof json_result['chart_html'] != "undefined") {
								console.log("refreshing charts...");
								$('#game'+_this.instance_id+'_chart_html').html(json_result['chart_html']);
								$('#game'+_this.instance_id+'_chart_js').html('<script type="text/javascript">'+json_result['chart_js']+'</script>');
							}
							
							if (parseInt(json_result['new_block']) == 1 || parseInt(json_result['new_transaction']) == 1) {
								$('#account_value').html(json_result['account_value']);
								$('#account_value').hide();
								$('#account_value').fadeIn('medium');
								
								$('#wallet_text_stats').html(json_result['wallet_text_stats']);
								$('#wallet_text_stats').hide();
								$('#wallet_text_stats').fadeIn('fast');
							}
							
							for (var game_event_index=0; game_event_index<_this.events.length; game_event_index++) {
								$('#game'+_this.instance_id+'_event'+game_event_index+'_current_round_table').html(json_result['current_round_table'][game_event_index]);
								
								$('#game'+_this.instance_id+'_event'+game_event_index+'_current_round_table').hide();
								$('#game'+_this.instance_id+'_event'+game_event_index+'_current_round_table').show();
								
								_this.render_event_images(game_event_index);
								
								if (_this.events[game_event_index].details_shown) {
									$('#game'+_this.instance_id+'_event'+game_event_index+'_details').show();
								}
								else {
									$('#game'+_this.instance_id+'_event'+game_event_index+'_details').hide();
								}
								
								if (typeof json_result['my_current_votes'] != "undefined" && typeof json_result['my_current_votes'][game_event_index] != "undefined") {
									$('#game'+_this.instance_id+'_event'+game_event_index+'_my_current_votes').html(json_result['my_current_votes'][game_event_index]);
								}
								
								console.log(".");
							}
							
							eval(json_result['set_options_js']);
							refresh_output_amounts();
							
							_this.last_game_loop_index_applied = json_result['game_loop_index'];
						}
					}
				},
				error: function(XMLHttpRequest, textStatus, errorThrown) {
					_this.refresh_in_progress = false;
					console.log("Game loop web request failed.");
				}
			});
		}
	};
	this.game_loop_event = function() {
		this.refresh_if_needed();
		this.game_loop_index++;
		var _this = this;
		setTimeout(function() {_this.game_loop_event()}, 2000);
	};
};

function newsletter_signup() {
	var email = $('#newsletter_email').val();
	$.get("/ajax/newsletter.php?action=signup&email="+encodeURIComponent(email), function(result) {
		var resultObj = JSON.parse(result);
		alert(resultObj['message']);
	});
}
function set_select_add_output() {
	var optionsAsString = "<option value=''>Please select...</option>";
	for (var i=0; i<games[0].events.length; i++) {
		for (var j=0; j<games[0].events[i].options.length; j++) {
			optionsAsString += "<option value='"+games[0].events[i].options[j].option_id+"'>"+games[0].events[i].options[j].name+" ("+games[0].events[i].event_name+")</option>";
		}
	}
	$("#select_add_output").find('option').remove().end().append($(optionsAsString));
	$("#principal_option_id").find('option').remove().end().append($(optionsAsString));
}

var account_io_id = false;
var account_io_amount = false;
var account_game_id = false;
var selected_account_action = false;

function account_start_spend_io(game_id, io_id, amount, blockchain_coin_name, game_coin_name) {
	account_io_id = io_id;
	account_io_amount = amount;
	account_game_id = game_id;
	
	$('#account_spend_buyin_total').html("(Total: "+format_coins(amount)+" coins)");
	$('#account_spend_modal').modal('show');
	$('#account_io_id').val(account_io_id);
	$('#set_for_sale_io_id').val(account_io_id);
	$('#donate_game_id').val(game_id);
	$('#set_for_sale_game_id').val(game_id);
	
	var optionsAsString = "<option value='blockchain'>"+blockchain_coin_name+"</option>\n";
	optionsAsString += "<option value='game'>"+game_coin_name+"</option>\n";
	$("#spend_withdraw_coin_type").find('option').remove().end().append($(optionsAsString));
	
	$('#spend_withdraw_fee_label').html(blockchain_coin_name);
}
function account_spend_action_changed() {
	var account_spend_action = $('#account_spend_action').val();
	
	if (selected_account_action !== false) $('#account_spend_'+selected_account_action).hide();
	
	$('#account_spend_'+account_spend_action).show('fast');
	selected_account_action = account_spend_action;
	
	if (account_spend_action == "join_tx") {
		$.get("/ajax/account_spend.php?io_id="+account_io_id+"&action=start_join_tx", function(result) {
			var result_obj = JSON.parse(result);
			$('#account_spend_join_tx').html(result_obj['html']);
		});
	}
}
function account_spend_buyin_address_choice_changed() {
	var address_choice = $('#account_spend_buyin_address_choice').val();
	if (address_choice == "new") {
		$('#account_spend_buyin_address_existing').hide('fast');
	}
	else {
		$('#account_spend_buyin_address_existing').show('fast');
		$('#account_spend_buyin_address').focus();
	}
}
function account_spend_refresh() {
	var buyin_amount = parseFloat($('#account_spend_buyin_amount').val());
	if (buyin_amount > 0) {
		var fee_amount = parseFloat($('#account_spend_buyin_fee').val());
		var color_amount = account_io_amount - buyin_amount - fee_amount;
		$('#account_spend_buyin_color_amount').html("Color "+format_coins(color_amount)+" coins");
	}
	setTimeout("account_spend_refresh();", 500);
}
function account_spend_buyin() {
	var account_spend_action = $('#account_spend_action').val();
	
	if (account_spend_action == "buyin") {
		var address_choice = $('#account_spend_buyin_address_choice').val();
		var buyin_amount = parseFloat($('#account_spend_buyin_amount').val());
		var fee_amount = parseFloat($('#account_spend_buyin_fee').val());
		var game_id = $('#account_spend_game_id').val();
		
		var buyin_url = "/ajax/account_spend.php?action=buyin&io_id="+account_io_id+"&game_id="+game_id+"&buyin_amount="+buyin_amount+"&fee_amount="+fee_amount;
		if (address_choice == "new") buyin_url += "&address=new";
		else buyin_url += "&address="+$('#account_spend_buyin_address').val();
		
		$.get(buyin_url, function(result) {
			var result_obj = JSON.parse(result);
			alert(result_obj['message']);
			if (result_obj['status_code'] == 1) window.location = window.location;
		});
	}
}
function account_spend_withdraw() {
	var withdraw_address = $('#spend_withdraw_address').val();
	var withdraw_amount = $('#spend_withdraw_amount').val();
	$.get("/ajax/account_spend.php?action=withdraw&io_id="+account_io_id+"&address="+withdraw_address+"&amount="+withdraw_amount+"&fee="+$('#spend_withdraw_fee').val()+"&withdraw_type="+$("#spend_withdraw_coin_type").val(), function(result) {
		var result_obj = JSON.parse(result);
		alert(result_obj['message']);
		if (result_obj['status_code'] == 1) window.location = window.location;
	});
}
function account_spend_split() {
	var spend_url = "/ajax/account_spend.php?action=split&game_id="+account_game_id+"&io_id="+account_io_id+"&amount_each="+$('#split_amount_each').val()+"&quantity="+$('#split_quantity').val();
	
	$.get(spend_url, function(result) {
		var result_obj = JSON.parse(result);
		if (result_obj['status_code'] == 1) window.location = result_obj['message'];
		else alert(result_obj['message']);
	});
}
function manage_addresses(account_id, action, address_id) {
	var ajax_url = "/ajax/manage_addresses.php?action="+action+"&account_id="+account_id;
	if (address_id) ajax_url += "&address_id="+address_id;
	
	$.get(ajax_url, function(result) {
		var result_obj = JSON.parse(result);
		if (result_obj['status_code'] == 1) window.location = window.location;
		else alert(result_obj['message']);
	});
}

var set_event_id = false;

function set_event_outcome(game_id, event_id) {
	var _event_id = event_id;
	$.get("/ajax/set_event_outcome.php?action=fetch&event_id="+event_id, function(result) {
		set_event_id = _event_id;
		var result_obj = JSON.parse(result);
		$('#set_event_outcome_modal').modal('show');
		$('#set_event_outcome_modal_content').html(result_obj['html']);
		console.log(result_obj);
	});
}

function set_event_outcome_selected() {
	var option_id = parseInt($('#set_event_outcome_option_id').val());
	$('#set_event_outcome_option_id').attr('disabled', 'disabled');
	
	if (option_id > 0) {
		$.get("/ajax/set_event_outcome.php?action=set&event_id="+set_event_id+"&option_id="+option_id, function(result) {
			var result_obj = JSON.parse(result);
			alert(result_obj['message']);
		});
	}
}

function leftpad(num, size, pad_char) {
	var s = num+"";
	while (s.length < size) s = pad_char + s;
	return s;
}

function try_claim_address(blockchain_id, game_id, address_id) {
	$.get("/ajax/try_claim_address.php?blockchain_id="+blockchain_id+"&game_id="+game_id+"&address_id="+address_id, function(result) {
		var result_obj = JSON.parse(result);
		console.log(result_obj);
		
		if (result_obj['status_code'] == 1) window.location = window.location;
		else if (result_obj['status_code'] == 2) window.location = '/wallet/?redirect_key='+result_obj['message'];
		else alert(result_obj['message']);
	});
}
function change_user_game() {
	var user_game_id = $('#select_user_game').val();
	window.location = '/wallet/'+games[0].game_url_identifier+'/?action=change_user_game&user_game_id='+user_game_id;
}
function explorer_change_user_game() {
	var user_game_id = $('#select_user_game').val();
	window.location = '/explorer/games/'+games[0].game_url_identifier+'/my_bets/?user_game_id='+user_game_id;
}
function finish_join_tx() {
	$.get("/ajax/account_spend.php?action=finish_join_tx&io_id="+account_io_id+"&join_io_id="+$('#join_tx_io_id').val(), function(result) {
		var result_obj = JSON.parse(result);
		alert(result_obj['message']);
		if (result_obj['status_code'] == 13) window.location = window.location;
	});
}
function create_account_step(step) {
	var create_account_action = $('#create_account_action').val();
	
	if (step == 1) {
		$('#create_account_blockchain_id').val("");
		$('#create_account_submit').hide('fast');
		$('#create_account_step3').hide('fast');
		
		if (create_account_action == "") {
			$('#create_account_step2').hide('fast');
		}
		else {
			$('#create_account_step2').show('fast');
		}
	}
	else if (step == 2) {
		$('#create_account_submit').show('fast');
		
		if (create_account_action == "by_rpc_account") {
			$('#create_account_step3').show('fast');
			$('#create_account_rpc_name').focus();
		}
	}
	else if (step == "submit") {
		$.get("/ajax/create_account.php?action="+create_account_action+"&blockchain_id="+$('#create_account_blockchain_id').val()+"&account_name="+$('#create_account_rpc_name').val(), function(result) {
			var result_obj = JSON.parse(result);
			if (result_obj['status_code'] == 1) window.location = result_obj['message'];
			else alert(result_obj['message']);
		});
	}
}

var event_verbatim_vars = new Array('event_index', 'next_event_index', 'event_starting_block', 'event_final_block', 'event_payout_block', 'event_starting_time', 'event_final_time', 'event_payout_offset_time', 'event_name', 'option_block_rule', 'option_name', 'option_name_plural', 'outcome_index');

function clear_event_form() {
	for (form_i in event_verbatim_vars) {
		$('#event_form_'+event_verbatim_vars[form_i]).val("");
	}
}
function manage_game_set_event_blocks(game_defined_event_id) {
	var ajax_url = "/ajax/set_event_blocks.php?game_id="+games[0].game_id;
	if (game_defined_event_id) ajax_url += "&game_defined_event_id="+game_defined_event_id;
	
	$.get(ajax_url, function(result) {
		var result_obj = JSON.parse(result);
		alert(result_obj['message']);
	});
}
function manage_game_load_event(gde_id) {
	clear_event_form();
	
	$.get("/ajax/manage_game.php?action=load_gde&game_id="+games[0].game_id+"&gde_id="+gde_id, function(result) {
		var result_obj = JSON.parse(result);
		$('#event_modal').modal('show');
		
		var form_data = result_obj['form_data'];
		
		for (var form_key in form_data) {
			$('#event_form_'+form_key).val(form_data[form_key]);
		}
		
		if (form_data['event_starting_time'] || !form_data['event_starting_time'] && !form_data['event_starting_time']) {
			$('#event_form_event_times').show();
			$('#event_form_event_blocks').hide();
		}
		else {
			$('#event_form_event_times').hide();
			$('#event_form_event_blocks').show();
		}
		
		$('#event_form_save_btn').click(function() {
			save_gde(gde_id);
		});
	});
}
function save_gde(gde_id) {
	var save_url = "/ajax/manage_game.php?action=save_gde&game_id="+games[0].game_id+"&gde_id="+gde_id;
	
	for (var i=0; i<event_verbatim_vars.length; i++) {
		save_url += "&"+event_verbatim_vars[i]+"="+encodeURIComponent($('#event_form_'+event_verbatim_vars[i]).val());
	}
	
	$.get(save_url, function(result) {
		var result_obj = JSON.parse(result);
		alert(result_obj['message']);
	});
}
function manage_game_event_options(gde_id) {
	$.get("/ajax/manage_game.php?action=manage_gdos&game_id="+games[0].game_id+"&gde_id="+gde_id, function(result) {
		var result_obj = JSON.parse(result);
		$('#options_modal').modal('show');
		$('#options_modal_content').html(result_obj['html']);
	});
}
function add_game_defined_option(gde_id) {
	var gdo_name = $('#new_gdo_name').val();
	var gdo_entity_type_id = $('#new_gdo_entity_type_id').val();
	$.get("/ajax/manage_game.php?action=add_new_gdo&game_id="+games[0].game_id+"&gde_id="+gde_id+"&name="+encodeURIComponent(gdo_name)+"&entity_type_id="+gdo_entity_type_id, function(result) {
		var result_obj = JSON.parse(result);
		if (result_obj['status_code'] == 1) manage_game_event_options(gde_id);
		else alert(result_obj['message']);
		console.log(result_obj);
	});
}
function delete_game_defined_option(gde_id, gdo_id) {
	$.get("/ajax/manage_game.php?action=delete_gdo&game_id="+games[0].game_id+"&gdo_id="+gdo_id, function(result) {
		var result_obj = JSON.parse(result);
		if (result_obj['status_code'] == 1) manage_game_event_options(gde_id);
		else alert(result_obj['message']);
	});
}
function toggle_account_details(account_id) {
	$('#account_details_'+account_id).toggle('fast');
	selected_account_id = account_id;
}
function withdraw_from_account(account_id, step) {
	if (step == 1) {
		selected_account_id = account_id;
		$('#withdraw_dialog').modal('show');
	}
	else if (step == 2) {
		if ($('#withdraw_btn').html() == "Withdraw") {
			$('#withdraw_btn').html("Loading...");
			$.get("/ajax/account_spend.php?action=withdraw_from_account&account_id="+selected_account_id+"&amount="+$('#withdraw_amount').val()+"&fee="+$('#withdraw_fee').val()+"&address="+$('#withdraw_address').val(), function(result) {
				$('#withdraw_btn').html("Withdraw");
				$('#withdraw_message').html(result['message']);
				$('#withdraw_message').show("fast");
				console.log(result);
			});
		}
	}
}
function cards_howmany_changed() {
	var howmany = $('#cards_howmany').val();
	if (howmany == "other") {
		$('#cards_howmany_other').show();
		$('#cards_howmany_other_val').focus();
	}
	else {
		$('#cards_howmany_other').hide();
	}
}
function fv_currency_id_changed() {
	var fv_currency_id = $('#cards_fv_currency_id').val();
	var currency_id = $('#cards_currency_id').val();
	
	$.get("/ajax/select_denominations_by_currencies.php?currency_id="+currency_id+"&fv_currency_id="+fv_currency_id, function(result) {
		var result_obj = JSON.parse(result);
		cost_per_coin = result_obj['cost_per_coin'];
		coin_abbreviation = result_obj['coin_abbreviation'];
		$('#cards_denomination_id').html(result_obj['denominations_html']);
		$('#cards_account_id').html(result_obj['accounts_html']);
	});
}
function currency_id_changed() {
	var currency_id = $('#cards_currency_id').val();
	
	$('#cards_denomination_id').html("");
	
	$.get("/ajax/select_fv_currency_by_currency.php?currency_id="+currency_id, function(result) {
		$('#cards_fv_currency_id').html(result);
	});
}
function show_card_preview() {
	var denomination_id = $('#cards_denomination_id').val();
	
	var preview_url = "/ajax/card_preview.php?denomination_id="+denomination_id;
	
	preview_url += "&purity="+$('#cards_purity').val()+"&name="+$('#cards_name').val();
	preview_url += "&title="+$('#cards_title').val()+"&email=";
	preview_url += $('#cards_email').val()+"&pnum="+$('#cards_pnum').val();
	
	$('#cards_preview').show();
	$('#cards_preview').html("Loading...");
	
	$.get(preview_url, function(result) {
		$('#cards_preview').html(result);
	});
}
function search_card_id() {
	var issuer_id = $('#card_issuer_id').val();
	var card_id = $('#card_id_search').val();
	var url = "/redeem/"+issuer_id+"/"+card_id;
	if ($('#redirect_key').val() != "") url += "/?redirect_key="+$('#redirect_key').val();
	window.location = url;
}
function redeem_toggle() {
	if ($('#enter_redeem_code').is(":visible")) {
		$('#enter_redeem_code').hide();
	}
	else {
		$('#enter_redeem_code').show();
		$('#redeem_code').focus();
	}
}
function check_show_confirm_button() {
	var legit_length = 0;
	var check_string = $('#redeem_code').val();
	for (var i=0; i<check_string.length; i++) {
		if (check_string[i] == "_" || check_string[i] == "-") {}
		else legit_length++;
	}
	if (legit_length >= 16) $('#confirm_button').show();
	else $('#confirm_button').hide();
}
function check_the_code() {
	var url = "/ajax/check_code.php?issuer_id="+issuer_id+"&card_id="+card_id+"&code="+$('#redeem_code').val().replace(/-/g, '');
	$('#confirm_button').html("Checking...");
	$('#messages').hide();
	
	$.get(url, function(result) {
		var result_obj = JSON.parse(result);
		
		$('#confirm_button').html("Redeem");
		if (result_obj['status_code'] == 1 || result_obj['status_code'] == 4) {
			$('#step1').hide();
			$('#redeem_options').modal('show');
			$('#messages').hide();
		}
		else {
			$('#messages').html("Incorrect");
			$('#messages').css("color", "#f00");
			$('#messages').show();
		}
	});
}
function card_login(create_mode, login_card_id, issuer_id) {
	$('#card_account_password').val(Sha256.hash($('#card_account_password').val()));
	if (create_mode) $('#card_account_password2').val(Sha256.hash($('#card_account_password2').val()));
	
	var card_password = $('#card_account_password').val();
	var card_password2;
	if (create_mode) card_password2 = $('#card_account_password2').val();
	
	var successful = false;
	
	if (!create_mode || card_password == card_password2) {
		var url = "/ajax/check_code.php?action=login&issuer_id="+issuer_id+"&card_id="+login_card_id+"&password="+card_password+"&code="+$('#redeem_code').val().replace(/-/g, '');
		if ($('#redirect_key').val() != "") url += "&redirect_key="+$('#redirect_key').val();
		
		$.get(url, function(result) {
			var result_obj = JSON.parse(result);
			
			if (result_obj['status_code'] == 1 || result_obj['status_code'] == 2) {
				window.location = result_obj['message'];
				successful = true;
			}
			else alert(result_obj['message']);
		});
	}
	else alert("Error, the passwords that you entered do not match.");
	
	if (!successful) {
		$('#card_account_password').val("");
		if (create_mode) $('#card_account_password2').val("");
	}
}
function open_card(card_id) {
	if (card_id != selected_card) {
		if (selected_card != -1) {
			$('#card_block'+selected_card).hide('fast');
			$('#card_btn'+selected_card).removeClass("card_small_sel");
		}
		selected_card = card_id;
		$('#card_btn'+card_id).addClass("card_small_sel");
		
		setTimeout(function() {
			$('#card_block'+card_id).show('medium');
		}, 300);
	}
}
function card_withdrawal(card_id) {
	var address = $('#withdraw_address').val();
	var name = $('#withdraw_name').val();
	
	var url = "/ajax/withdraw.php?action=card_withdrawal&address="+address+"&card_id="+card_id+"&name="+name;
	$.get(url, function(result) {
		if (result == "Beyonic request was successful!") {
			alert('Great, your money has been sent!');
			window.location = window.location;
		}
		else if (result == "2") {
			alert("There was an error withdrawing.  It looks like our hot wallet is out of money right now.");
		}
		else {
			alert("There was an error redeeming your card. The error code was: "+result);
		}
	});
}
function claim_card(claim_type) {
	var btn_id = "";
	var btn_original_text = "";
	if (claim_type == "to_address") btn_id = 'claim_address_btn';
	else if (claim_type == "to_game") btn_id = 'claim_game_btn_'+card_id+'_'+issuer_id;
	else if (claim_type == "to_account") btn_id = 'claim_account_btn_'+card_id+'_'+issuer_id;
	
	if ($('#'+btn_id).html() != "Loading...") {
		btn_original_text = $('#'+btn_id).html();
		
		$('#'+btn_id).html("Loading...");
		
		var ajax_url = "/ajax/account_spend.php?action=withdraw_from_card&claim_type="+claim_type+"&card_id="+card_id+"&issuer_id="+issuer_id;
		if (claim_type == "to_address") ajax_url += "&fee="+$('#claim_fee').val()+"&address="+$('#claim_address').val();
		
		$.get(ajax_url, function(result) {
			$('#'+btn_id).html(btn_original_text);
			var result_obj = JSON.parse(result);
			
			if (claim_type == "address") {
				$('#'+btn_id).html(btn_original_text);
				$('#claim_message').html(result_obj['message']);
				$('#claim_message').show("fast");
			}
			else {
				if (result_obj['status_code'] == 1) window.location = result_obj['message'];
				else alert(result_obj['message']);
			}
		});
	}
}
var betting_mode = false;
function toggle_betting_mode(to_betting_mode) {
	if (betting_mode !== false) {
		$('#betting_mode_'+betting_mode).hide();
	}
	$('#betting_mode_'+to_betting_mode).show();
	betting_mode = to_betting_mode;
	
	$.get("/ajax/set_betting_mode.php?game_id="+games[0].game_id+"&mode="+to_betting_mode, function(result) {});
}
function submit_principal_bet() {
	var principal_amount = $('#principal_amount').val();
	var principal_option_id = $('#principal_option_id').val();
	var principal_fee = $('#principal_fee').val();
	
	$('#principal_bet_btn').html("Loading...");
	$.get("/ajax/principal_bet.php?game_id="+games[0].game_id+"&amount="+principal_amount+"&option_id="+principal_option_id+"&fee="+principal_fee, function(result) {
		var result_obj = JSON.parse(result);
		$('#principal_bet_btn').html('<i class="fas fa-check-circle"></i> &nbsp; Confirm Bet');
		$('#principal_bet_message').html(result_obj['message']);
		console.log(result);
	});
}
function save_featured_strategy() {
	var featured_strategy_id = $("input[name='featured_strategy_id']:checked").val();
	
	if (featured_strategy_id) {
		$('#featured_strategy_save_btn').html("Saving...");
		
		$.get("/ajax/set_featured_strategy.php?game_id="+games[0].game_id+"&featured_strategy_id="+featured_strategy_id, function(result) {
			$('#featured_strategy_save_btn').html("Save");
			var result_obj = JSON.parse(result);
			console.log(result_obj);
			$("input[name=voting_strategy][value='featured']").prop("checked",true);
		});
	}
}
function apply_game_definition(game_id) {
	var apply_url = "/ajax/apply_game_definition.php?game_id="+game_id;
	
	$.get(apply_url, function(result) {
		var result_obj = JSON.parse(result);
		alert(result_obj['message']);
		console.log(result_obj);
	});
}
function filter_changed(which_filter) {
	var filter_value = $('#filter_by_'+which_filter).val();
	
	if (which_filter == "date") {
		games[0].filter_date = filter_value;
		console.log("filter date: "+games[0].filter_date);
	}
}
function change_password() {
	$('#change_password_btn').html("Loading...");
	
	$('#change_password_existing').val(Sha256.hash($('#change_password_existing').val()));
	$('#change_password_new').val(Sha256.hash($('#change_password_new').val()));
	
	$.get("/ajax/change_password.php?username="+$('#change_password_username').val()+"&existing="+$('#change_password_existing').val()+"&new="+$('#change_password_new').val(), function(result) {
		var result_obj = JSON.parse(result);
		
		$('#change_password_btn').html("Change my Password");
		$('#change_password_username').val("");
		$('#change_password_existing').val("");
		$('#change_password_new').val("");
		
		alert(result_obj['message']);
	});
}
var existing_account = false;

function generate_credentials() {
	$.get("/ajax/check_username.php?action=generate", function(result) {
		var result_obj = JSON.parse(result);
		$('#generate_display').html(result_obj['message']);
		$('#login_password').val($('#generate_password').val());
		$('#username').val($('#generate_username').val());
	});
}
function check_username() {
	var username = $('#username').val();
	$('#check_username_btn').html("Loading...");
	
	$.get("/ajax/check_username.php?username="+encodeURIComponent(username), function(result) {
		$('#check_username_btn').html("Continue");
		var result_obj = JSON.parse(result);
		
		$('#login_message').html(result_obj['message']);
		$('#login_message').show();
		
		if (result_obj['status_code'] == 3 || result_obj['status_code'] == 4) {
			toggle_to_panel('password');
			
			if (result_obj['status_code'] == 4) {
				$('#login_btn').html("Sign Up");
			}
			else $('#login_btn').html("Log In");
		}
		else if (result_obj['status_code'] == 1 || result_obj['status_code'] == 2) {
			login();
		}
	});
}
function login() {
	if ($('#login_password').val() != "") $('#login_password').val(Sha256.hash($('#login_password').val()));
	var username = $('#username').val();
	var password = $('#login_password').val();
	$('#login_btn').val("Loading...");
	$.get("/ajax/log_in.php?username="+encodeURIComponent(username)+"&password="+encodeURIComponent(password)+"&redirect_key="+$('#redirect_key').val(), function(result) {
		$('#login_btn').val("Log In");
		var result_obj = JSON.parse(result);
		
		if (result_obj['status_code'] == 1) {
			window.location = result_obj['message'];
		}
		else {
			$('#login_password').val("");
			alert(result_obj['message']);
		}
	});
}
var selected_panel = false;

function toggle_to_panel(which_panel) {
	if (selected_panel) $('#'+selected_panel+'_panel').hide();
	
	if (which_panel == 'noemail') {
		which_panel = 'generate';
		
		$('#login_panel').hide();
	}
	
	selected_panel = which_panel;
	
	$('#'+selected_panel+'_panel').show('fast');
	
	if (which_panel == "login") setTimeout("$('#username').focus();", 500);
	else if (selected_panel == 'password') setTimeout("$('#login_password').focus();", 500);
}
function manage_game_event_filter_changed() {
	var event_filter = $('#manage_game_event_filter').val();
	window.location = '/manage/'+games[0].game_url_identifier+'/?next=events&event_filter='+event_filter;
}