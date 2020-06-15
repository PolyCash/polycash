<?php
if (!empty($blockchain) || !empty($game)) {
	?>
	<script type="text/javascript">
	thisPageManager.blockchain_id = <?php
	if ($blockchain) echo $blockchain->db_blockchain['blockchain_id'];
	else echo $game->blockchain->db_blockchain['blockchain_id'];
	?>;
	</script>
	<div class="row">
		<div class="col-sm-7 ">
			<ul class="list-inline explorer_nav" id="explorer_nav">
				<?php if ($game) { ?>
				<li><a <?php if ($explore_mode == 'wallet') echo 'class="selected" '; ?>href="/wallet/<?php echo $game->db_game['url_identifier']; ?>/">Wallet</a></li>
				<li><a <?php if ($explore_mode == 'my_bets') echo 'class="selected" '; ?>href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/my_bets/">My Bets</a></li>
				<?php if (empty(AppSettings::getParam('limited_navigation'))) { ?>
				<li><a <?php if ($explore_mode == 'about') echo 'class="selected" '; ?>href="/<?php echo $game->db_game['url_identifier']; ?>/">About</a></li>
				<?php } ?>
				<?php } ?>
				<li><a <?php if ($explore_mode == 'blocks') echo 'class="selected" '; ?>href="/explorer/<?php echo $explorer_type; ?>/<?php
				if ($game) echo $game->db_game['url_identifier'];
				else echo $blockchain->db_blockchain['url_identifier'];
				?>/blocks/">Blocks</a></li>
				<?php if ($game) { ?>
				<li><a <?php if ($explore_mode == 'events') echo 'class="selected" '; ?>href="/explorer/<?php echo $explorer_type; ?>/<?php
				if ($game) echo $game->db_game['url_identifier'];
				else echo $blockchain->db_blockchain['url_identifier'];
				?>/events/">Events</a></li>
				<?php } ?>
				<?php if ($game) { ?>
				<li><a <?php if ($explore_mode == 'utxos') echo 'class="selected" '; ?>href="/explorer/<?php echo $explorer_type; ?>/<?php
				echo $game->db_game['url_identifier'];
				?>/utxos/">UTXOs</a></li>
				<?php } ?>
				<li><a <?php if ($explore_mode == 'unconfirmed') echo 'class="selected" '; ?>href="/explorer/<?php echo $explorer_type; ?>/<?php
				if ($game) echo $game->db_game['url_identifier'];
				else echo $blockchain->db_blockchain['url_identifier'];
				?>/transactions/unconfirmed/">Unconfirmed TXNs</a></li>
				<?php if ($game && $game->db_game['escrow_address'] != "") { ?>
				<li><a <?php if (($explore_mode == 'addresses' && $address['address'] == $game->db_game['escrow_address']) || ($explore_mode == "transactions" && $transaction['tx_hash'] == $game->db_game['genesis_tx_hash'])) echo 'class="selected" '; ?>href="/explorer/<?php echo $explorer_type; ?>/<?php echo $game->db_game['url_identifier']; ?>/transactions/<?php echo $game->db_game['genesis_tx_hash']; ?>">Genesis</a></li>
				<?php } ?>
				<?php if ($game) { ?>
				<li><a <?php if ($explore_mode == 'definition') echo 'class="selected" '; ?>href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/definition/">Game Definition</a>
				<?php } else { ?>
				<li><a <?php if ($explore_mode == 'definition') echo 'class="selected" '; ?>href="/explorer/blockchains/<?php echo $blockchain->db_blockchain['url_identifier']; ?>/definition/">Definition</a>
				<?php } ?>
			</ul>
		</div>
		<?php if ($top_nav_show_search) { ?>
		<form onsubmit="thisPageManager.explorer_search(); return false;">
			<div class="col-sm-4 row-no-padding">
				<input type="text" class="form-control" placeholder="Search addresses & transactions" id="explorer_search"<?php if (!empty($search_term)) echo ' value="'.str_replace('"', '', $search_term).'"'; ?>/>
			</div>
			<div class="col-sm-1 row-no-padding">
				<button type="submit" class="btn btn-primary">Go</button>
			</div>
		</form>
		<?php } ?>
	</div>
	<?php
}
?>