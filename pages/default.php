<?php
$pagetitle = $GLOBALS['coin_brand_name']." - Home";
$nav_tab_selected = "home";
include('includes/html_start.php');
?>
<script type="text/javascript">
var games = new Array();
</script>
<?php
$app->display_games(false);
?>
<a href="/import/">Add another game</a>
<?php
include('includes/html_stop.php');
?>
