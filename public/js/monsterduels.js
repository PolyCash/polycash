function MonsterDuelsManager(game_id, game_slug, event_index, base_monsters, sec_per_frame=null, coins_per_vote=0, event_info=[]) {
	this.game_id = game_id;
	this.game_slug = game_slug;
	this.event_index = event_index;
	this.event_info = event_info;
	this.coins_per_vote = coins_per_vote;
	this.render_div_id = 'monsterduels_'+game_id+'_'+event_index;
	this.base_monsters = base_monsters;
	this.rand_list_sec_per_seed = 5;
	this.sec_per_seed = this.rand_list_sec_per_seed;
	this.default_sec_per_frame = 3;
	this.initial_sec_per_frame = sec_per_frame === null ? this.default_sec_per_frame : sec_per_frame;
	this.sec_per_frame = this.initial_sec_per_frame;
	this.seeds = [];
	this.render_frame = 0;
	this.remaining_monster_option_indexes = [];
	this.attacking_monster_option_index = this.base_monsters[0].event_option_index;
	this.max_attack_damage = 47;
	this.winner = null;
	this.messages = [];
	this.rendering_frames = false;
	this.px_per_pct_point = null;
	this.previous_defender_option_index = null;
	this.red_bar_color = '#e25';
	this.blue_bar_color = '#57f';

	this.event_effective_coins = this.event_info.sum_votes*this.coins_per_vote + this.event_info.effective_destroy_score + this.event_info.sum_unconfirmed_effective_destroy_score;

	this.base_monsters.forEach(function(base_monster, monster_pos) {
		var option_votes = base_monster.votes + base_monster.unconfirmed_votes;
		var option_effective_coins = option_votes*this.coins_per_vote + base_monster.effective_destroy_score + base_monster.unconfirmed_effective_destroy_score;
		base_monster.payout_odds = option_effective_coins > 0 ? this.event_info.payout_rate*this.event_effective_coins/option_effective_coins : null;
	}.bind(this));

	this.initialize = function() {
		console.log("Initializing event #"+this.event_index);

		this.base_monsters.forEach(function(monster, monster_pos) {
			this.base_monsters[monster_pos].remaining_hp = this.base_monsters[monster_pos].hp;
			this.base_monsters[monster_pos].eliminated = false;
			this.remaining_monster_option_indexes[monster_pos] = this.base_monsters[monster_pos].event_option_index;
		}.bind(this));

		this.loadSeeds();
	}.bind(this);
	
	this.loadSeeds = function() {
		$.ajax({
			url: "/ajax/monsterduels.php",
			dataType: "json",
			data: {
				game_id: this.game_id,
				event_index: this.event_index,
				action: 'load_seeds',
			},
			context: this,
			success: function(seeds_response) {
				if (seeds_response.seeds) {
					this.seeds = seeds_response.seeds;

					if (!this.rendering_frames) {
						this.renderFrame();
					}
				} else {
					$('#'+this.render_div_id).html(seeds_response.message);

					setTimeout(function() {
						this.loadSeeds();
					}.bind(this), 5000);
				}
			},
		});
	}.bind(this);

	this.renderFrame = function() {
		if (this.winner) {
			this.rendering_frames = false;
			return null;
		}

		if (this.seeds[this.render_frame]) {
			var seed = this.seeds[this.render_frame];

			var attacking_monster_base_pos = this.option_index_to_base_pos(this.attacking_monster_option_index);

			var attacking_monster = this.base_monsters[attacking_monster_base_pos];
			
			var attacking_monster_pos_in_remaining = this.option_index_to_remaining_pos(attacking_monster.event_option_index);

			var randInt = seed.seed;

			var defending_monster_option_indexes = this.remaining_monster_option_indexes.slice()
			defending_monster_option_indexes.splice(attacking_monster_pos_in_remaining, 1);

			var defending_monster_option_index = defending_monster_option_indexes[randInt%defending_monster_option_indexes.length];

			var defending_monster_base_pos = this.option_index_to_base_pos(defending_monster_option_index);
			
			var defending_monster = this.base_monsters[defending_monster_base_pos];

			var defending_monster_initial_hp = defending_monster.remaining_hp;

			var rendered_content = '<div>';
			
			var duel_number = seed.position - this.seeds[0].position + 1;
			rendered_content += '<h3><a target="_blank" href="/explorer/games/'+this.game_slug+'/events/'+this.event_index+'">Battle #'+(this.event_index+1)+'</a>, Duel #'+duel_number+'</h3>';
			
			rendered_content += this.render_remaining_monsters(attacking_monster, defending_monster);

			var attack_damage = randInt%(this.max_attack_damage);

			[weighted_win_points, weighted_win_points_by_remaining_pos] = this.get_expected_win_rates();
			var bar_chart_content = this.render_bar_chart(weighted_win_points, weighted_win_points_by_remaining_pos);

			if (this.previous_defender_option_index !== null) {
				var set_blue_option_index = this.previous_defender_option_index;
				setTimeout(function() {
					$('#monster_bar_bar_'+set_blue_option_index).css('background-color', this.blue_bar_color);
				}.bind(this), Math.round(1000*this.sec_per_frame/4));
			}

			this.previous_defender_option_index = defending_monster.event_option_index;

			defending_monster.remaining_hp = Math.max(0, defending_monster.remaining_hp - attack_damage);

			if (defending_monster.remaining_hp == 0) {
				defending_monster.eliminated = true;
				this.remaining_monster_option_indexes = this.remaining_monster_option_indexes.filter(option_index => option_index !== defending_monster_option_index);
			}

			var message = 'Duel #'+duel_number+': '+attacking_monster.entity_name+' used '+attacking_monster.best_attack_name+', did '+attack_damage+' damage to '+defending_monster.entity_name;
			this.messages.unshift(message);
			
			rendered_content += message+'<br/>';
			if (defending_monster.eliminated) {
				message = 'Duel #'+duel_number+': '+defending_monster.entity_name+' was eliminated';
				this.messages.unshift(message);
				rendered_content += message+'<br/>';
			}
			
			rendered_content += '<div style="display: block; overflow: hidden;">';
			rendered_content += this.render_monster(attacking_monster, true);
			rendered_content += this.render_monster(defending_monster, true);
			rendered_content += bar_chart_content;
			rendered_content += '</div>';
			
			rendered_content += '</div>';

			rendered_content += '<p><a href="" onClick="thisMonsterDuelsManager.initiate_replay(); return false;">Restart Battle</a> &nbsp; <a href="" onClick="thisMonsterDuelsManager.jump_to_latest(); return false;">Jump to Latest</a></p>';
			
			rendered_content += this.render_messages();
			
			$('#'+this.render_div_id).html(rendered_content);

			setTimeout(function() {
				$('#remaining_monster_hp_'+defending_monster.event_option_index).html("HP: "+defending_monster.remaining_hp+"/"+defending_monster.hp);
				$('#monster_bar_bar_'+defending_monster.event_option_index).css('background-color', this.red_bar_color);
				var defender_bar_initial_width = parseInt($('#monster_bar_bar_'+defending_monster.event_option_index).css('width').replace("px", ""));

				var new_bar_width = (Math.pow(10+defending_monster.remaining_hp, 2.72)/Math.pow(10+defending_monster_initial_hp, 2.72))*defender_bar_initial_width;
				
				this.animate_bar_width(defending_monster.event_option_index, defender_bar_initial_width, new_bar_width);
				
			}.bind(this), (this.sec_per_frame/2)*1000);

			if (defending_monster.eliminated) {
				setTimeout(function() {
					$('#remaining_monster_'+defending_monster.event_option_index).hide('slow');
					$('#monster_bar_'+defending_monster.event_option_index).hide('slow');
				}, (this.sec_per_frame/2)*1000);
			}

			if (this.remaining_monster_option_indexes.length < 2) {
				this.battle_over(duel_number);
				return null;
			}
			
			var next_remaining_pos = attacking_monster_pos_in_remaining;
			if (!defending_monster.eliminated || defending_monster.event_option_index > attacking_monster.event_option_index) {
				next_remaining_pos++;
			}
			this.attacking_monster_option_index = this.remaining_monster_option_indexes[next_remaining_pos%this.remaining_monster_option_indexes.length];
			
			this.render_frame++;
		} else {
			console.log('seed '+this.render_frame+' not loaded yet');
			
			if (this.sec_per_frame != this.rand_list_sec_per_seed) {
				console.log('switching to slower real time rate for frame rendering');
				this.sec_per_frame = this.rand_list_sec_per_seed;
			}
			
			this.loadSeeds();
		}

		this.rendering_frames = true;
		setTimeout(function() {
			this.renderFrame();
		}.bind(this), this.sec_per_frame*1000);
	};

	this.render_monster = function(monster, show_name) {
		var rendered_content = "<div class='dueling_monster_container'>";
		if (show_name) rendered_content +=	"<h4>"+monster.entity_name+"</h4>";
		rendered_content += "<div class='dueling_monster' style='background-image: url(\""+monster.image_url+"\");'></div>\
			HP: "+monster.remaining_hp+"/"+monster.hp+"<br/>\
			Color: "+monster.color+"<br/>\
			Body shape: "+monster.body_shape+"<br/>\
			Payout: "+(monster.payout_odds == null ? "No bets" : "x"+monster.payout_odds.toFixed(3))+"\
			</div>";
		return rendered_content;
	};

	this.get_expected_win_rates = function() {		
		var weighted_win_points = 0;
		var weighted_win_points_by_remaining_pos = [];
		this.remaining_monster_option_indexes.forEach(function(remaining_monster_option_index, remaining_pos) {
			var base_pos = this.option_index_to_base_pos(remaining_monster_option_index);
			var win_points = Math.pow(10+this.base_monsters[base_pos].remaining_hp, 2.72);
			weighted_win_points += win_points;
			weighted_win_points_by_remaining_pos[remaining_pos] = win_points;
		}.bind(this));
		return [weighted_win_points, weighted_win_points_by_remaining_pos];
	};

	this.animate_bar_width = function(event_option_index, defender_bar_initial_width, new_bar_width) {
		var total_less_px = defender_bar_initial_width - new_bar_width;
		var animate = false;

		if (animate) {
			var animate_ms_per_frame = 30;
			var num_frames = (1000*this.sec_per_frame/2)/animate_ms_per_frame;
			var reduce_px_per_frame = total_less_px/num_frames;

			for (var anim_frame=0; anim_frame < num_frames; anim_frame++) {
				var current_width = defender_bar_initial_width - (reduce_px_per_frame*(anim_frame+1));

				setTimeout(function(a_width) {
					$('#monster_bar_bar_'+event_option_index).css('width', a_width+'px');
				}, anim_frame*animate_ms_per_frame, current_width);
			}
		} else {
			$('#monster_bar_bar_'+event_option_index).css('width', new_bar_width+'px');
		}
	};

	this.render_bar_chart = function(weighted_win_points, weighted_win_points_by_remaining_pos) {
		var remaining_pos_weighted_points = Object.entries(weighted_win_points_by_remaining_pos);
		var max_weighted_points = Math.max(...remaining_pos_weighted_points.map(info => info[1]));
		var max_win_pct = max_weighted_points/weighted_win_points*100;
		var chart_width_px = 200;
		this.px_per_pct_point = chart_width_px/max_win_pct;

		remaining_pos_weighted_points.sort(([, a], [, b]) => b - a);

		var rendered_content = '<div class="md_bar_chart_section"><p><strong>Winning likelihood</strong></p><table style="line-height: 34px;">';

		remaining_pos_weighted_points.forEach(function(remaining_pos_weighted_point, position) {
			var win_pct = 100*remaining_pos_weighted_point[1]/weighted_win_points;

			var monster = this.base_monsters[this.remaining_monster_option_indexes[remaining_pos_weighted_point[0]]];

			rendered_content += "<tr id='monster_bar_"+monster.event_option_index+"'><td style='min-width: 35px;'>#"+(position+1)+"</td><td>"+monster.entity_name+"</td><td style='overflow: hidden;'><div id='monster_bar_bar_"+monster.event_option_index+"' class='md_bar_chart_bar' style='width: "+Math.round(this.px_per_pct_point*win_pct)+"px; background-color: "+(this.previous_defender_option_index == monster.event_option_index ? this.red_bar_color : this.blue_bar_color)+";'></div><div style='display: inline-block; float: left;'>"+win_pct.toFixed(2)+"%</div></td></tr>";
		}.bind(this));

		rendered_content += '</table></div>';

		return rendered_content;
	};

	this.render_remaining_monsters = function(attacking_monster, defending_monster) {
		var remaining_monsters = [];

		[weighted_win_points, weighted_win_points_by_remaining_pos] = this.get_expected_win_rates();
		
		rendered_content = "<div style='margin-bottom: 15px;'>";

		this.remaining_monster_option_indexes.forEach(function(remaining_monster_option_index, remaining_pos) {
			var base_pos = this.option_index_to_base_pos(remaining_monster_option_index);
			remaining_monster = this.base_monsters[base_pos];
			var win_pct = (100*weighted_win_points_by_remaining_pos[remaining_pos]/weighted_win_points).toFixed(2);
			rendered_content += "<div id='remaining_monster_"+remaining_monster.event_option_index+"' class='remaining_monster_box' title='"+remaining_monster.entity_name+"'>";
			rendered_content += "<div class='remaining_monster_image' style='background-image: url(\""+remaining_monster.image_url+"\");";
			if (attacking_monster.event_option_index == remaining_monster.event_option_index) rendered_content += ' border: 1px solid #77f;';
			else if (defending_monster.event_option_index == remaining_monster.event_option_index) rendered_content += ' border: 1px solid #f77';
			rendered_content += "'></div>";
			rendered_content += remaining_monster.entity_name+"<br/>";
			rendered_content += "<div id='remaining_monster_hp_"+remaining_monster.event_option_index+"'>HP: "+remaining_monster.remaining_hp+"/"+remaining_monster.hp+"</div>";
			rendered_content += (remaining_monster.payout_odds == null ? "No bets" : "Pays: x"+remaining_monster.payout_odds.toFixed(3));
			rendered_content += "</div>";
		}.bind(this));

		rendered_content += "</div>";

		return rendered_content;
	};

	this.render_messages = function() {
		var rendered_content = "<div class='duel_messages'>";
		
		this.messages.forEach(function(message) {
			rendered_content += message+"<br/>";
		}.bind(this));
		
		rendered_content += '</div>';
		
		return rendered_content;
	};

	this.option_index_to_base_pos = function(option_index) {
		var base_pos = null;
		this.base_monsters.forEach(function(base_monster, base_monster_pos) {
			if (base_monster.event_option_index == option_index) {
				base_pos = base_monster_pos;
			}
		}.bind(this));
		return base_pos;
	};

	this.option_index_to_remaining_pos = function(option_index) {
		var pos_in_remaining = null;
		this.remaining_monster_option_indexes.forEach(function(remaining_option_index, remaining_pos) {
			if (remaining_option_index == option_index) pos_in_remaining = remaining_pos;
		}.bind(this));
		return pos_in_remaining;
	};

	this.battle_over = function(duel_number) {
		this.winner = this.base_monsters[this.option_index_to_base_pos(this.remaining_monster_option_indexes[0])];
		
		setTimeout(function() {
			var message = this.winner.entity_name+' won battle #'+(this.event_index+1);
			this.messages.unshift("Duel #"+duel_number+": "+message);
			var rendered_content = '<h3>'+this.winner.entity_name+' won <a target="_blank" href="/explorer/games/'+this.game_slug+'/events/'+this.event_index+'">battle #'+(this.event_index+1)+'</a></h3>';
			
			rendered_content += '<div style="display: block; overflow: hidden;">';
			rendered_content += this.render_monster(this.winner, false);
			rendered_content += '</div>';

			rendered_content += '<p><a href="" onClick="thisMonsterDuelsManager.initiate_replay(); return false;">Replay Battle</a> &nbsp; <a href="" onClick="thisMonsterDuelsManager.jump_to_latest(); return false;">Jump to Latest</a></p>';
			rendered_content += this.render_messages();
			$('#'+this.render_div_id).html(rendered_content);
			this.rendering_frames = false;
		}.bind(this), this.sec_per_frame*1000);
	};

	this.initiate_replay = function() {
		$('#'+this.render_div_id).html('Restarting battle...');

		this.sec_per_seed = this.rand_list_sec_per_seed;
		this.sec_per_frame = 2.5;
		this.seeds = [];
		this.render_frame = 0;
		this.remaining_monster_option_indexes = [];
		this.attacking_monster_option_index = this.base_monsters[0].event_option_index;
		this.winner = null;
		this.messages = [];

		this.initialize();
	};

	this.jump_to_latest = function() {
		this.sec_per_frame = 0;
	};
}
