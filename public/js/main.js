
var EventOption = function(parent_event, option_index, option_id, db_option_index, name, points, has_votingaddr, image_url) {
	this.parent_event = parent_event;
	this.option_index = option_index;
	this.option_id = option_id;
	this.db_option_index = db_option_index;
	this.name = name;
	this.points = points;
	this.has_votingaddr = has_votingaddr;
	this.image_url = image_url;
	
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
};
var ChainIO = function(chain_io_index, io_id, amount, create_block_id) {
	this.chain_io_index = chain_io_index;
	this.io_id = io_id;
	this.amount = amount;
	this.create_block_id = (create_block_id == "") ? null : parseInt(create_block_id);
	this.game_ios = [];
	
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
var GameIO = function(game_io_id, amount, create_block_id) {
	this.game_io_id = game_io_id;
	this.amount = amount;
	this.create_block_id = (create_block_id == "") ? null : parseInt(create_block_id);
};
var BetInput = function(input_index, ref_io) {
	this.input_index = input_index;
	this.ref_io = ref_io;
};
var BetOutput = function(option_index, name, option_id, event_index) {
	this.option_index = option_index;
	this.name = name;
	this.option_id = option_id;
	this.event_index = event_index;
	this.slider_val = 50;
	this.amount = 0;
};
var PlanRound = function(round_id) {
	this.round_id = round_id;
	this.event_ids = [];
	this.sum_points = 0;
};
var GameEvent = function(game, game_event_index, event_id, real_event_index, num_voting_options, vote_effectiveness_function, effectiveness_param1, option_block_rule, event_name, event_starting_block, event_final_block, payout_rate) {
	this.game = game;
	this.game_event_index = game_event_index;
	this.event_id = event_id;
	this.real_event_index = real_event_index;
	
	this.num_voting_options = num_voting_options;
	this.vote_effectiveness_function = vote_effectiveness_function;
	this.effectiveness_param1 = effectiveness_param1;
	this.option_block_rule = option_block_rule;
	this.event_name = event_name;
	this.event_starting_block = event_starting_block;
	this.event_final_block = event_final_block;
	this.payout_rate = payout_rate;
	
	this.rendered_event_hash = "";
	
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
	
	this.options = [];
	this.option_id2option_index = {};
	
	this.start_vote = function(option_id) {
		var option_display_name = this.options[this.db_id2option_index(option_id)].name;
		option_display_name += ' (<a href="/explorer/games/'+games[this.game.instance_id].game_url_identifier+'/events/'+this.real_event_index+'">'+this.event_name+'</a>)';
		games[this.game.instance_id].add_option_to_vote(game_event_index, option_id);
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
	this.refresh_time_estimate = function() {
		if (this.deleted == false) {
			if (this.option_block_rule == "football_match") {
				var blocks_into_game = this.game.last_block_id > 0 ? Math.max(0, this.game.last_block_id - this.event_starting_block) : 0;
				var event_sim_time_sec = 90*60;
				var sec_into_game = 0;
				
				if (this.game.last_block_id >= this.event_final_block) {
					sec_into_game = event_sim_time_sec;
				}
				else {
					var block_sim_time_sec = Math.round(event_sim_time_sec/(this.event_final_block-this.event_starting_block));
					sec_into_game = blocks_into_game*block_sim_time_sec;
					
					var sec_since_block_loaded;
					if (this.game.time_last_block_loaded > 0) sec_since_block_loaded = ((new Date().getTime())/1000 - this.game.time_last_block_loaded);
					else sec_since_block_loaded = 0;
					
					var expected_sec_this_block = sec_since_block_loaded + this.game.seconds_per_block;
					var sim_sec_into_block = Math.round((sec_since_block_loaded/expected_sec_this_block)*block_sim_time_sec);
					sec_into_game += sim_sec_into_block;
				}
				
				var min_disp = Math.floor(sec_into_game/60);
				var sec_disp = leftpad(sec_into_game - min_disp*60, 2, "0");
				if (document.getElementById('game'+this.game.instance_id+'_event'+this.game_event_index+'_timer')) {
					document.getElementById('game'+this.game.instance_id+'_event'+this.game_event_index+'_timer').innerHTML = min_disp+":"+sec_disp;
				}
			}
			setTimeout(function() {this.refresh_time_estimate()}.bind(this), 1000);
		}
	};
	this.block_id_to_effectiveness_factor = function(block_id) {
		if (this.vote_effectiveness_function == "linear_decrease") {
			var slope = -1*this.effectiveness_param1;
			var event_length_blocks = this.event_final_block-this.event_starting_block+1;
			var blocks_in = block_id-this.event_starting_block;
			var frac_complete = blocks_in/event_length_blocks;
			var effectiveness = Math.floor(Math.pow(10,8)*frac_complete*slope)/Math.pow(10,8) + 1;
			return effectiveness;
		}
		else return 1;
	};
};
var Game = function(pageManager, game_id, last_block_id, last_transaction_id, mature_io_ids_csv, payout_weight, game_round_length, fee_amount, game_url_identifier, coin_name, coin_name_plural, chain_coin_name, chain_coin_name_plural, refresh_page, event_ids, logo_image_url, vote_effectiveness_function, effectiveness_param1, seconds_per_block, inflation, exponential_inflation_rate, time_last_block_loaded, decimal_places, blockchain_decimal_places, view_mode, initial_event_index, filter_date, default_betting_mode, render_events) {
	Game.numInstances = (Game.numInstances || 0) + 1;
	this.instance_id = Game.numInstances-1;
	
	this.pageManager = pageManager;
	this.game_id = game_id;
	this.last_block_id = last_block_id;
	this.last_transaction_id = last_transaction_id;
	this.mature_io_ids_csv = mature_io_ids_csv ? mature_io_ids_csv : "";
	this.mature_io_ids_hash = Sha256.hash(this.mature_io_ids_csv);
	this.payout_weight = payout_weight;
	this.game_round_length = game_round_length;
	this.fee_amount = fee_amount;
	this.game_url_identifier = game_url_identifier;
	this.coin_name = coin_name;
	this.coin_name_plural = coin_name_plural;
	this.chain_coin_name = chain_coin_name;
	this.chain_coin_name_plural = chain_coin_name_plural;
	this.refresh_page = refresh_page;
	this.event_ids = event_ids ? event_ids : "";
	this.event_ids_hash = Sha256.hash(this.event_ids);
	this.logo_image_url = logo_image_url;
	this.vote_effectiveness_function = vote_effectiveness_function;
	this.effectiveness_param1 = effectiveness_param1;
	this.seconds_per_block = parseInt(seconds_per_block);
	this.inflation = inflation;
	this.exponential_inflation_rate = parseFloat(exponential_inflation_rate);
	this.decimal_places = parseInt(decimal_places);
	this.blockchain_decimal_places = parseInt(blockchain_decimal_places);
	this.view_mode = view_mode;
	this.selected_event_index = initial_event_index;
	this.filter_date = filter_date;
	this.default_betting_mode = default_betting_mode;
	this.render_events = render_events;
	
	if (filter_date) {
		document.getElementById('filter_by_date').value(filter_date);
	}
	
	this.events = [];
	this.all_events = [];
	this.all_events_start_index = false;
	this.all_events_stop_index = false;
	this.option_has_votingaddr = [];
	this.sel_game_event_index = false;
	this.all_events_db_id_to_index = {};
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
		return ((block_id-1) % this.game_round_length);
	};
	this.block_to_round = function(block_id) {
		return Math.ceil(block_id/this.game_round_length);
	};
	this.add_option_to_vote = function(event_index, option_id) {
		var this_event = this.events[event_index];
		var this_option = this_event.options[this_event.option_id2option_index[option_id]];
		var option_display_name = this_option.name;
		
		if (this.refresh_page != "wallet") {
			var add_option_url = '/wallet/'+games[0].game_url_identifier+'/?action=start_bet&event_index='+event_index+'&option_id='+option_id;
			if (thisuser) {
				window.location = add_option_url;
			}
			else {
				var ans = confirm("To bet, first log in to your wallet.");
				if (ans) window.location = add_option_url;
			}
		}
		else {
			var index_id = this.pageManager.bet_outputs.length;
			
			if (games[0].option_has_votingaddr[option_id]) {
				this.pageManager.bet_outputs.push(new BetOutput(index_id, option_display_name, option_id, event_index));
				$('#compose_bet_outputs').append('<div id="compose_bet_output_'+index_id+'" class="select_utxo">'+this.pageManager.render_option_output(index_id, option_display_name)+'</div>');
				
				this.pageManager.load_option_slider(index_id);
				this.pageManager.refresh_compose_bets();
				this.pageManager.refresh_output_amounts();
			}
			else {
				alert("You can't vote for this candidate yet, you don't have a voting address for it.");
			}
		}
	};
	this.set_user_game_event_index = function() {
		$.ajax({
			url: "/ajax/set_user_game_event_index.php",
			data: {
				game_id: games[0].game_id,
				event_index: this.selected_event_index,
				synchronizer_token: this.pageManager.synchronizer_token
			}
		});
	};
	this.show_selected_event = function(skip_set_event_index) {
		if (this.selected_event_index > this.events.length-1) {
			this.selected_event_index = 0;
		}
		var event_nav_txt = "Viewing "+(this.selected_event_index+1)+" of "+this.events.length;
		event_nav_txt += " &nbsp;&nbsp;&nbsp; <a href='' onclick='games["+this.instance_id+"].show_previous_event(); return false;'>Previous</a> &nbsp; <a href='' onclick='games["+this.instance_id+"].show_next_event(); return false;'>Next</a> &nbsp;&nbsp;&nbsp; Jump to: <input id=\"jump_to_event_index_"+this.selected_event_index+"\" class=\"form-control input-sm\" style=\"width: 80px; display: inline-block;\" /><button class=\"btn btn-primary btn-sm\" onclick=\"games["+this.instance_id+"].hide_selected_event(); games["+this.instance_id+"].selected_event_index=parseInt($('#jump_to_event_index_"+this.selected_event_index+"').val())-1; games["+this.instance_id+"].show_selected_event(false);\">Go</button>";
		
		document.getElementById('game'+this.instance_id+'_event'+this.selected_event_index).style.display = 'block';
		document.getElementById('game'+this.instance_id+'_event'+this.selected_event_index+'_event_nav').innerHTML = event_nav_txt;
		
		if (!skip_set_event_index) this.set_user_game_event_index();
		
		this.render_event_images(this.event_index_to_previous(this.selected_event_index));
		this.render_event_images(this.selected_event_index);
		this.render_event_images(this.event_index_to_next(this.selected_event_index));
	};
	this.render_event_images = function(event_index) {
		if (typeof this.events[event_index] !== "undefined") {
			for (var i=0; i<this.events[event_index].options.length; i++) {
				$('#option'+this.events[event_index].options[i].option_id+'_image').attr("src", this.events[event_index].options[i].image_url);
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
			
			$.ajax({
				url: "/ajax/check_new_activity.php",
				dataType: "json",
				data: {
					instance_id: this.instance_id,
					game_id: this.game_id,
					event_ids_hash: this.event_ids_hash,
					refresh_page: this.refresh_page,
					last_block_id: this.last_block_id,
					last_transaction_id: this.last_transaction_id,
					mature_io_ids_hash: this.mature_io_ids_hash,
					game_loop_index: this.game_loop_index,
					event_hashes: _.map(this.events, 'rendered_event_hash').join(","),
					filter_date: this.filter_date ? this.filter_date : "",
					synchronizer_token: this.pageManager.synchronizer_token
				},
				context: this,
				success: function(check_activity_response) {
					this.refresh_in_progress = false;
					
					if (check_activity_response.game_loop_index > this.last_game_loop_index_applied) {
						if (check_activity_response.new_block == 1) {
							this.last_block_id = parseInt(check_activity_response.last_block_id);
							this.time_last_block_loaded = parseInt(check_activity_response.time_last_block_loaded);
							
							if (this.refresh_page == "wallet") {
								if (parseInt(check_activity_response.new_performance_history) == 1) {
									$('#performance_history_new').html(check_activity_response.performance_history);
								}
							}
						}
						
						if (parseInt(check_activity_response.new_event_ids) == 1) {
							eval(check_activity_response.new_event_js);
							
							this.event_ids = check_activity_response.event_ids;
							this.event_ids_hash = Sha256.hash(check_activity_response.event_ids);
							
							this.pageManager.set_select_add_output();
							
							if (this.view_mode == "simple") {
								this.hide_selected_event();
								this.selected_event_index = 0;
								this.show_selected_event(false);
							}
						}
						
						if (this.refresh_page == "wallet") {
							$('#game_status_explanation').html(check_activity_response.game_status_explanation);
							if (check_activity_response.game_status_explanation == '') $('#game_status_explanation').hide();
							else $('#game_status_explanation').show();
							
							if (parseInt(check_activity_response.new_mature_ios) == 1 || parseInt(check_activity_response.new_transaction) == 1 || check_activity_response.new_block == 1) {
								if (typeof check_activity_response.mature_io_ids_csv == "undefined") this.mature_io_ids_csv = "";
								else this.mature_io_ids_csv = check_activity_response.mature_io_ids_csv;
								this.mature_io_ids_hash = Sha256.hash(this.mature_io_ids_csv);
								$('#select_input_buttons').html(check_activity_response.select_input_buttons);
								this.pageManager.reload_compose_bets();
								this.pageManager.utxo_spend_offset = 0;
							}
							
							this.pageManager.set_input_amount_sums();
							this.pageManager.refresh_mature_io_btns();
							
							if (parseInt(check_activity_response.new_messages) == 1) {
								var new_message_user_ids = check_activity_response.new_message_user_ids.split(",");
								for (var i=0; i<new_message_user_ids.length; i++) {
									this.pageManager.openChatWindow(new_message_user_ids[i]);
								}
							}
						}
						
						if (typeof check_activity_response.chart_html != "undefined") {
							$('#game'+this.instance_id+'_chart_html').html(check_activity_response.chart_html);
							$('#game'+this.instance_id+'_chart_js').html('<script type="text/javascript">'+check_activity_response.chart_js+'</script>');
						}
						
						if (parseInt(check_activity_response.new_mature_ios) == 1) {
							$('#account_value').html(check_activity_response.account_value);
							$('#account_value').hide();
							$('#account_value').fadeIn('medium');
							
							$('#wallet_text_stats').html(check_activity_response.wallet_text_stats);
							$('#wallet_text_stats').hide();
							$('#wallet_text_stats').fadeIn('fast');
						}
						
						for (var game_event_index=0; game_event_index<this.events.length; game_event_index++) {
							if (check_activity_response.rendered_events[game_event_index].hash != false) {
								this.events[game_event_index].rendered_event_hash = check_activity_response.rendered_events[game_event_index].hash;
								$('#game'+this.instance_id+'_event'+game_event_index+'_display').html(check_activity_response.rendered_events[game_event_index].html);
								$('#game'+this.instance_id+'_event'+game_event_index+'_display').hide();
								$('#game'+this.instance_id+'_event'+game_event_index+'_display').show();
								
								this.render_event_images(game_event_index);
								
								if (this.events[game_event_index].details_shown) {
									$('#game'+this.instance_id+'_event'+game_event_index+'_details').show();
								}
								else {
									$('#game'+this.instance_id+'_event'+game_event_index+'_details').hide();
								}
								
								if (typeof check_activity_response.my_current_votes != "undefined" && typeof check_activity_response.my_current_votes[game_event_index] != "undefined") {
									$('#game'+this.instance_id+'_event'+game_event_index+'_my_current_votes').html(check_activity_response.my_current_votes[game_event_index]);
								}
							}
						}
						
						if (check_activity_response.set_options_js != "") {
							eval(check_activity_response.set_options_js);
							
							this.pageManager.refresh_output_amounts();
						}
						
						this.last_game_loop_index_applied = check_activity_response.game_loop_index;
					}
				},
				error: function(XMLHttpRequest, textStatus, errorThrown) {
					this.refresh_in_progress = false;
					console.log("Game loop web request failed.");
				}
			});
		}
	};
	this.game_loop_event = function() {
		this.refresh_if_needed();
		this.game_loop_index++;
		setTimeout(function() {this.game_loop_event()}.bind(this), 2000);
	};
};
var User = function(id) {
	this.userId = id;
};
var ChatWindow = function(chatWindowId, toUserId) {
	this.chatWindowId = chatWindowId;
	this.toUserId = toUserId;
	
	this.initialize = function() {};
};
var PageManager = function() {
	this.chatWindows = [];
	this.userId2ChatWindowId = [];
	this.visibleChatWindows = 0;

	this.last_event_index_shown = false;
	this.event_outcome_sections_shown = 1;

	this.game_form_vars = ['blockchain_id', 'module', 'event_rule', 'option_group_id', 'event_entity_type_id', 'events_per_round', 'event_type_name', 'maturity', 'name', 'payout_weight', 'round_length', 'pos_reward', 'pow_reward', 'inflation', 'exponential_inflation_rate', 'exponential_inflation_minershare', 'final_round', 'coin_name', 'coin_name_plural', 'coin_abbreviation', 'start_condition', 'buyin_policy', 'game_buyin_cap', 'default_vote_effectiveness_function', 'default_effectiveness_param1', 'default_max_voting_fraction', 'game_starting_block', 'escrow_address', 'genesis_tx_hash', 'genesis_amount', 'default_betting_mode', 'finite_events'];
	this.event_verbatim_vars = ['event_index', 'next_event_index', 'event_starting_block', 'event_final_block', 'event_payout_block', 'event_starting_time', 'event_final_time', 'event_payout_time', 'event_name', 'option_block_rule', 'option_name', 'option_name_plural', 'outcome_index', 'payout_rule', 'payout_rate', 'track_max_price', 'track_min_price', 'track_payout_price', 'track_name_short'];

	this.transaction_in_progress = false;
	this.invoice_id = false;

	this.burn_io_amount = "";
	this.editing_game_id = false;
	this.bet_inputs = [];
	this.bet_outputs = [];
	this.output_amounts_need_update = false;
	this.io_id2input_index = {};
	this.chain_ios = [];
	this.utxo_spend_offset = 0;

	this.account_io_id = false;
	this.account_io_amount = false;
	this.account_game_id = false;
	this.selected_account_action = false;
	this.set_event_id = false;

	this.betting_mode = false;
	this.existing_account = false;
	this.selected_panel = false;
	
	this.reset_in_progress = false;
	this.selected_card = -1;
	this.selected_section = false;
	this.remove_utxo_ms = 50;
	
	this.format_coins = function(amount) {
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
			return this.rtrim((amount).toPrecision(5), "0.");
		}
		else return parseFloat((amount).toPrecision(5));
	}
	this.explorer_search = function() {
		var search_params = {
			search_term: $('#explorer_search').val(),
			synchronizer_token: this.synchronizer_token
		};
		if (typeof games !== "undefined" && games.length > 0) search_params.game_id = games[0].game_id;
		if (typeof this.blockchain_id !== "undefined") search_params.blockchain_id = this.blockchain_id;
		
		$.ajax({
			url: "/ajax/explorer_search.php",
			data: search_params,
			dataType: 'json',
			success: function(search_response) {
				if (search_response.status_code == 1) window.location = search_response.message;
				else alert(search_response.message);
			}
		});
	}
	this.openChatWindow = function(userId) {
		if (typeof userId2ChatWindowId[userId] === 'undefined' || userId2ChatWindowId[userId] === false) {
			var chatWindowId = chatWindows.length;
			this.newChatWindow(chatWindowId, userId);
		}
	}
	this.newChatWindow = function(chatWindowId, userId) {
		this.chatWindows[chatWindowId] = new ChatWindow(chatWindowId, userId);
		this.userId2ChatWindowId[userId] = chatWindowId;
		
		$('#chatWindows').append('<div id="chatWindow'+chatWindowId+'" class="chatWindow"></div>');
		$('#chatWindow'+chatWindowId).css("right", chatWindowId*230);
		$('#chatWindow'+chatWindowId).html(this.baseChatWindow(chatWindowId));
		this.renderChatWindow(chatWindowId);
		$('#chatWindowWriter'+chatWindowId).focus();
	}
	this.closeChatWindow = function(chatWindowId) {
		this.userId2ChatWindowId[this.chatWindows[chatWindowId].toUserId] = false;
		for (var i=chatWindowId+1; i<this.chatWindows.length; i++) {
			this.userId2ChatWindowId[this.chatWindows[i].toUserId] = i-1;
			this.chatWindows[i-1] = this.chatWindows[i];
			$('#chatWindow'+(i-1)).html(this.baseChatWindow(i-1));
			this.renderChatWindow(i-1);
		}
		$('#chatWindow'+(this.chatWindows.length-1)).remove();
		this.chatWindows.length = this.chatWindows.length-1;
	}
	this.baseChatWindow = function(chatWindowId) {
		return $('#chatWindowTemplate').html().replace(/CHATID/g, chatWindowId);
	}
	this.renderChatWindow = function(chatWindowId) {
		$('#chatWindowTitle'+chatWindowId).html('Loading...');
		$('#chatWindowContent'+chatWindowId).html('');
		
		$.ajax({
			url: "/ajax/chat.php",
			dataType: 'json',
			data: {
				action: "fetch",
				game_id: games[0].game_id,
				user_id: this.chatWindows[chatWindowId].toUserId,
				synchronizer_token: this.synchronizer_token
			},
			success: function(chat_response) {
				$('#chatWindowTitle'+chatWindowId).html(chat_response.username);
				$('#chatWindowContent'+chatWindowId).html(chat_response.content);
				$('#chatWindowContent'+chatWindowId).scrollTop($('#chatWindowContent'+chatWindowId)[0].scrollHeight);
			}
		});
	}
	this.sendChatMessage = function(chatWindowId) {
		var message = $('#chatWindowWriter'+chatWindowId).val();
		$('#chatWindowSendBtn'+chatWindowId).html("...");
		$('#chatWindowWriter'+chatWindowId).val("");
		
		$.ajax({
			url: "/ajax/chat.php",
			dataType: 'json',
			data: {
				action: "send",
				game_id: games[0].game_id,
				user_id: this.chatWindows[chatWindowId].toUserId,
				message: message,
				synchronizer_token: this.synchronizer_token
			},
			success: function(chat_response) {
				$('#chatWindowSendBtn'+chatWindowId).html("Send");
				$('#chatWindowContent'+chatWindowId).html(chat_response.content);
				$('#chatWindowContent'+chatWindowId).scrollTop($('#chatWindowContent'+chatWindowId)[0].scrollHeight);
			}
		});
	}
	this.tab_clicked = function(index_id) {
		if (this.current_tab !== false) {
			$('#tabcell'+this.current_tab).removeClass("active");
			$('#tabcontent'+this.current_tab).hide();
		}
		
		$('#tabcell'+index_id).addClass("active");
		$('#tabcontent'+index_id).show();
		
		this.current_tab = index_id;
		
		if (index_id == 1) {
			this.refresh_players();
		}
	}
	this.refresh_players = function() {
		$('#tabcontent1').html("Loading...");
		$.ajax({
			url: "/ajax/show_players.php",
			data: {
				game_id: games[0].game_id,
				synchronizer_token: this.synchronizer_token
			},
			success: function(show_players_html) {
				$('#tabcontent1').html(show_players_html);
			}
		});
	}
	this.claim_from_faucet = function() {
		var faucet_btn_txt = $('#faucet_btn').html();
		$('#faucet_btn').html("Loading...");
		
		$.ajax({
			url: "/ajax/faucet.php",
			dataType: 'json',
			data: {
				game_id: games[0].game_id,
				action: "claim",
				synchronizer_token: this.synchronizer_token
			},
			success: function(faucet_response) {
				$('#faucet_btn').html(faucet_btn_txt);
				
				if (faucet_response.status_code == 1) {
					window.location = '/wallet/'+games[0].game_url_identifier+'/';
					return false;
				}
				else alert(faucet_response.message);
				
				games[0].refresh_if_needed();
			}
		});
	}
	this.rank_check_all_changed = function() {
		var set_checked = false;
		if ($('#rank_check_all').is(":checked")) set_checked = true;
		for (var i=1; i<=games[0].num_voting_options; i++) {
			$('#by_rank_'+i).prop("checked", set_checked);
		}
	}
	this.vote_on_block_all_changed = function() {
		var set_checked = false;
		if ($('#vote_on_block_all').is(":checked")) set_checked = true;
		for (var i=1; i<=games[0].game_round_length; i++) {
			$('#vote_on_block_'+i).prop("checked", set_checked);
		}
	}
	this.by_entity_reset_pct = function() {
		for (var option_id=1; option_id<=games[0].num_voting_options; option_id++) {
			$('#option_pct_'+option_id).val("0");
		}
	}
	this.next_block = function() {
		if ($('#next_block_btn').html() == "Next Block") {
			$('#next_block_btn').html("Loading...");
			
			$.ajax({
				url: "/ajax/next_block.php",
				data: {
					game_id: games[0].game_id,
					synchronizer_token: this.synchronizer_token
				},
				success: function() {
					games[0].refresh_if_needed();
				}
			});
		}
	}
	this.notification_pref_changed = function() {
		var notification_pref = $('#notification_preference').val();
		if (notification_pref == "email") {
			$('#notification_email').show('fast');
			$('#notification_email').focus();
		}
		else {
			$('#notification_email').hide();
		}
	}
	this.save_notification_preferences = function() {
		var btn_text = '<i class="fas fa-check-circle"></i> &nbsp; Save Notification Settings';
		
		if ($('#notification_save_btn').html() == btn_text) {
			$('#notification_save_btn').html("Saving...");
			
			$.ajax({
				url: "/ajax/set_notification_preference.php",
				data: {
					game_id: games[0].game_id,
					preference: $('#notification_preference').val(),
					email: $('#notification_email').val(),
					synchronizer_token: this.synchronizer_token
				},
				success: function(set_notification_response) {
					$('#notification_save_btn').html(btn_text);
					alert(set_notification_response);
				}
			});
		}
	}
	this.attempt_withdrawal = function() {
		var amount = $('#withdraw_amount').val();
		var address = $('#withdraw_address').val();
		
		var initial_btn_text = $('#withdraw_btn').html();
		var loading_btn_text = "Sending...";
		
		if (initial_btn_text != loading_btn_text) {
			$('#withdraw_btn').html(loading_btn_text);
			
			$.ajax({
				url: "/ajax/withdraw.php",
				dataType: "json",
				data: {
					game_id: games[0].game_id,
					amount: amount,
					address: address,
					remainder_address_id: $('#withdraw_remainder_address_id').val(),
					fee: $('#withdraw_fee').val(),
					synchronizer_token: this.synchronizer_token
				},
				success: function(withdraw_response) {
					$('#withdraw_btn').html(initial_btn_text);
					$('#withdraw_amount').val("");
					
					$('#withdraw_message').removeClass("redtext");
					$('#withdraw_message').removeClass("greentext");
					
					$('#withdraw_message').show('fast');
					$('#withdraw_message').html(withdraw_response.message);
					
					if (withdraw_response.status_code == 1) $('#withdraw_message').addClass("greentext");
					else $('#withdraw_message').addClass("redtext");
					
					setTimeout(function() {$('#withdraw_message').slideUp('fast')}, 10000);
				}
			});
		}
		else alert("Already sending");
	}
	this.input_amount_sums = function() {
		var io_sum = 0;
		var amount_sum = 0;
		var vote_sum = 0;
		
		for (var i=0; i<this.bet_inputs.length; i++) {
			io_sum += this.bet_inputs[i].ref_io.amount;
			vote_sum += this.bet_inputs[i].ref_io.votes_at_block(games[0].last_block_id+1);
			amount_sum += this.bet_inputs[i].ref_io.game_amount_sum();
		}
		return [io_sum, amount_sum, vote_sum];
	}
	this.set_input_amount_sums = function() {
		var amount_sums = this.input_amount_sums();
		var burn_amount = 0;
		if ($('#compose_burn_amount').val() != "") burn_amount += parseFloat($('#compose_burn_amount').val())*Math.pow(10,games[0].decimal_places);
		
		var input_disp = this.format_coins(amount_sums[1]/Math.pow(10,games[0].decimal_places));
		if (input_disp == '1') input_disp += ' '+games[0].coin_name;
		else input_disp += ' '+games[0].coin_name_plural;
		$('#input_amount_sum').html(input_disp);
		
		if (games[0].payout_weight == 'coin') $('#input_amount_sum').show();
		else {
			if (games[0].inflation == "exponential") {
				var coin_equiv = Math.round(amount_sums[2]*games[0].coins_per_vote);
				var coin_disp = this.format_coins((coin_equiv+burn_amount)/Math.pow(10,games[0].decimal_places));
				
				var render_text = coin_disp+" ";
				if (coin_disp == '1') render_text += games[0].coin_name;
				else render_text += games[0].coin_name_plural;
				$('#input_vote_sum').html(render_text);
			}
			else {
				$('#input_vote_sum').html(this.format_coins(amount_sums[2]/Math.pow(10,games[0].decimal_places))+" votes");
			}
			this.output_amounts_need_update = true;
		}
		
		var max_burn_amount = Math.floor(amount_sums[1]*0.9/Math.pow(10,games[0].decimal_places));
		$('#max_burn_amount').html("Up to "+this.format_coins(max_burn_amount));
	}
	this.render_bet_input = function(index_id) {
		if (games[0].logo_image_url != "") {
			return "";
		}
		else {
			var votes = this.bet_inputs[index_id].ref_io.votes_at_block(games[0].last_block_id);
			var render_text;
			
			if (games[0].inflation == "exponential") {
				var coin_equiv = Math.round(votes*games[0].coins_per_vote);
				var disp_coins = coin_equiv;
				if (games[0].default_betting_mode == "principal" || games[0].coins_per_vote == 0) disp_coins += this.bet_inputs[index_id].ref_io.game_amount_sum();
				var coin_disp = this.format_coins(disp_coins/Math.pow(10,games[0].decimal_places));
				
				render_text = coin_disp+" ";
				if (coin_disp == '1') render_text += games[0].coin_name;
				else render_text += games[0].coin_name_plural;
			}
			else {
				render_text = this.format_coins(votes/Math.pow(10,games[0].decimal_places))+' ';
				if (games[0].payout_weight == "coin") {
					if (render_text == '1') render_text += games[0].coin_name;
					else render_text += games[0].coin_name_plural;
				}
				else render_text += ' votes';
			}
			return render_text;
		}
	}
	this.render_option_output = function(index_id, name) {
		var html = "";
		html += name+'&nbsp;&nbsp; <div id="output_amount_disp_'+index_id+'" class="output_amount_disp"></div> <font class="output_removal_link" onclick="thisPageManager.remove_option_from_vote('+index_id+');">&#215;</font>';
		html += '<div><div id="output_threshold_'+index_id+'" class="noUiSlider"></div></div>';
		return html;
	}
	this.add_utxo_to_vote = function(io_index) {
		var index_id = this.bet_inputs.length;
		
		this.bet_inputs.push(new BetInput(index_id, this.chain_ios[io_index]));
		
		$('#select_utxo_'+this.chain_ios[io_index].io_id).hide('fast');
		
		var select_btn_html = '<div id="selected_utxo_'+index_id+'" onclick="thisPageManager.remove_utxo_from_vote('+index_id+');" class="select_utxo';
		if (games[0].logo_image_url != "") select_btn_html += ' select_utxo_image';
		select_btn_html += ' btn btn-primary btn-sm">'+this.render_bet_input(index_id)+'</div>';
		$('#compose_bet_inputs').append(select_btn_html);
		
		this.io_id2input_index[this.chain_ios[io_index].io_id] = index_id;
		
		this.refresh_compose_bets();
		this.set_input_amount_sums();
		this.refresh_output_amounts();
	}
	this.add_all_utxos_to_vote = function() {
		var ms_per_add = 50;
		
		for (var i=0; i<this.chain_ios.length; i++) {
			(function(utxo_index, _this) {
				setTimeout(function() {
					if (typeof _this.io_id2input_index[_this.chain_ios[utxo_index].io_id] == "undefined") {
						_this.add_utxo_to_vote(utxo_index);
					}
				}.bind(_this), utxo_index*ms_per_add);
			})(i, this);
		}
		setTimeout(function() {
			this.refresh_compose_bets();
			this.set_input_amount_sums();
			this.refresh_output_amounts();
		}.bind(this), (this.chain_ios.length+1)*ms_per_add);
	}
	this.load_option_slider = function(index_id) {
		var _this = this;
		
		$('#output_threshold_'+index_id).noUiSlider({
			range: [0, 100],
			start: 50, step: 1,
			handles: 1,
			connect: "lower",
			serialization: {
				to: [false, false],
				resolution: 1
			},
			slide: function(){
				_this.bet_outputs[index_id].slider_val = parseInt($('#output_threshold_'+index_id).val());
				_this.output_amounts_need_update = true;
			}
		});
	}
	this.remove_all_utxos_from_vote = function() {
		for (var i=0; i<this.bet_inputs.length; i++) {
			setTimeout(function() {
				this.remove_utxo_from_vote(0);
			}.bind(this), i*this.remove_utxo_ms);
		}
	}
	this.remove_utxo_from_vote = function(index_id) {
		if (this.bet_inputs[index_id]) {
			var io_id = this.bet_inputs[index_id].ref_io.io_id;
			$('#select_utxo_'+io_id).show('fast');
			
			delete this.io_id2input_index[io_id];
			
			for (var i=index_id; i<this.bet_inputs.length-1; i++) {
				this.bet_inputs[i] = this.bet_inputs[i+1];
				
				this.io_id2input_index[this.bet_inputs[i].ref_io.io_id] = i;
				
				$('#selected_utxo_'+i).html(this.render_bet_input(i));
			}
			$('#selected_utxo_'+(this.bet_inputs.length-1)).remove();
			
			this.bet_inputs.length = this.bet_inputs.length-1;
			this.set_input_amount_sums();
			this.refresh_compose_bets();
			this.refresh_output_amounts();
		}
	}
	this.remove_option_from_vote = function(index_id) {
		for (var i=index_id+1; i<this.bet_outputs.length; i++) {
			$('#compose_bet_output_'+(i-1)).html(this.render_option_output(i-1, this.bet_outputs[i].name));
			$('#compose_bet_output_'+i).html('');
			this.bet_outputs[i-1] = this.bet_outputs[i];
			this.load_option_slider(i-1);
			$('#output_threshold_'+(i-1)).val(this.bet_outputs[i-1].slider_val);
		}
		$('#compose_bet_output_'+(this.bet_outputs.length-1)).remove();
		this.bet_outputs.length = this.bet_outputs.length-1;
		
		this.refresh_output_amounts();
		this.refresh_compose_bets();
	}
	this.refresh_compose_bets = function() {
		if (this.bet_inputs.length > 0 || this.bet_outputs.length > 0) $('#compose_bets').show('fast');
		else $('#compose_bets').hide('fast');
	}
	this.finish_refresh_output_amounts = function() {
		if (this.bet_outputs.length > 0) {
			var input_sums = this.input_amount_sums();
			
			var io_amount = input_sums[0];
			var game_amount = input_sums[1];
			var votes = input_sums[2];
			var inflation_amount = votes*games[0].coins_per_vote;
			var nonfee_amount = io_amount - (games[0].fee_amount*Math.pow(10, games[0].blockchain_decimal_places));
			
			var burn_amount = 0;
			if ($('#compose_burn_amount').val() != "") {
				burn_amount = parseInt(parseFloat($('#compose_burn_amount').val())*Math.pow(10, games[0].decimal_places));
				this.burn_io_amount = Math.ceil(nonfee_amount*burn_amount/game_amount);
			}
			else this.burn_io_amount = "";
			
			var io_nondestroy_amount = nonfee_amount;
			if (this.burn_io_amount != "") io_nondestroy_amount -= this.burn_io_amount;
			var game_amount_bet = burn_amount+inflation_amount;
			
			var slider_sum = 0;
			for (var i=0; i<this.bet_outputs.length; i++) {
				slider_sum += this.bet_outputs[i].slider_val;
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
			
			for (var i=0; i<this.bet_outputs.length; i++) {
				var output_io_amount = Math.round(io_nondestroy_amount*this.bet_outputs[i].slider_val/slider_sum);
				if (i == this.bet_outputs.length-1) output_io_amount = io_nondestroy_amount - output_io_sum;
				
				this_event = games[0].events[this.bet_outputs[i].event_index];
				
				var effectiveness_factor = this_event.block_id_to_effectiveness_factor(games[0].last_block_id+1);
				var output_votes = Math.round(votes*this.bet_outputs[i].slider_val/slider_sum);
				var output_effective_votes = Math.round(effectiveness_factor*output_votes);
				var output_burn_amount = Math.round(burn_amount*this.bet_outputs[i].slider_val/slider_sum);
				var output_effective_burn_amount = Math.round(effectiveness_factor*output_burn_amount);
				
				this_event.sum_hypothetical_votes += output_votes;
				this_event.sum_hypothetical_effective_votes += output_effective_votes;
				this_event.sum_hypothetical_burn_amount += output_burn_amount;
				this_event.sum_hypothetical_effective_burn_amount += output_effective_burn_amount;
				
				this_event.options[this_event.option_id2option_index[this.bet_outputs[i].option_id]].hypothetical_votes += output_votes;
				this_event.options[this_event.option_id2option_index[this.bet_outputs[i].option_id]].hypothetical_effective_votes += output_effective_votes;
				this_event.options[this_event.option_id2option_index[this.bet_outputs[i].option_id]].hypothetical_burn_amount += output_burn_amount;
				this_event.options[this_event.option_id2option_index[this.bet_outputs[i].option_id]].hypothetical_effective_votes += output_effective_burn_amount;
				
				output_io_sum += output_io_amount;
			}
			
			var output_io_sum = 0;
			
			for (var i=0; i<this.bet_outputs.length; i++) {
				var output_io_amount = Math.round(io_nondestroy_amount*this.bet_outputs[i].slider_val/slider_sum);
				if (i == this.bet_outputs.length-1) output_io_amount = io_nondestroy_amount - output_io_sum;
				
				var this_event = games[0].events[this.bet_outputs[i].event_index];
				
				var effectiveness_factor = this_event.block_id_to_effectiveness_factor(games[0].last_block_id+1);
				var output_votes = Math.round(votes*this.bet_outputs[i].slider_val/slider_sum);
				var output_effective_votes = Math.round(effectiveness_factor*output_votes);
				var output_burn_amount = Math.round(burn_amount*this.bet_outputs[i].slider_val/slider_sum);
				var output_effective_burn_amount = Math.round(effectiveness_factor*output_burn_amount);
				
				var output_cost = output_votes*games[0].coins_per_vote + output_burn_amount;
				var output_effective_coins = output_effective_votes*games[0].coins_per_vote + output_effective_burn_amount;
				
				var this_option = this_event.options[this_event.option_id2option_index[this.bet_outputs[i].option_id]];
				
				var event_votes = this_event.sum_votes + this_event.sum_unconfirmed_votes + this_event.sum_hypothetical_votes;
				var event_payout = event_votes*games[0].coins_per_vote + this_event.sum_burn_amount + this_event.sum_unconfirmed_burn_amount + this_event.sum_hypothetical_burn_amount;
				
				var event_effective_votes = this_event.sum_effective_votes + this_event.sum_unconfirmed_effective_votes + this_event.sum_hypothetical_effective_votes;
				var event_effective_coins = event_effective_votes*games[0].coins_per_vote + this_event.sum_effective_burn_amount + this_event.sum_unconfirmed_effective_burn_amount + this_event.sum_hypothetical_burn_amount;
				
				var option_effective_votes = this_option.effective_votes + this_option.unconfirmed_effective_votes + this_option.hypothetical_effective_votes;
				var option_effective_coins = option_effective_votes*games[0].coins_per_vote + this_option.effective_burn_amount + this_option.unconfirmed_effective_burn_amount + this_option.hypothetical_burn_amount;
				
				var expected_payout = Math.floor(this_event.payout_rate*event_payout*(output_effective_coins/option_effective_coins));
				var payout_factor = expected_payout/output_cost;
				
				output_val_disp = this.format_coins(output_cost/Math.pow(10,games[0].decimal_places));
				output_val_disp += " &rarr; "+this.format_coins(expected_payout/Math.pow(10,games[0].decimal_places));
				output_val_disp += " "+games[0].coin_name_plural+" (x"+this.format_coins(payout_factor)+")";
				
				$('#output_amount_disp_'+i).html(output_val_disp);
				
				this.bet_outputs[i].amount = output_io_amount;
				
				output_io_sum += output_io_amount;
			}
		}
	}
	this.refresh_output_amounts = function() {
		this.refresh_mature_io_btns();
		this.finish_refresh_output_amounts();
	}
	this.select_add_output_changed = function() {
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
				games[0].add_option_to_vote(event_index, option_id);
				$('#select_add_output').val("");
			}
		}
	}
	this.add_all_options = function() {
		for (var i=0; i<games[0].events.length; i++) {
			for (var j=0; j<games[0].events[i].options.length; j++) {
				var this_option = games[0].events[i].options[j];
				var already_in = false;
				this.bet_outputs.forEach(function(bet_output) {
					if (bet_output.option_id == this_option.option_id) already_in = true;
				});
				if (!already_in) games[0].add_option_to_vote(i, this_option.option_id);
			}
		}
	}
	this.remove_all_outputs = function() {
		for (var i=0; i<this.bet_outputs.length; i++) {
			$('#compose_bet_output_'+i).remove();
		}
		this.bet_outputs.length = 0;
		
		this.refresh_compose_bets();
	}
	this.show_intro_message = function() {
		$('#intro_message').modal('show');
	}
	this.show_planned_votes = function() {
		$('#planned_votes').modal('show');
	}
	this.show_featured_strategies = function() {
		$('#featured_strategies').modal('show');
		$('#featured_strategies_inner').html("Loading...");
		
		$.ajax({
			url: "/ajax/featured_strategies.php",
			data: {
				game_id: games[0].game_id,
				synchronizer_token: this.synchronizer_token
			},
			success: function(featured_strategies_html) {
				$('#featured_strategies_inner').html(featured_strategies_html);
			}
		});
	}
	this.create_new_game = function() {
		$('#new_game_save_btn').html("Loading...");
		
		var genesis_type = $('#new_game_genesis_type').val();
		
		var new_game_params = {
			action: "new",
			name: $('#new_game_name').val(),
			module: $('#new_game_module').val(),
			blockchain_id: $('#new_game_blockchain_id').val(),
			genesis_type: genesis_type,
			synchronizer_token: this.synchronizer_token
		};
		
		if (genesis_type == "existing") {
			new_game_params.genesis_tx_hash = $('#new_game_genesis_tx_hash').val();
		}
		else {
			new_game_params.genesis_io_id = $('#new_game_genesis_io_id').val();
			new_game_params.escrow_amount = $('#new_game_genesis_escrow_amount').val();
		}
		
		$.ajax({
			url: "/ajax/manage_game.php",
			dataType: "json",
			data: new_game_params,
			success: function(manage_game_response) {
				$('#new_game_save_btn').html("Save &amp; Continue");
				if (manage_game_response.status_code == 1) window.location = '/manage/'+manage_game_response.message;
				else alert(manage_game_response.message);
			}
		});
	}
	this.new_game_genesis_type_changed = function() {
		var type = $('#new_game_genesis_type').val();
		
		$.ajax({
			url: "/ajax/select_accounts_by_blockchain.php",
			dataType: "json",
			data: {
				blockchain_id: $('#new_game_blockchain_id').val(),
				synchronizer_token: this.synchronizer_token
			},
			success: function(new_game_response) {
				$('#new_game_genesis_account_id').html(new_game_response.html)
			}
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
	this.new_game_genesis_account_changed = function() {
		$.ajax({
			url: "/ajax/select_io_by_account.php",
			dataType: "json",
			data: {
				account_id: $('#new_game_genesis_account_id').val(),
				synchronizer_token: this.synchronizer_token
			},
			success: function(select_io_response) {
				$('#new_game_genesis_io_id').html(select_io_response.html);
			}
		});
	}
	this.manage_game = function(game_id, action) {
		var fetch_link_text = $('#fetch_game_link_'+game_id).html();
		var switch_link_text = $('#switch_game_btn').html();
		
		if (action == "fetch") $('#fetch_game_link_'+game_id).html("Loading...");
		if (action == "switch") $('#switch_game_btn').html("Switching...");
		
		$.ajax({
			url: "/ajax/manage_game.php",
			dataType: "json",
			data: {
				game_id: game_id,
				action: action,
				synchronizer_token: this.synchronizer_token
			},
			context: this,
			success: function(manage_game_response) {
				if (action == "fetch") {
					this.editing_game_id = game_id;
					$('#fetch_game_link_'+game_id).html(fetch_link_text);
					
					$('#game_form_has_final_round').prop('disabled', true);
					if (manage_game_response.game_status == "editable") $('#game_form_has_final_round').prop('disabled', false);

					if (manage_game_response.my_game == true && manage_game_response.game_status == "editable") {
						$('#save_game_btn').show();
						$('#publish_game_btn').show();
					}
					else {
						$('#save_game_btn').hide();
						$('#publish_game_btn').hide();
					}
					
					if (manage_game_response.giveaway_status == "invite_free" || manage_game_response.giveaway_status == "invite_pay") {
						if (manage_game_response.my_game) {
							$('#game_invitations_game_btn').show();
						}
						else $('#game_invitations_game_btn').hide();
					}
					else if (manage_game_response.giveaway_status == "public_pay" || manage_game_response.giveaway_status == "public_free") $('#game_invitations_game_btn').show();
					else $('#game_invitations_game_btn').hide();
					
					$('#game_form').modal('show');
					$('#game_form_name_disp').html("Settings: "+manage_game_response.name_disp);
					
					this.game_form_vars.forEach(function(var_name) {
						if (["pos_reward", "pow_reward", "giveaway_amount", "genesis_amount"].indexOf(var_name) != -1) {
							manage_game_response[var_name] = parseInt(manage_game_response[var_name])/Math.pow(10,games[0].decimal_places);
						}
						else if (["exponential_inflation_minershare", "exponential_inflation_rate", "default_max_voting_fraction"].indexOf(var_name) != -1) {
							manage_game_response[var_name] = parseFloat(manage_game_response[var_name]);
						}
						else if (["per_user_buyin_cap", "game_buyin_cap"].indexOf(var_name) != -1) {
							if (manage_game_response[var_name].indexOf('.') != -1) {
								manage_game_response[var_name] = this.rtrim(manage_game_response[var_name], '0');
							}
							manage_game_response[var_name] = this.rtrim(manage_game_response[var_name], '.');
						}
						
						$('#game_form_'+var_name).val(manage_game_response[var_name]);
						
						if (manage_game_response.my_game && manage_game_response.game_status == "editable") $('#game_form_'+var_name).prop('disabled', false);
						else $('#game_form_'+var_name).prop('disabled', true);
					}, this);
					
					if (manage_game_response.my_game && manage_game_response.game_status == "editable") $('#game_form_has_final_round').prop('disabled', false);
					else $('#game_form_has_final_round').prop('disabled', true);
					
					$('#game_form_game_status').html(manage_game_response.game_status);

					if (manage_game_response.inflation == "exponential") {
						$('#game_form_inflation_exponential').show();
						$('#game_form_inflation_linear').hide();
					}
					else {
						$('#game_form_inflation_exponential').hide();
						$('#game_form_inflation_linear').show();
					}
					
					if (manage_game_response.buyin_policy == "game_cap") {
						$('#game_form_game_buyin_cap_disp').show();
					}
					else $('#game_form_game_buyin_cap_disp').hide();
					
					if (manage_game_response.final_round > 0) {
						$('#game_form_final_round_disp').show();
						$('#game_form_has_final_round').val(1);
					}
					else {
						$('#game_form_final_round_disp').hide();
						$('#game_form_has_final_round').val(0);
					}

					if (manage_game_response.giveaway_status == "invite_pay" || manage_game_response.giveaway_status == "public_pay") {
						$('#game_form_giveaway_status_pay').show();
					}
					else {
						$('#game_form_giveaway_status_pay').hide();
					}

					this.game_form_start_condition_changed();
					this.game_form_event_rule_changed();
				}
				else {
					if (action == "switch") $('#switch_game_btn').html(switch_link_text);
					
					if (manage_game_response.status_code == 1) {
						window.location = manage_game_response.redirect_url;
					}
					else alert(manage_game_response.message);
				}
			}
		});
	}
	this.game_form_final_round_changed = function() {
		if ($('#game_form_has_final_round').val() == 1) {
			$('#game_form_final_round_disp').slideDown('fast');
			$('#game_form_final_round').focus();
		}
		else {
			$('#game_form_final_round_disp').slideUp('fast');
			$('#game_form_final_round').val(0);
		}
	}
	this.game_form_inflation_changed = function() {
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
	this.game_form_giveaway_status_changed = function() {
		var giveaway_status = $('#game_form_giveaway_status').val();
		if (giveaway_status == "invite_pay" || giveaway_status == "public_pay") {
			$('#game_form_giveaway_status_pay').slideDown('fast');
		}
		else {
			$('#game_form_giveaway_status_pay').hide();
		}
	}
	this.game_form_start_condition_changed = function() {
		var start_condition = $('#game_form_start_condition').val();

		$('#game_form_start_condition_fixed_time').hide();
		$('#game_form_start_condition_players_joined').hide();

		$('#game_form_start_condition_'+start_condition).show();
	}
	this.game_form_buyin_policy_changed = function() {
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
	this.game_form_event_rule_changed = function() {
		var event_rule = $('#game_form_event_rule').val();
		if (event_rule == "entity_type_option_group") $('#game_form_event_rule_entity_type_option_group').show();
		else $('#game_form_event_rule_entity_type_option_group').hide();
	}
	this.save_game = function(action) {
		var save_link_text = $('#save_game_btn').html();
		
		var save_game_vars = {
			game_id: this.editing_game_id,
			action: action,
			synchronizer_token: this.synchronizer_token
		};
		
		this.game_form_vars.forEach(function(var_name) {
			save_game_vars[var_name] = $('#game_form_'+var_name).val();
		});
		
		$('#save_game_btn').html("Loading...");
		
		$.ajax({
			url: "/ajax/save_game.php",
			data: save_game_vars,
			dataType: "json",
			success: function(save_game_response) {
				$('#save_game_btn').html(save_link_text);
				
				if (save_game_response.status_code == 1) {
					if (save_game_response.redirect_user == 1) {
						window.location = '/wallet/'+save_game_response.url_identifier+'/';
					}
					else window.location = window.location;
				}
				else alert(save_game_response.message);
			}
		});
	}
	this.refresh_mature_io_btns = function() {
		this.chain_ios.forEach(function(chain_io) {
			var select_btn_text = "";
			var votes = chain_io.votes_at_block(games[0].last_block_id+1);
			
			if (games[0].inflation == "exponential") {
				var coin_equiv = Math.round(votes*games[0].coins_per_vote);
				var disp_coins = coin_equiv;
				disp_coins += chain_io.game_amount_sum();
				if (disp_coins < 0) disp_coins = 0;
				var coin_disp = this.format_coins(disp_coins/Math.pow(10,games[0].decimal_places));
				
				select_btn_text += "Stake "+coin_disp+" ";
				if (coin_disp == '1') select_btn_text += games[0].coin_name;
				else select_btn_text += games[0].coin_name_plural;
			}
			else {
				select_btn_text += 'Add '+this.format_coins(votes/Math.pow(10,games[0].decimal_places));
				select_btn_text += ' votes';
				if (games[0].payout_weight != 'coin') {
					var coin_disp = this.format_coins(chain_io.amount/Math.pow(10,games[0].decimal_places));
					select_btn_text += "<br/>("+coin_disp+" ";
					if (coin_disp == '1') select_btn_text += games[0].coin_name;
					else select_btn_text += games[0].coin_name_plural;
					select_btn_text += ")";
				}
			}
			document.getElementById('select_utxo_'+chain_io.io_id).innerHTML = select_btn_text;
		}, this);
		
		for (var i=0; i<this.bet_inputs.length; i++) {
			document.getElementById('selected_utxo_'+i).innerHTML = this.render_bet_input(i);
		}
	}
	this.compose_bets_loop = function() {
		if (this.output_amounts_need_update) this.refresh_output_amounts();
		this.output_amounts_need_update = false;
		setTimeout(function() {this.compose_bets_loop()}.bind(this), 400);
	}
	this.confirm_compose_bets = function() {
		if (this.bet_inputs.length > 0) {
			if (this.bet_outputs.length > 0) {
				var bet_btn_text = '<i class="fas fa-check-circle"></i> &nbsp; Confirm & Stake';
				
				this.transaction_in_progress = true;
				this.utxo_spend_offset++;
				$('#confirm_compose_bets_btn').html("Loading...");
				
				var place_bets_params = {
					game_id: games[0].game_id,
					burn_amount: this.burn_io_amount,
					synchronizer_token: this.synchronizer_token
				};
				
				place_bets_params.io_ids = _.map(_.map(this.bet_inputs, 'ref_io'), 'io_id').join(",");
				place_bets_params.option_ids = _.map(this.bet_outputs, 'option_id').join(",");
				place_bets_params.amounts = _.map(this.bet_outputs, 'amount').join(",");
				
				$.ajax({
					url: "/ajax/place_bets.php",
					data: place_bets_params,
					dataType: "json",
					context: this,
					success: function(place_bets_response) {
						$('#confirm_compose_bets_btn').html(bet_btn_text);
						
						var remove_utxos_delay_ms = 1500;
						var num_inputs = this.bet_inputs.length;
						
						if (place_bets_response.status_code == 1) {
							$('#compose_bets_success').html(place_bets_response.message);
							$('#compose_bets_success').slideDown('slow');
							$('#compose_burn_amount').val("");
							setTimeout(function() {
								$('#compose_bets_success').slideUp('fast');
							}, 10000);
							
							setTimeout(function() {
								this.remove_all_outputs();
								
								for (var i=0; i<num_inputs; i++) {
									this.remove_utxo_from_vote(0, false);
								}
							}.bind(this), remove_utxos_delay_ms);
						}
						else {
							$('#compose_bets_errors').html(place_bets_response.message);
							$('#compose_bets_errors').slideDown('slow');
							setTimeout(function() {$('#compose_bets_errors').slideUp('fast')}, 10000);
						}
						
						this.transaction_in_progress = false;
						
						setTimeout(function() {
							this.refresh_compose_bets();
						}.bind(this), remove_utxos_delay_ms + this.remove_utxo_ms*num_inputs + 100);
					},
					error: function(XMLHttpRequest, textStatus, errorThrown) {
						console.log(errorThrown);
						$('#confirm_compose_bets_btn').html(bet_btn_text);
						this.transaction_in_progress = false;
					}
				});
			}
			else {
				alert("First, please select something to bet on.");
			}
		}
		else {
			alert("First, please add coin inputs to your voting transaction.");
		}
	}
	this.reload_compose_bets = function() {
		for (var i=0; i<this.bet_inputs.length; i++) {
			$('#selected_utxo_'+i).remove();
		}
		this.bet_inputs.length = 0;
		
		$('#select_input_buttons').find('.select_utxo').each(function() {
			$(this).hide();
		});
		
		if (games[0].mature_io_ids_csv == "") {
			$('#select_input_buttons_msg').html("");
		}
		else {
			$('#select_input_buttons_msg').html("");
		}
		this.refresh_visible_inputs();
	}
	this.refresh_visible_inputs = function() {
		var show_count = 0;
		games[0].mature_io_ids_csv.split(",").forEach(function(mature_io_id, i) {
			if (typeof this.io_id2input_index[mature_io_id] == 'undefined' || this.io_id2input_index[mature_io_id] === false) {
				$('#select_utxo_'+mature_io_id).show();
				show_count++;
			}
			else {
				this.add_utxo_to_vote(i);
			}
		}, this);
	}
	this.show_more_event_outcomes = function(game_id) {
		var show_quantity = 50;
		if ($('#show_more_link').html() == "Show More") {
			var to_event_index = (this.last_event_index_shown-1);
			var from_event_index = to_event_index - show_quantity + 1;
			
			if (to_event_index < -1) to_event_index = -1;
			if (from_event_index < -1) from_event_index = -1;
			
			$('#show_more_link').html("Loading...");
			this.last_event_index_shown = from_event_index;
			
			$.ajax({
				url: "/ajax/show_event_outcomes.php",
				dataType: "json",
				data: {
					game_id: game_id,
					from_event_index: from_event_index,
					to_event_index: to_event_index,
					synchronizer_token: this.synchronizer_token
				},
				success: function(show_events_response) {
					$('#show_more_link').html("Show More");
					$('#render_event_outcomes').append('<div id="event_outcomes_'+this.event_outcome_sections_shown+'">'+show_events_response[1]+'</div>');
					this.event_outcome_sections_shown++;
				}
			});
		}
	}
	this.render_tx_fee = function() {
		$('#display_tx_fee').html("TX fee: "+this.format_coins(games[0].fee_amount)+" "+games[0].chain_coin_name_plural);
	}
	this.manage_game_invitations = function(this_game_id) {
		$.ajax({
			url: "/ajax/game_invitations.php",
			data: {
				action: "manage",
				game_id: this_game_id,
				synchronizer_token: this.synchronizer_token
			},
			success: function(game_invitations_html) {
				$('#game_invitations_inner').html(game_invitations_html);
				$('#game_invitations').modal('show');
			}
		});
	}
	this.generate_invitation = function(this_game_id) {
		$.ajax({
			url: "/ajax/game_invitations.php",
			context: this,
			data: {
				action: "generate",
				game_id: this_game_id,
				synchronizer_token: this.synchronizer_token
			},
			success: function(result) {
				this.manage_game_invitations(this_game_id)
			}
		});
	}
	this.send_invitation = function(this_game_id, invitation_id, send_method) {
		var send_to = "";
		if (send_method == 'email') {
			send_to = prompt("Please enter the email address where you'd like to send this invitation.");
		}
		else send_to = prompt("Please enter the username of the account where the invitation should be sent.");
		
		if (send_to) {
			$.ajax({
				url: "/ajax/game_invitations.php",
				dataType: "json",
				data: {
					action: "send",
					send_method: send_method,
					game_id: this_game_id,
					invitation_id: invitation_id,
					send_to: send_to,
					synchronizer_token: this.synchronizer_token
				},
				context: this,
				success: function(game_invitations_response) {
					if (game_invitations_response.status_code == 1) this.manage_game_invitations(this_game_id);
					else alert(game_invitations_response.message);
				}
			});
		}
	}
	this.set_plan_round_sums = function() {
		for (var i=0; i<this.plan_rounds.length; i++) {
			this.set_plan_round_sum(i);
		}
	}
	this.set_plan_round_sum = function(round_index) {
		var round_points = 0;
		for (var e=0; e<this.plan_rounds[round_index].event_ids.length; e++) {
			var event_id = this.plan_rounds[round_index].event_ids[e];
			var event_index = games[0].all_events_db_id_to_index[event_id];
			if (typeof games[0].all_events[event_index] !== "undefined") {
				var this_event = games[0].all_events[event_index];
				for (var o=0; o<this_event.options.length; o++) {
					round_points += this_event.options[o].points;
				}
			}
		}
		this.plan_rounds[round_index].sum_points = round_points;
	}
	this.render_plan_option = function(round_index, event_index, option_index, event_id, option_id) {
		var pct_points = 0;
		var round_id = this.plan_rounds[round_index].round_id;
		var row_sum = this.plan_rounds[round_index].sum_points;
		var this_option = games[0].all_events[event_index].options[option_index];
		if (row_sum > 0) pct_points = Math.round(100*this_option.points/row_sum);
		$('#plan_option_'+round_id+'_'+event_id+'_'+option_id).css("background-color", "rgba(0,0,255,"+(pct_points/100)+")");
		if (pct_points >= 50) $('#plan_option_'+round_id+'_'+event_id+'_'+option_id).css("color", "#fff");
		else $('#plan_option_'+round_id+'_'+event_id+'_'+option_id).css("color", "#000");
		$('#plan_option_amount_'+round_id+'_'+event_id+'_'+option_id).html(this_option.points+" ("+pct_points+"%)");
		$('#plan_option_input_'+round_id+'_'+event_id+'_'+option_id).val(this_option.points);
	}
	this.plan_option_clicked = function(round_id, event_id, option_id) {
		var event_index = games[0].all_events_db_id_to_index[event_id];
		var this_event = games[0].all_events[event_index];
		var option_index = this_event.option_id2option_index[option_id];
		var new_points = (this_event.options[option_index].points+plan_option_increment)%(plan_option_max_points+1);
		this_event.options[option_index].points = new_points;
		var round_index = this.round_id2plan_round_id[round_id];
		this.set_plan_round_sums();
		this.render_plan_round(round_index);
	}
	this.render_plan_round = function(round_index) {
		for (var i=0; i<this.plan_rounds[round_index].event_ids.length; i++) {
			var event_id = this.plan_rounds[round_index].event_ids[i];
			var event_index = games[0].all_events_db_id_to_index[event_id];
			if (typeof games[0].all_events[event_index] !== "undefined") {
				var this_event = games[0].all_events[event_index];
				for (var option_i=0; option_i<this_event.options.length; option_i++) {
					this.render_plan_option(round_index, event_index, option_i, this_event.event_id, this_event.options[option_i].option_id);
				}
			}
		}
	}
	this.set_plan_rightclicks = function() {
		$('.plan_option').contextmenu(function() {
			var id_parts = $(this).attr("id").split('_');
			var round_id = parseInt(id_parts[2]);
			var event_id = parseInt(id_parts[3]);
			var option_id = parseInt(id_parts[4]);
			
			var event_index = games[0].all_events_db_id_to_index[event_id];
			var option_index = games[0].all_events[event_index].option_id2option_index[option_id];
			var round_index = this.round_id2plan_round_id[round_id];
			
			games[0].all_events[event_index].options[option_index].points = 0;
			this.set_plan_round_sum(round_index);
			this.render_plan_round(round_index);
			
			return false;
		});
	}
	this.save_plan_allocations = function() {
		var postvars = {
			game_id: games[0].game_id,
			action: "save",
			voting_strategy_id: parseInt($('#voting_strategy_id').val()),
			from_round: parseInt($('#from_round').val()),
			to_round: parseInt($('#to_round').val()),
			synchronizer_token: this.synchronizer_token
		};
		
		if (games[0].all_events_start_index !== false && games[0].all_events_stop_index !== false) {
			for (var i=games[0].all_events_start_index; i<=games[0].all_events_stop_index; i++) {
				games[0].all_events[i].options.forEach(function(event_option) {
					if (event_option.points > 0) {
						postvars['poi_'+event_option.option_id] = event_option.points;
					}
				});
			}
		}
		
		$('#save_plan_btn').html("Saving...");
		$.ajax({
			type: "POST",
			url: "/ajax/planned_allocations.php",
			data: postvars,
			success: function() {
				$('#save_plan_btn').html("Save");
				$("input[name=voting_strategy][value='by_plan']").prop("checked",true);
			}
		});
	}
	this.load_plan_rounds = function() {
		this.save_plan_allocations();
		this.refresh_plan_allocations();
	}
	this.refresh_plan_allocations = function() {
		var from_round = parseInt($('#select_from_round').val());
		var to_round = parseInt($('#select_to_round').val());
		
		$.ajax({
			url: "/ajax/planned_allocations.php",
			dataType: "json",
			data: {
				game_id: games[0].game_id,
				action: "fetch",
				voting_strategy_id: $('#voting_strategy_id').val(),
				from_round: from_round,
				to_round: to_round,
				synchronizer_token: this.synchronizer_token
			},
			context: this,
			success: function(result) {
				$('#from_round').val(from_round);
				$('#to_round').val(to_round);
				$('#plan_rows').html(JSON.parse(result).html);
				this.set_plan_round_sums();
				this.render_plan_rounds();
			}
		});
	}
	this.render_plan_rounds = function() {
		for (var i=0; i<this.plan_rounds.length; i++) {
			this.render_plan_round(i);
		}
	}
	this.manage_buyin = function(action) {
		var buyin_params = {
			action: action,
			game_id: games[0].game_id,
			synchronizer_token: this.synchronizer_token
		};
		
		if (action == "check_amount") {
			buyin_params.buyin_amount = $('#buyin_amount').val();
			buyin_params.color_amount = $('#color_amount').val();
			
			if (this.invoice_id) {
				buyin_params.invoice_id = this.invoice_id;
			}
		}
		
		$('#buyin_modal_details').hide();
		
		$.ajax({
			url: "/ajax/buyin.php",
			data: buyin_params,
			dataType: "json",
			context: this,
			success: function(buyin_response) {
				if (action == "initiate") {
					$('#buyin_modal_content').html(buyin_response.content_html);
					$('#buyin_modal_invoices').html(buyin_response.invoices_html);
					$('#buyin_modal').modal('show');
					setTimeout(function() {$('#buyin_amount').focus()}, 1000);
				}
				else if (action == 'check_amount') {
					$('#buyin_modal_details').html(buyin_response.content_html);
					$('#buyin_modal_details').slideDown('fast');
					$('#buyin_modal_invoices').html(buyin_response.invoices_html);
					this.invoice_id = buyin_response.invoice_id;
				}
			}
		});
	}
	this.manage_sellout = function(action) {
		var sellout_params = {
			action: action,
			game_id: games[0].game_id,
			synchronizer_token: this.synchronizer_token
		};
		
		if (action == "confirm" || action == "check_amount") {
			sellout_params.sellout_amount = $('#sellout_amount').val();
		}
		if (action == "confirm") {
			sellout_params.address = $('#sellout_blockchain_address').val();
		}
		
		if (action != 'confirm') $('#sellout_modal_details').hide();
		
		$.ajax({
			url: "/ajax/sellout.php",
			data: sellout_params,
			dataType: "json",
			success: function(sellout_response) {
				if (action == "initiate") {
					$('#sellout_modal_content').html(sellout_response.content_html);
					$('#sellout_modal_invoices').html(sellout_response.invoices_html);
					$('#sellout_modal').modal('show');
				}
				else if (action == 'check_amount') {
					$('#sellout_modal_details').html(sellout_response.content_html);
					$('#sellout_modal_details').slideDown('fast');
				}
				else {
					$('#sellout_modal_invoices').html(sellout_response.invoices_html);
					alert(sellout_response.message);
				}
			}
		});
	}
	this.scramble_strategy = function(strategy_id) {
		var btn_default_text = $('#scramble_plan_btn').html();
		var btn_loading_text = "Randomizing...";
		
		if ($('#scramble_plan_btn').html() != btn_loading_text) {
			var user_confirmed = confirm('All of your votes in rounds '+$('#select_from_round').val()+' to '+$('#select_to_round').val()+' will be overwritten. Are you sure you want to randomize your votes?');
			
			if (user_confirmed) {
				$('#scramble_plan_btn').html(btn_loading_text);
				
				$.ajax({
					url: "/ajax/planned_allocations.php",
					dataType: "json",
					context: this,
					data: {
						game_id: games[0].game_id,
						voting_strategy_id: strategy_id,
						action: "scramble",
						from_round: $('#select_from_round').val(),
						to_round: $('#select_to_round').val(),
						synchronizer_token: this.synchronizer_token
					},
					success: function(scramble_response) {
						$('#scramble_plan_btn').html(btn_default_text);
						this.refresh_plan_allocations();
					}
				});
			}
		}
	}
	this.newsletter_signup = function() {
		$.ajax({
			url: "/ajax/newsletter.php",
			dataType: "json",
			data: {
				action: "signup",
				email: $('#newsletter_email').val()
			},
			success: function(newsletter_response) {
				alert(newsletter_response.message);
			}
		});
	}
	this.set_select_add_output = function() {
		var optionsAsString = "<option value=''>Please select...</option>";
		
		games[0].events.forEach(function(this_event) {
			this_event.options.forEach(function(this_option) {
				optionsAsString += "<option value='"+this_option.option_id+"'>"+this_option.name+" ("+this_event.event_name+")</option>";
			});
		});
		
		$("#select_add_output").find('option').remove().end().append($(optionsAsString));
		$("#principal_option_id").find('option').remove().end().append($(optionsAsString));
	}
	this.account_start_spend_io = function(game_id, io_id, amount, blockchain_coin_name, game_coin_name) {
		this.account_io_id = io_id;
		this.account_io_amount = amount;
		this.account_game_id = game_id;
		
		$('#account_spend_buyin_total').html("(Total: "+this.format_coins(amount)+" coins)");
		$('#account_spend_modal').modal('show');
		$('#account_io_id').val(io_id);
		$('#set_for_sale_io_id').val(io_id);
		$('#donate_game_id').val(game_id);
		$('#set_for_sale_game_id').val(game_id);
		
		var optionsAsString = "<option value='blockchain'>"+blockchain_coin_name+"</option>\n";
		optionsAsString += "<option value='game'>"+game_coin_name+"</option>\n";
		$("#spend_withdraw_coin_type").find('option').remove().end().append($(optionsAsString));
		
		$('#spend_withdraw_fee_label').html(blockchain_coin_name);
	}
	this.account_spend_action_changed = function() {
		var account_spend_action = $('#account_spend_action').val();
		
		if (this.selected_account_action !== false) $('#account_spend_'+this.selected_account_action).hide();
		
		$('#account_spend_'+account_spend_action).show('fast');
		this.selected_account_action = account_spend_action;
		
		if (account_spend_action == "join_tx") {
			$.ajax({
				url: "/ajax/account_spend.php",
				dataType: "json",
				data: {
					io_id: this.account_io_id,
					action: "start_join_tx",
					synchronizer_token: this.synchronizer_token
				},
				success: function(spend_response) {
					$('#account_spend_join_tx').html(spend_response.html);
				}
			});
		}
	}
	this.account_spend_buyin_address_choice_changed = function() {
		var address_choice = $('#account_spend_buyin_address_choice').val();
		if (address_choice == "new") {
			$('#account_spend_buyin_address_existing').hide('fast');
		}
		else {
			$('#account_spend_buyin_address_existing').show('fast');
			$('#account_spend_buyin_address').focus();
		}
	}
	this.account_spend_refresh = function() {
		var buyin_amount = parseFloat($('#account_spend_buyin_amount').val());
		if (buyin_amount > 0) {
			var fee_amount = parseFloat($('#account_spend_buyin_fee').val());
			var color_amount = this.account_io_amount - buyin_amount - fee_amount;
			$('#account_spend_buyin_color_amount').html("Color "+this.format_coins(color_amount)+" coins");
		}
		setTimeout(function() {this.account_spend_refresh()}.bind(this), 500);
	}
	this.account_spend_buyin = function() {
		if ($('#account_spend_action').val() == "buyin") {
			$.ajax({
				url: "/ajax/account_spend.php",
				dataType: "json",
				data: {
					action: "buyin",
					game_id: $('#account_spend_game_id').val(),
					io_id: this.account_io_id,
					buyin_amount: parseFloat($('#account_spend_buyin_amount').val()),
					fee_amount: parseFloat($('#account_spend_buyin_fee').val()),
					address: $('#account_spend_buyin_address_choice').val(),
					synchronizer_token: this.synchronizer_token
				},
				success: function(spend_response) {
					alert(spend_response.message);
					if (spend_response.status_code == 1) window.location = window.location;
				}
			});
		}
	}
	this.account_spend_withdraw = function() {
		$.ajax({
			url: "/ajax/account_spend.php",
			dataType: "json",
			data: {
				action: "withdraw",
				io_id: this.account_io_id,
				address: $('#spend_withdraw_address').val(),
				amount: $('#spend_withdraw_amount').val(),
				fee: $('#spend_withdraw_fee').val(),
				withdraw_type: $("#spend_withdraw_coin_type").val(),
				synchronizer_token: this.synchronizer_token
			},
			success: function(spend_response) {
				if (spend_response.status_code == 1) window.location = spend_response.message;
				else alert(spend_response.message);
			}
		});
	}
	this.account_spend_split = function() {
		$.ajax({
			url: "/ajax/account_spend.php",
			dataType: "json",
			data: {
				action: "split",
				game_id: this.account_game_id,
				io_id: this.account_io_id,
				amount_each: $('#split_amount_each').val(),
				quantity: $('#split_quantity').val(),
				fee: $('#split_fee').val(),
				synchronizer_token: this.synchronizer_token
			},
			success: function(spend_response) {
				if (spend_response.status_code == 1) window.location = spend_response.message;
				else alert(spend_response.message);
			}
		});
	}
	this.manage_addresses = function(account_id, action, address_id="") {
		$.ajax({
			url: "/ajax/manage_addresses.php",
			dataType: "json",
			data: {
				action: action,
				account_id: account_id,
				address_id: address_id,
				synchronizer_token: this.synchronizer_token
			},
			success: function(manage_addresses_response) {
				if (manage_addresses_response.status_code == 1) window.location = manage_addresses_response.message;
				else alert(manage_addresses_response.message);
			}
		});
	}
	this.set_event_outcome = function(game_id, event_id) {
		$.ajax({
			url: "/ajax/set_event_outcome.php",
			dataType: "json",
			context: this,
			data: {
				action: "fetch",
				event_id: event_id,
				synchronizer_token: this.synchronizer_token
			},
			success: function(set_event_response) {
				this.set_event_id = event_id;
				$('#set_event_outcome_modal').modal('show');
				$('#set_event_outcome_modal_content').html(set_event_response.html);
			}
		});
	}
	this.set_event_outcome_changed = function() {
		var outcome_index = $('#set_event_outcome_index').val();
		$('#set_event_outcome_index').attr('disabled', 'disabled');
		
		if (outcome_index != "select") {
			$.ajax({
				url: "/ajax/set_event_outcome.php",
				dataType: "json",
				data: {
					action: "set",
					event_id: this.set_event_id,
					outcome_index: outcome_index,
					synchronizer_token: this.synchronizer_token
				},
				success: function(set_event_response) {
					if (set_event_response.status_code == 2) window.location = window.location;
					else alert(set_event_response.message);
				}
			});
		}
	}
	this.rtrim = function(str, charlist) {
		charlist = !charlist ? ' \\s\u00A0' : (charlist + '')
			.replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:])/g, '\\$1');
		var re = new RegExp('[' + charlist + ']+$', 'g');
		return (str + '').replace(re, '');
	}
	this.leftpad = function(num, size, pad_char) {
		var s = num+"";
		while (s.length < size) s = pad_char + s;
		return s;
	}
	this.try_claim_address = function(blockchain_id, game_id, address_id) {
		$.ajax({
			url: "/ajax/try_claim_address.php",
			dataType: "json",
			data: {
				blockchain_id: blockchain_id,
				game_id: game_id,
				address_id: address_id,
				synchronizer_token: this.synchronizer_token
			},
			success: function(claim_response) {
				if (claim_response.status_code == 1) window.location = window.location;
				else if (claim_response.status_code == 2) window.location = '/wallet/?redirect_key='+claim_response.message;
				else alert(claim_response.message);
			}
		});
	}
	this.change_user_game = function() {
		window.location = '/wallet/'+games[0].game_url_identifier+'/?action=change_user_game&user_game_id='+$('#select_user_game').val();
	}
	this.explorer_change_user_game = function() {
		window.location = '/explorer/games/'+games[0].game_url_identifier+'/my_bets/?user_game_id='+$('#select_user_game').val();
	}
	this.finish_join_tx = function() {
		$.ajax({
			url: "/ajax/account_spend.php",
			dataType: "json",
			data: {
				action: "finish_join_tx",
				io_id: this.account_io_id,
				join_io_id: $('#join_tx_io_id').val(),
				synchronizer_token: this.synchronizer_token
			},
			success: function(spend_response) {
				if (spend_response.status_code == 1) window.location = spend_response.message;
				else alert(spend_response.message);
			}
		});
	}
	this.create_account_step = function(step) {
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
			$.ajax({
				url: "/ajax/create_account.php",
				dataType: "json",
				data: {
					action: create_account_action,
					blockchain_id: $('#create_account_blockchain_id').val(),
					account_name: $('#create_account_rpc_name').val(),
					synchronizer_token: this.synchronizer_token
				},
				success: function(create_account_response) {
					if (create_account_response.status_code == 1) window.location = create_account_response.message;
					else alert(create_account_response.message);
				}
			});
		}
	}
	this.clear_event_form = function() {
		this.event_verbatim_vars.forEach(function(var_name) {
			$('#event_form_'+var_name).val("");
		});
	}
	this.manage_game_set_event_blocks = function(game_defined_event_id = false) {
		var set_event_params = {
			game_id: games[0].game_id,
			synchronizer_token: this.synchronizer_token
		};
		if (game_defined_event_id) set_event_params.game_defined_event_id = game_defined_event_id;
		
		$.ajax({
			url: "/ajax/set_event_blocks.php",
			dataType: "json",
			data: set_event_params,
			success: function(set_blocks_response) {
				alert(set_blocks_response.message);
			}
		});
	}
	this.manage_game_load_event = function(gde_id) {
		this.clear_event_form();
		
		$.ajax({
			url: "/ajax/manage_game.php",
			dataType: "json",
			context: this,
			data: {
				action: "load_gde",
				game_id: games[0].game_id,
				gde_id: gde_id,
				synchronizer_token: this.synchronizer_token
			},
			success: function(this_event) {
				$('#event_modal').modal('show');
				
				for (var form_key in this_event.form_data) {
					$('#event_form_'+form_key).val(this_event.form_data[form_key]);
				}
				
				if (this_event.form_data['event_starting_time'] || !this_event.form_data['event_starting_time'] && !this_event.form_data['event_starting_time']) {
					$('#event_form_event_times').show();
					$('#event_form_event_blocks').hide();
				}
				else {
					$('#event_form_event_times').hide();
					$('#event_form_event_blocks').show();
				}
				
				$('#event_form_save_btn').click(function() {
					this.save_gde(gde_id);
				}.bind(this));
			}
		});
	}
	this.save_gde = function(gde_id) {
		var save_gde_params = {
			action: "save_gde",
			game_id: games[0].game_id,
			gde_id: gde_id,
			synchronizer_token: this.synchronizer_token
		};
		
		this.event_verbatim_vars.forEach(function(var_name) {
			save_gde_params[var_name] = $('#event_form_'+var_name).val()
		});
		
		$.ajax({
			url: "/ajax/manage_game.php",
			data: save_gde_params,
			dataType: "json",
			success: function(manage_game_response) {
				alert(manage_game_response.message);
			}
		});
	}
	this.manage_game_event_options = function(gde_id) {
		$.ajax({
			url: "/ajax/manage_game.php",
			dataType: "json",
			data: {
				action: "manage_gdos",
				game_id: games[0].game_id,
				gde_id: gde_id,
				synchronizer_token: this.synchronizer_token
			},
			success: function(manage_gdos_response) {
				$('#options_modal').modal('show');
				$('#options_modal_content').html(manage_gdos_response.html);
			}
		});
	}
	this.add_game_defined_option = function(gde_id) {
		$.ajax({
			url: "/ajax/manage_game.php",
			dataType: "json",
			context: this,
			data: {
				action: "add_new_gdo",
				game_id: games[0].game_id,
				gde_id: gde_id,
				name: $('#new_gdo_name').val(),
				entity_type_id: $('#new_gdo_entity_type_id').val(),
				synchronizer_token: this.synchronizer_token
			},
			success: function(response_info) {
				if (response_info.status_code == 1) this.manage_game_event_options(gde_id);
				else alert(response_info.message);
			}
		});
	}
	this.delete_game_defined_option = function(gde_id, gdo_id) {
		$.ajax({
			url: "/ajax/manage_game.php",
			dataType: "json",
			context: this,
			data: {
				action: "delete_gdo",
				game_id: games[0].game_id,
				gdo_id: gdo_id,
				synchronizer_token: this.synchronizer_token
			},
			success: function(response_info) {
				if (response_info.status_code == 1) this.manage_game_event_options(gde_id);
				else alert(response_info.message);
			}
		});
	}
	this.toggle_account_details = function(account_id) {
		$('#account_details_'+account_id).toggle('fast');
		this.selected_account_id = account_id;
	}
	this.withdraw_from_account = function(account_id, step) {
		if (step == 1) {
			this.selected_account_id = account_id;
			$('#withdraw_dialog').modal('show');
		}
		else if (step == 2) {
			if ($('#withdraw_btn').html() == "Withdraw") {
				$('#withdraw_btn').html("Loading...");
				
				$.ajax({
					url: "/ajax/account_spend.php",
					dataType: "json",
					data: {
						action: "withdraw_from_account",
						account_id: this.selected_account_id,
						amount: $('#withdraw_amount').val(),
						fee: $('#withdraw_fee').val(),
						address: $('#withdraw_address').val(),
						synchronizer_token: this.synchronizer_token
					},
					success: function(response_info) {
						$('#withdraw_btn').html("Withdraw");
						$('#withdraw_message').html(response_info.message);
						$('#withdraw_message').show("fast");
					}
				});
			}
		}
	}
	this.cards_howmany_changed = function() {
		var howmany = document.getElementById('cards_howmany').value;
		if (howmany == "other") {
			document.getElementById('cards_howmany_other').style.display = 'block';
			document.getElementById('cards_howmany_other_val').focus();
		}
		else {
			document.getElementById('cards_howmany_other').style.display = 'none';
		}
	}
	this.fv_currency_id_changed = function() {
		$.ajax({
			url: "/ajax/select_denominations_by_currencies.php",
			dataType: "json",
			data: {
				currency_id: $('#cards_currency_id').val(),
				fv_currency_id: $('#cards_fv_currency_id').val(),
				synchronizer_token: this.synchronizer_token
			},
			success: function(response_info) {
				this.cost_per_coin = response_info.cost_per_coin;
				this.coin_abbreviation = response_info.coin_abbreviation;
				$('#cards_denomination_id').html(response_info.denominations_html);
				$('#cards_account_id').html(response_info.accounts_html);
			}
		});
	}
	this.currency_id_changed = function() {
		$('#cards_denomination_id').html("");
		
		$.ajax({
			url: "/ajax/select_fv_currency_by_currency.php",
			data: {
				currency_id: $('#cards_currency_id').val(),
				synchronizer_token: this.synchronizer_token
			},
			success: function(fv_currencies_html) {
				$('#cards_fv_currency_id').html(fv_currencies_html);
			}
		});
	}
	this.show_card_preview = function() {
		$('#cards_preview').show();
		$('#cards_preview').html("Loading...");
		
		$.ajax({
			url: "/ajax/card_preview.php",
			data: {
				denomination_id: $('#cards_denomination_id').val(),
				purity: $('#cards_purity').val(),
				name: $('#cards_name').val(),
				title: $('#cards_title').val(),
				email: $('#cards_email').val(),
				pnum: $('#cards_pnum').val(),
				synchronizer_token: this.synchronizer_token
			},
			success: function(card_preview_html) {
				$('#cards_preview').html(card_preview_html);
			}
		});
	}
	this.search_card_id = function() {
		var peer_id = $('#peer_id').val();
		var card_id = $('#card_id_search').val();
		var url = "/redeem/"+peer_id+"/"+card_id;
		if ($('#redirect_key').val() != "") url += "/?redirect_key="+$('#redirect_key').val();
		window.location = url;
	}
	this.redeem_toggle = function() {
		if ($('#enter_redeem_code').is(":visible")) {
			$('#enter_redeem_code').hide();
		}
		else {
			$('#enter_redeem_code').show();
			$('#redeem_code').focus();
		}
	}
	this.check_the_code = function() {
		$('#redeem_card_confirm_btn').html("Checking...");
		$('#messages').hide();
		
		$.ajax({
			url: "/ajax/check_code.php",
			dataType: "json",
			data: {
				peer_id: peer_id,
				card_id: card_id,
				code: $('#redeem_code').val().replace(/-/g, '')
			},
			success: function(check_code_response) {
				$('#redeem_card_confirm_btn').html("Redeem");
				
				if (check_code_response.status_code == 1 || check_code_response.status_code == 4) {
					$('#step1').hide();
					$('#redeem_options').modal('show');
					$('#messages').hide();
				}
				else {
					$('#messages').html("Incorrect");
					$('#messages').css("color", "#f00");
					$('#messages').show();
				}
			}
		});
	}
	this.card_login = function(create_mode, login_card_id, peer_id) {
		$('#card_account_password').val(Sha256.hash($('#card_account_password').val()));
		if (create_mode) $('#card_account_password2').val(Sha256.hash($('#card_account_password2').val()));
		
		var card_password = $('#card_account_password').val();
		var card_password2;
		if (create_mode) card_password2 = $('#card_account_password2').val();
		
		if (!create_mode || card_password == card_password2) {
			$.ajax({
				url: "/ajax/check_code.php",
				dataType: "json",
				data: {
					action: "login",
					peer_id: peer_id,
					card_id: login_card_id,
					"password": card_password,
					code: $('#redeem_code').val().replace(/-/g, ''),
					redirect_key: $('#redirect_key').val()
				},
				success: function(check_code_response) {
					if (check_code_response.status_code == 1 || check_code_response.status_code == 2) {
						window.location = check_code_response.message;
						successful = true;
					}
					else {
						$('#card_account_password').val("");
						if (create_mode) $('#card_account_password2').val("");
						alert(check_code_response.message);
					}
				}
			});
		}
		else alert("Error, the passwords that you entered do not match.");
	}
	this.open_card = function(card_id) {
		if (card_id != this.selected_card) {
			if (this.selected_card != -1) {
				document.getElementById('card_block'+selected_card).style.display = "none";
				document.getElementById('card_btn'+selected_card).classlist.remove("card_small_sel");
			}
			this.selected_card = card_id;
			document.getElementById('card_btn'+card_id).classlist.add("card_small_sel");
			
			setTimeout(function() {
				document.getElementById('card_block'+card_id).style.display = "inline-block";
			}, 300);
		}
	}
	this.card_withdrawal = function(card_id) {
		$.ajax({
			url: "/ajax/withdraw.php",
			data: {
				action: "card_withdrawal",
				address: $('#withdraw_address').val(),
				card_id: card_id,
				name: $('#withdraw_name').val(),
				synchronizer_token: this.synchronizer_token
			},
			success: function(withdrawal_response) {
				if (withdrawal_response == "Beyonic request was successful!") {
					alert('Great, your money has been sent!');
					window.location = window.location;
				}
				else if (withdrawal_response == "2") {
					alert("There was an error withdrawing.  It looks like our hot wallet is out of money right now.");
				}
				else {
					alert("There was an error redeeming your card. The error code was: "+withdrawal_response);
				}
			}
		});
	}
	this.claim_card = function(claim_type) {
		var btn_id = "";
		var btn_original_text = "";
		if (claim_type == "to_address") btn_id = 'claim_address_btn';
		else if (claim_type == "to_game") btn_id = 'claim_game_btn_'+this.card_id+'_'+this.peer_id;
		else if (claim_type == "to_account") btn_id = 'claim_account_btn_'+this.card_id+'_'+this.peer_id;
		
		if ($('#'+btn_id).html() != "Loading...") {
			btn_original_text = $('#'+btn_id).html();
			
			$('#'+btn_id).html("Loading...");
			
			var claim_params = {
				action: "withdraw_from_card",
				claim_type: claim_type,
				card_id: this.card_id,
				peer_id: this.peer_id,
				synchronizer_token: this.synchronizer_token
			};
			
			if (claim_type == "to_address") {
				claim_params.fee = $('#claim_fee').val();
				claim_params.address = $('#claim_address').val();
			}
			
			$.ajax({
				url: "/ajax/account_spend.php",
				dataType: "json",
				data: claim_params,
				success: function(spend_response) {
					$('#'+btn_id).html(btn_original_text);
					
					if (claim_type == "address") {
						$('#'+btn_id).html(btn_original_text);
						$('#claim_message').html(spend_response.message);
						$('#claim_message').show("fast");
					}
					else {
						if (spend_response.status_code == 1) window.location = spend_response.message;
						else alert(spend_response.message);
					}
				}
			});
		}
	}
	this.toggle_betting_mode = function(to_betting_mode) {
		if (this.betting_mode !== false) {
			$('#betting_mode_'+this.betting_mode).hide();
		}
		$('#betting_mode_'+to_betting_mode).show();
		this.betting_mode = to_betting_mode;
		
		$.ajax({
			url: "/ajax/set_betting_mode.php",
			data: {
				game_id: games[0].game_id,
				mode: to_betting_mode,
				synchronizer_token: this.synchronizer_token
			}
		});
	}
	this.submit_principal_bet = function() {
		$('#principal_bet_btn').html("Loading...");
		
		$.ajax({
			url: "/ajax/principal_bet.php",
			dataType: "json",
			data: {
				game_id: games[0].game_id,
				amount: $('#principal_amount').val(),
				option_id: $('#principal_option_id').val(),
				fee: $('#principal_fee').val(),
				synchronizer_token: this.synchronizer_token
			},
			success: function(principal_bet_response) {
				$('#principal_bet_btn').html('<i class="fas fa-check-circle"></i> &nbsp; Confirm Bet');
				$('#principal_bet_message').html(principal_bet_response.message);
			}
		});
	}
	this.save_featured_strategy = function() {
		var featured_strategy_id = $("input[name='featured_strategy_id']:checked").val();
		
		if (featured_strategy_id) {
			$('#featured_strategy_save_btn').html("Saving...");
			
			$.ajax({
				url: "/ajax/set_featured_strategy.php",
				data: {
					game_id: games[0].game_id,
					featured_strategy_id: featured_strategy_id,
					synchronizer_token: this.synchronizer_token
				},
				success: function() {
					$('#featured_strategy_save_btn').html("Save");
					$("input[name=voting_strategy][value='featured']").prop("checked",true);
				}
			});
		}
	}
	this.apply_game_definition = function(game_id) {
		if ($('#apply_def_link').html() == "Apply Changes") {
			$('#apply_def_link').html("Applying...");
			
			$.ajax({
				url: "/ajax/apply_game_definition.php",
				dataType: "json",
				data: {
					game_id: game_id,
					synchronizer_token: this.synchronizer_token
				},
				success: function(apply_response) {
					$('#apply_def_link').html("Apply Changes");
					alert(apply_response.message);
				}
			});
		}
	}
	this.filter_changed = function(which_filter) {
		var filter_value = $('#filter_by_'+which_filter).val();
		
		if (which_filter == "date") {
			games[0].filter_date = filter_value;
		}
	}
	this.change_password = function() {
		$('#change_password_btn').html("Loading...");
		
		$('#change_password_existing').val(Sha256.hash($('#change_password_existing').val()));
		$('#change_password_new').val(Sha256.hash($('#change_password_new').val()));
		
		$.ajax({
			url: "/ajax/change_password.php",
			dataType: "json",
			data: {
				username: $('#change_password_username').val(),
				existing: $('#change_password_existing').val(),
				"new": $('#change_password_new').val(),
				synchronizer_token: this.synchronizer_token
			},
			success: function(change_password_response) {
				$('#change_password_btn').html("Change my Password");
				$('#change_password_username').val("");
				$('#change_password_existing').val("");
				$('#change_password_new').val("");
				
				alert(change_password_response.message);
			}
		});
	}
	this.generate_credentials = function() {
		$.ajax({
			url: "/ajax/check_username.php",
			dataType: "json",
			data: {
				action: "generate"
			},
			success: function(generation_response) {
				$('#generate_display').html(generation_response.message);
				$('#login_password').val($('#generate_password').val());
				$('#username').val($('#generate_username').val());
			}
		});
	}
	this.check_username = function() {
		var username = $('#username').val();
		$('#check_username_btn').html("Loading...");
		
		$.ajax({
			url: "/ajax/check_username.php",
			dataType: "json",
			data: {
				username: username
			},
			context: this,
			success: function(check_response) {
				$('#check_username_btn').html("Continue");
				
				$('#login_message').html(check_response.message);
				$('#login_message').show();
				
				if (check_response.status_code == 3 || check_response.status_code == 4) {
					this.toggle_to_panel('password');
					
					if (check_response.status_code == 4) {
						$('#login_btn').html("Sign Up");
					}
					else $('#login_btn').html("Log In");
				}
				else if (check_response.status_code == 1 || check_response.status_code == 2) {
					this.login();
				}
			}
		});
	}
	this.login = function() {
		if ($('#login_password').val() != "") $('#login_password').val(Sha256.hash($('#login_password').val()));
		
		$('#login_btn').html("Loading...");
		$('#generate_login_btn').html("Loading...");
		
		$.ajax({
			url: "/ajax/log_in.php",
			dataType: "json",
			data: {
				username: $('#username').val(),
				"password": $('#login_password').val(),
				redirect_key: $('#redirect_key').val()
			},
			success: function(login_response) {
				$('#login_btn').html("Log In");
				$('#generate_login_btn').html("Continue");
				
				if (login_response.status_code == 1) {
					window.location = login_response.message;
				}
				else {
					$('#login_password').val("");
					alert(login_response.message);
				}
			}
		});
	}
	this.toggle_to_panel = function(which_panel) {
		if (this.selected_panel) $('#'+this.selected_panel+'_panel').hide();
		
		if (which_panel == 'noemail') {
			which_panel = 'generate';
			
			$('#login_panel').hide();
		}
		
		this.selected_panel = which_panel;
		
		$('#'+this.selected_panel+'_panel').show('fast');
		
		if (which_panel == "login") setTimeout(function() {$('#username').focus()}, 500);
		else if (this.selected_panel == 'password') setTimeout(function() {$('#login_password').focus()}, 500);
	}
	this.manage_game_event_filter_changed = function() {
		window.location = '/manage/'+games[0].game_url_identifier+'/?next=events&event_filter='+$('#manage_game_event_filter').val();
	}
	this.apply_my_strategy = function() {
		$.ajax({
			url: "/strategies/apply_strategy.php",
			dataType: "json",
			data: {
				game_id: games[0].game_id,
				synchronizer_token: this.synchronizer_token
			},
			success: function(strategy_response) {
				$('#apply_my_strategy_status').html(strategy_response.message);
				$('#apply_my_strategy_status').slideDown('fast');
				setTimeout(function() {$('#apply_my_strategy_status').slideUp('fast');}, 10000);
			}
		});
	}
	this.new_group_member = function(group_id) {
		var member_name = prompt("Please enter a name:");
		window.location = '/groups/?action=add_member&group_id='+group_id+'&member_name='+encodeURIComponent(member_name);
	}
	this.toggle_push_menu = function(expand_collapse) {
		$.get("/ajax/toggle_left_menu.php?expand_collapse="+expand_collapse);
	}
	this.start_install_module = function(key_string) {
		window.location = '/install.php?key='+key_string+'&action=install_module&module_name='+$('#select_install_module').val();
	}
	this.refresh_prices_by_event = function(game_id, event_id) {
		$.ajax({
			url: "/ajax/refresh_prices_by_event.php",
			data: {
				game_id: game_id,
				event_id: event_id,
				synchronizer_token: this.synchronizer_token
			},
			success: function() {
				window.location = window.location;
			}
		});
	}
	this.toggle_definitive_game_peer = function() {
		if ($('#definitive_game_peer_on').val() == 1) {
			$('#definitive_game_peer').show('fast');
			$('#definitive_game_peer').focus();
		}
		else {
			$('#definitive_game_peer').hide('fast');
		}
	}
	this.request_pass_reset = function() {
		if (!this.reset_in_progress) {
			this.reset_in_progress = true;
			$('#reset_button').val("Sending...");
			
			$.ajax({
				url: "/ajax/reset_password.php",
				context: this,
				data: {
					email: $('#reset_email').val(),
					synchronizer_token: this.synchronizer_token
				},
				success: function(reset_response) {
					this.reset_in_progress = false;
					$('#reset_button').val("Request Password Reset");
					alert(reset_response);
				}
			});
		}
	}
	this.open_page_section = function(section_id) {
		if (section_id == this.selected_section) {
			document.getElementById('section_link_'+this.selected_section).classList.remove('active');
			document.getElementById('section_'+this.selected_section).style.display = 'none';
			this.selected_section = false;
		}
		else {
			if (this.selected_section !== false) {
				document.getElementById('section_'+this.selected_section).style.display = 'none';
				document.getElementById('section_link_'+this.selected_section).classList.remove('active');
			}
			document.getElementById('section_'+section_id).style.display = 'block';
			document.getElementById('section_link_'+section_id).classList.add('active');
			this.selected_section = section_id;
		}
	}
	this.explorer_block_list_show_more = function() {
		this.explorer_block_list_from_block = this.explorer_block_list_from_block - this.explorer_blocks_per_section;
		this.explorer_block_list_sections++;
		var section = this.explorer_block_list_sections;
		
		$('#explorer_block_list').append('<div id="explorer_block_list_'+section+'">Loading...</div>');
		
		var show_more_params = {
			blockchain_id: this.blockchain_id,
			from_block: this.explorer_block_list_from_block,
			blocks_per_section: this.explorer_blocks_per_section,
			filter_complete: this.filter_complete
		};
		if (games[0]) show_more_params.game_id = games[0].game_id;
		
		$.ajax({
			url: "/ajax/explorer_block_list.php",
			data: show_more_params,
			success: function(show_more_html) {
				$('#explorer_block_list_'+section).html(show_more_html);
			}
		});
	}
	this.donate_step = function(step) {
		if (step == "start") {
			$('#donate_modal').modal('show');
			$('#donate_modal_inner').html("Loading...");
			
			$.get("/ajax/donate.php?action=load", function(donate_response) {
				$('#donate_modal_inner').html(donate_response);
			});
		}
		else if (step == "save_email") {
			$('#donate_email_save_btn').html("Saving...");
			
			$.ajax({
				url: "/ajax/donate.php",
				data: {
					action: "save_email",
					email: $('#donate_email_address').val(),
					access_key: $('#donate_access_key').val()
				},
				dataType: "json",
				success: function(donate_response) {
					$('#donate_email_save_btn').html("Save email address");
					$('#donate_email_form').html(donate_response.message);
				}
			});
		}
	}
	this.spend_unresolved_step = function(account_id, game_io_id, step) {
		if (step == "whole_or_part") {
			var whole_or_part = $('#spend_unresolved_whole_or_part').val();
			
			if (whole_or_part == "") {
				$('#spend_unresolved_whole').hide();
				$('#spend_unresolved_part').hide();
			}
			else if (whole_or_part == "whole") {
				$('#spend_unresolved_part').hide();
				$('#spend_unresolved_whole').show('fast');
			}
			else {
				$('#spend_unresolved_whole').hide();
				$('#spend_unresolved_part').show('fast');
			}
		}
		else if (step == "spend_whole") {
			$.ajax({
				url: "/ajax/account_spend.php",
				dataType: "json",
				data: {
					action: "spend_unresolved",
					whole_or_part: "whole",
					address: $('#spend_unresolved_whole_address').val(),
					fee: $('#spend_unresolved_whole_fee').val(),
					account_id: account_id,
					game_io_id: game_io_id,
					synchronizer_token: this.synchronizer_token
				},
				success: function(spend_response) {
					if (spend_response.status_code == 1) window.location = spend_response.message;
					else {
						$('#spend_unresolved_whole_message').html('<font class="redtext">'+spend_response.message+'</font>');
						$('#spend_unresolved_whole_message').slideDown('fast');
						setTimeout(function() {
							$('#spend_unresolved_whole_message').hide()
						}, 12000);
					}
				}
			});
		}
	}
}

var thisPageManager = new PageManager();
