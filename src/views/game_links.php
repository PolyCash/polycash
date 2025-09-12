<p>
	<?php
	if (in_array($explore_mode, array('blocks','addresses','transactions','utxo','unconfirmed'))) {
		echo "<a class='btn btn-sm btn-default' href='/explorer/blockchains/".$blockchain->db_blockchain['url_identifier'].($explore_mode == "unconfirmed" ? "/transactions" : '')."/".$explore_mode;
		if ($explore_mode == "blocks") {
			if (!empty($block)) echo "/".$block['block_id'];
		}
		else if ($explore_mode == "addresses") echo "/".$address['address'];
		else if ($explore_mode == "transactions") echo "/".$transaction['tx_hash'];
		else if ($explore_mode == "utxo") echo "/".$io['tx_hash']."/".$io['out_index'];
		else if ($explore_mode == "unconfirmed") echo "/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/transactions/unconfirmed/";
		else if ($explore_mode == "utxos") {
			if ($account) echo "/?account_id=".$account['account_id'];
		}
		echo "'><i class=\"fas fa-link\"></i> &nbsp; View on ".$game->blockchain->db_blockchain['blockchain_name']."</a>\n";
	}
	?>
	<a href="/wallet/<?php echo $game->db_game['url_identifier']; ?>/" class="btn btn-sm btn-success"><i class="fas fa-play-circle"></i> &nbsp; Play Now</a>
	
	<a class="btn btn-sm btn-primary" href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/my_bets/"><i class="fas fa-chart-line"></i> &nbsp; My Bets</a>
</p>

<?php
if (count($my_games) > 0) {
	?>
	<p>
		<select class="form-control input-sm" onchange="thisPageManager.change_game(this, '<?php echo $explore_mode; ?>');">
			<option value="">-- Switch Games --</option>
			<?php
			foreach ($my_games as $my_game) {
				echo "<option ";
				if ($game->db_game['game_id'] == $my_game['game_id']) echo 'selected="selected" ';
				echo "value=\"".$my_game['url_identifier']."\">".$my_game['name']."</option>\n";
			}
			?>
		</select>
	</p>
	<?php
}
