<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");
$pagetitle = "Manage Faucets";
$nav_tab_selected = "manage_faucets";

if (!$thisuser) {
	$redirect_url = $app->get_redirect_url($_SERVER['REQUEST_URI']);
	if ($redirect_url) $redirect_key = $redirect_url['redirect_key'];
	
	include(AppSettings::srcPath()."/includes/html_start.php");
	?>
	<div class="container-fluid">
	<?php
	include(AppSettings::srcPath()."/includes/html_register.php");
	?>
	</div>
	<?php
	include(AppSettings::srcPath().'/includes/html_stop.php');
	die();
}

$db_game = $app->fetch_game_from_url();

if (!$db_game) {
	include(AppSettings::srcPath()."/includes/html_start.php");
	?>
	<div class="container-fluid">
		You've reached an invalid URL. Please try again.
	</div>
	<?php
	include(AppSettings::srcPath().'/includes/html_stop.php');
	die();
}

$blockchain = new Blockchain($app, $db_game['blockchain_id']);
$game = new Game($blockchain, $db_game['game_id']);

$uri_parts = explode("/", $_SERVER['REQUEST_URI']);

if (isset($uri_parts[3]) && $uri_parts[3] == "new") {
	$faucet = null;
	include(AppSettings::srcPath()."/manage_faucet_single.php");
	die();
}

if (isset($uri_parts[3]) && ctype_digit($uri_parts[3])) {
	$faucet = Faucet::fetchById($app, $uri_parts[3]);
	if (! $faucet || $faucet['user_id'] != $thisuser->db_user['user_id']) {
		echo "Sorry, you don't have access to that faucet.";
	} else {
		include(AppSettings::srcPath()."/manage_faucet_single.php");
	}
	die();
}

$my_faucets = Faucet::fetchFaucetsManagedByUser($app, $thisuser, $game->db_game['game_id']);

include(AppSettings::srcPath()."/includes/html_start.php");
?>
<div class="container-fluid" style="padding-top: 15px;">
	<?php
	echo $app->render_view('game_links', [
		'explore_mode' => 'manage_faucets',
		'game' => $game,
		'blockchain' => $game->blockchain,
		'block' => null,
		'io' => null,
		'transaction' => null,
		'address' => null,
		'account' => null,
		'my_games' => $app->my_games($thisuser->db_user['user_id'], true)->fetchAll(PDO::FETCH_ASSOC),
	]);
	?>
	<div class="panel panel-default" style="margin-top: 15px;">
		<div class="panel-heading">
			<div class="panel-title">Manage Faucet Donations: <?php echo $game->db_game['name']; ?></div>
		</div>
		<div class="panel-body">
			<?php
			if (count($my_faucets) > 0) {
				?>
				<p>Please select a faucet to manage</p>
				<ul>
					<?php
					foreach ($my_faucets as $my_faucet) {
						echo '<li><a href="/manage_faucets/'.$game->db_game['url_identifier'].'/'.$my_faucet['faucet_id'].'">Faucet #'.$my_faucet['faucet_id'].': '.$my_faucet['account_name'].'</a></li>';
					}
					?>
				</ul>
				<?php
			}
			?>
			<a href="/manage_faucets/<?php echo $game->db_game['url_identifier']; ?>/new" class="btn btn-sm btn-success">+ New Faucet</a>
		</div>
	</div>
</div>
<?php
include(AppSettings::srcPath().'/includes/html_stop.php');
