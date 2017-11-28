<?php
$pagetitle = $GLOBALS['coin_brand_name']." - Home";
$nav_tab_selected = "home";
include('includes/html_start.php');
?>
<script type="text/javascript">
var games = new Array();
</script>
<div class="container-fluid" style="padding-top: 10px;">
	<?php
	$app->display_games(false, false);
	?>
	<a href="/import/">Add another game</a>
</div>
<?php
include('includes/html_stop.php');
?>
