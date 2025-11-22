function MonsterDuelsManager(game_id, game_slug, event_index, base_monsters, sec_per_frame) {
	this.game_id = game_id;
	this.game_slug = game_slug;
	this.event_index = event_index;
	this.render_div_id = 'monsterduels_'+game_id+'_'+event_index;
	this.base_monsters = base_monsters;
	this.rand_list_sec_per_seed = 5;
	this.sec_per_seed = this.rand_list_sec_per_seed;
	this.initial_sec_per_frame = sec_per_frame;
	this.sec_per_frame = this.initial_sec_per_frame;
	this.seeds = [];
	this.render_frame = 0;
	this.remaining_monster_option_indexes = [];
	this.attacking_monster_option_index = this.base_monsters[0].event_option_index;
	this.max_attack_damage = 47;
	this.winner = null;
	this.messages = [];
	this.rendering_frames = false;

	this.initialize = function() {
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

			var randInt = Math.abs(this.base64ToInteger(seed.seed));

			var defending_monster_option_indexes = this.remaining_monster_option_indexes.slice()
			defending_monster_option_indexes.splice(attacking_monster_pos_in_remaining, 1);

			var defending_monster_option_index = defending_monster_option_indexes[randInt%defending_monster_option_indexes.length];

			var defending_monster_base_pos = this.option_index_to_base_pos(defending_monster_option_index);
			
			var defending_monster = this.base_monsters[defending_monster_base_pos];

			var rendered_content = '<div>';
			
			var duel_number = seed.position - this.seeds[0].position + 1;
			rendered_content += '<h3><a target="_blank" href="/explorer/games/'+this.game_slug+'/events/'+this.event_index+'">Battle #'+(this.event_index+1)+'</a>, Duel #'+duel_number+'</h3>';
			
			rendered_content += this.render_remaining_monsters(attacking_monster, defending_monster);

			var attack_damage = randInt%(this.max_attack_damage);

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
			
			rendered_content += this.render_monster(attacking_monster, true);
			
			rendered_content += this.render_monster(defending_monster, true);

			rendered_content += '</div>';

			rendered_content += '<p><a href="" onClick="thisMonsterDuelsManager.initiate_replay(); return false;">Restart Battle</a> &nbsp; <a href="" onClick="thisMonsterDuelsManager.jump_to_latest(); return false;">Jump to Latest</a></p>';
			
			rendered_content += this.render_messages();
			
			$('#'+this.render_div_id).html(rendered_content);

			setTimeout(function() {
				$('#remaining_monster_hp_'+defending_monster.event_option_index).html(defending_monster.remaining_hp+"/"+defending_monster.hp);
			}, (this.sec_per_frame/2)*1000);

			if (defending_monster.eliminated) {
				setTimeout(function() {
					$('#remaining_monster_'+defending_monster.event_option_index).hide('slow');
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
			Body shape: "+monster.body_shape+"\
			</div>";
		return rendered_content;
	};

	this.render_remaining_monsters = function(attacking_monster, defending_monster) {
		rendered_content = "<div>";
		
		this.remaining_monster_option_indexes.forEach(function(remaining_monster_option_index, remaining_pos) {
			var base_pos = this.option_index_to_base_pos(remaining_monster_option_index);
			var monster = this.base_monsters[base_pos];
			rendered_content += "<div id='remaining_monster_"+remaining_monster_option_index+"' class='remaining_monster_box' title='"+monster.entity_name+"'><div class='remaining_monster_image' style='background-image: url(\""+monster.image_url+"\");";
			if (attacking_monster.event_option_index == remaining_monster_option_index) rendered_content += ' border: 1px solid #77f;';
			else if (defending_monster.event_option_index == remaining_monster_option_index) rendered_content += ' border: 1px solid #f77';
			rendered_content += "'></div><div id='remaining_monster_hp_"+remaining_monster_option_index+"'>"+monster.remaining_hp+"/"+monster.hp+"</div></div>";
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
			rendered_content += this.render_monster(this.winner, false);
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

	this.base64ToInteger = function(base64String) {
		// 1. Decode the Base64 string into a binary string
		const binaryString = atob(base64String);

		// 2. Convert the binary string to a Uint8Array (byte array)
		const bytes = new Uint8Array(binaryString.length);
		for (let i = 0; i < binaryString.length; i++) {
			bytes[i] = binaryString.charCodeAt(i);
		}

		// 3. Interpret the byte array as an integer using DataView
		const view = new DataView(bytes.buffer);
		
		// Check length to determine the appropriate method (e.g., 32-bit or 64-bit)
		if (bytes.length === 8) {
			// Use getBigUint64 for 64-bit numbers (returns a BigInt)
			// Default is Big Endian, pass `true` for Little Endian if needed
			return view.getBigUint64(0, false); 
		} else if (bytes.length === 4) {
			// Use getUint32 for 32-bit numbers
			return view.getUint32(0, false);
		} else {
			// Handle other lengths, e.g., smaller integers
			let value = 0;
			for (let i = 0; i < bytes.length; i++) {
				// Read byte by byte and shift
				value = (value << 8) | bytes[i];
			}
			// If it fits within standard JS number precision (53-bit)
			if (value <= Number.MAX_SAFE_INTEGER) {
				return value;
			} else {
				// Otherwise return a BigInt (might lose precision if > 53-bit)
				console.warn("Number exceeds standard JavaScript integer precision. Returning BigInt.");
				return BigInt(value);
			}
		}
	}
}
