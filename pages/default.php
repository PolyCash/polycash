<?php
$pagetitle = $GLOBALS['coin_brand_name']." - Home";
$nav_tab_selected = "home";
include('includes/html_start.php');
?>
<div class="container" style="max-width: 1000px; padding-top: 10px;">
	<script type="text/javascript">
	var games = new Array();
	</script>
	<?php
	$app->display_featured_games();
	?>
</div>
<?php
include('includes/html_stop.php');
?>
