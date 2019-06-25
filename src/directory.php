<?php
include_once("includes/connect.php");
include_once("includes/get_session.php");

if (empty($selected_category) && !empty($_REQUEST['path'])) {
	$uri_parts = explode("/", $_REQUEST['path']);
	$selected_category = $app->run_query("SELECT * FROM categories WHERE url_identifier=:category_identifier;", ['category_identifier'=>$uri_parts[1]])->fetch();
}

$selected_subcategory = false;

if (!empty($selected_category)) {
	if (!empty($uri_parts[2])) {
		$selected_subcategory = $app->run_query("SELECT * FROM categories WHERE parent_category_id=:category_id AND url_identifier=:subcategory_identifier;", [
			'category_id' => $selected_category['category_id'],
			'subcategory_identifier' => $uri_parts[2]
		])->fetch();
	}
}
$pagetitle = "Directory";
if (!empty($selected_category)) $pagetitle = $selected_category['category_name'];
if (!empty($selected_subcategory)) $pagetitle = $selected_subcategory['category_name'];

$nav_tab_selected = "directory";

include(AppSettings::srcPath().'/includes/html_start.php');
?>
<div class="container-fluid" style="padding-top: 10px;">
	<?php
	if (!empty($selected_category)) {
		$subcategories = $app->run_query("SELECT * FROM categories WHERE parent_category_id=:parent_category_id;", [
			'parent_category_id' => $selected_category['category_id']
		]);
		
		if ($subcategories->rowCount() > 0) {
			while ($subcategory = $subcategories->fetch()) {
				echo "<a href=\"/".$subcategory['url_identifier']."\">".$subcategory['category_name']."</a><br/>\n";
			}
			echo "<br/>\n";
		}
		
		$app->display_games($selected_category['category_id'], false);
	}
	else {
		echo "<h2>Please select a category:</h2>\n";
		
		$categories = $app->run_query("SELECT * FROM categories WHERE category_level=0 ORDER BY category_name ASC;");
		
		while ($category = $categories->fetch()) {
			echo "<a href=\"/".$category['url_identifier']."\">".$category['category_name']."</a><br/>\n";
		}
	}
	?>
</div>
<?php
include(AppSettings::srcPath().'/includes/html_stop.php');
?>