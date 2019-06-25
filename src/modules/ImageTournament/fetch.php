<?php
include(AppSettings::srcPath()."/includes/connect.php");
include(AppSettings::srcPath()."/includes/get_session.php");

$allowed_params = ['action'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$action = "fetch";
	if (!empty($_REQUEST['action'])) $action = $_REQUEST['action'];

	$pages_to_fetch = 23;
	$images_to_fetch = 512;
	$base_url = "https://www.reddit.com/r/sexygirls/";

	$entity_type = $app->check_set_entity_type("images");
	$option_group = $app->check_set_option_group("Reddit /r/sexygirls pics", "picture", "pictures");

	$num_memberships = (int)($app->run_query("SELECT COUNT(*) FROM option_group_memberships WHERE option_group_id=:option_group_id;", [
		'option_group_id' => $option_group['group_id']
	])->fetch()['COUNT(*)']);

	if ($num_memberships > 0) echo "This group has already been generated. Skipping...\n";
	else {
		$current_page_url = $app->async_fetch_url($base_url, true);
		$page_i = 0;
		$good_images = 0;

		do {
			$html = $current_page_url['cached_result'];
			$nextpage_link = $app->first_snippet_between($html, '<span class="next-button"><a href="', '"');
			$current_page_url = $app->async_fetch_url($nextpage_link, true);
			
			$html_parts = explode('click_thing(this)', $html);
			echo $page_i." (".count($html_parts).")<br/>\n";
			
			for ($i=1; $i<count($html_parts); $i++) {
				if ($good_images < $images_to_fetch) {
					$pic_url = $app->first_snippet_between($html_parts[$i], 'data-url="', '"');
					$reddit_url = $app->first_snippet_between($html_parts[$i], 'data-permalink="', '"');
					$title_html = $app->first_snippet_between($html_parts[$i], '<p class="title">', '</p>');
					$title = ($good_images+1).". ".$app->first_snippet_between($title_html, 'rel="" >', '</a>');
					
					$pic_url_parts = explode(".", $pic_url);
					$pic_extension = $pic_url_parts[count($pic_url_parts)-1];
					
					if (in_array($pic_extension, array('jpg','png'))) {
						echo $reddit_url."<br/>\n";
						echo $title."<br/><br/>\n\n";
						$entity = $app->check_set_entity($entity_type['entity_type_id'], $title);
						
						$app->run_query("UPDATE entities SET image_url=:image_url, content_url=:content_url WHERE entity_id=:entity_id;", [
							'image_url' => $pic_url,
							'content_url' => "https://reddit.com".$reddit_url,
							'entity_id' => $entity['entity_id']
						]);
						
						$app->run_query("INSERT INTO option_group_memberships SET option_group_id=:option_group_id, entity_id=:entity_id;", [
							'option_group_id' => $option_group['group_id'],
							'entity_id' => $entity['entity_id']
						]);
						
						$good_images++;
					}
				}
			}
			$page_i++;
		}
		while ($page_i < $pages_to_fetch);

		echo "Fetched ".$good_images." images.\n";
	}
}
else echo "You need admin privileges to run this script.\n";
?>