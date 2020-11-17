<?php
$pagetitle = AppSettings::getParam('site_name')." - Home";
$nav_tab_selected = "home";
include(AppSettings::srcPath().'/includes/html_start.php');
?>
<div class="container-fluid" style="padding-top: 10px;">
	<?php
	$app->display_games(false, false, $thisuser);
	?>
	<p><a href="/import/">Add another game</a></p>
	
	<p><a href="/redeem/">Redeem a card</a></p>
	
	<br/>
</div>
<?php
include(AppSettings::srcPath().'/includes/html_stop.php');
?>
