<?php
include_once("includes/connect.php");
include_once("includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if (empty($selected_category) && !empty($_REQUEST['path'])) {
	$uri_parts = explode("/", $_REQUEST['path']);
	$q = "SELECT * FROM categories WHERE url_identifier=".$app->quote_escape($uri_parts[1]).";";
	$r = $app->run_query($q);
	
	if ($r->rowCount() == 1) {
		$selected_category = $r->fetch();
	}
}

$selected_subcategory = false;

if (!empty($selected_category)) {
	if (!empty($uri_parts[2])) {
		$q = "SELECT * FROM categories WHERE parent_category_id='".$selected_category['category_id']."' AND url_identifier=".$app->quote_escape($uri_parts[2]).";";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$selected_subcategory = $r->fetch();
		}
	}
}
$pagetitle = "Directory";
if (!empty($selected_category)) $pagetitle = $selected_category['category_name'];
if (!empty($selected_subcategory)) $pagetitle = $selected_subcategory['category_name'];

$nav_tab_selected = "directory";

include('includes/html_start.php');
?>
<div class="container-fluid" style="padding-top: 10px;">
	<script type="text/javascript">
	var games = new Array();
	</script>
	<?php
	if (!empty($selected_category)) {
		$subcategory_q = "SELECT * FROM categories WHERE parent_category_id='".$selected_category['category_id']."';";
		$subcategory_r = $app->run_query($subcategory_q);
		
		if ($subcategory_r->rowCount() > 0) {
			while ($subcategory = $subcategory_r->fetch()) {
				echo "<a href=\"/".$subcategory['url_identifier']."\">".$subcategory['category_name']."</a><br/>\n";
			}
			echo "<br/>\n";
		}
		
		$app->display_games($selected_category['category_id'], false);
	}
	else {
		echo "<h2>Please select a category:</h2>\n";
		
		$q = "SELECT * FROM categories WHERE category_level=0 ORDER BY category_name ASC;";
		$r = $app->run_query($q);
		
		while ($category = $r->fetch()) {
			echo "<a href=\"/".$category['url_identifier']."\">".$category['category_name']."</a><br/>\n";
		}
	}
	?>
</div>
<?php
include('includes/html_stop.php');
?>