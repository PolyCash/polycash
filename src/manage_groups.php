<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if (!$thisuser) {
	$redirect_url = $app->get_redirect_url($_SERVER['REQUEST_URI']);
	$redirect_key = $redirect_url['redirect_key'];
	
	include(AppSettings::srcPath()."/includes/html_start.php");
	?>
	<div class="container-fluid">
	<?php
	include(AppSettings::srcPath()."/includes/html_login.php");
	?>
	</div>
	<?php
	include(AppSettings::srcPath().'/includes/html_stop.php');
}
else {
	$nav_tab_selected = "install";
	$pagetitle = "Manage groups";
	include(AppSettings::srcPath().'/includes/html_start.php');
	
	$selected_group = false;
	if (!empty($_REQUEST['group_id'])) {
		$selected_group = $app->fetch_group_by_id((int)$_REQUEST['group_id']);
	}
	?>
	<div class="container-fluid">
		<div class="panel panel-info" style="margin-top: 15px;">
			<div class="panel-heading">
				<div class="panel-title">Manage Group<?php if (!$selected_group) echo 's'; else echo ": ".$selected_group['description']; ?></div>
			</div>
			<div class="panel-body">
				<?php
				if ($selected_group) {
					if (!empty($_REQUEST['action']) && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
						if ($_REQUEST['action'] == "add_member") {
							$member_name = urldecode($_REQUEST['member_name']);
							$general_entity_type = $app->check_set_entity_type("general entity");
							$member_entity = $app->check_set_entity($general_entity_type['entity_type_id'], $member_name);
							
							$app->run_insert_query("option_group_memberships", [
								'option_group_id' => $selected_group['group_id'],
								'entity_id' => $member_entity['entity_id']
							]);
						}
						else if ($_REQUEST['action'] == "fetch_images_from_peer") {
							$api_url = "https://poly.cash/api/groups/?group=".urlencode($selected_group['description']);
							$api_response_raw = file_get_contents($api_url);
							
							if ($api_response = @json_decode($api_response_raw)) {
								if ($api_response->status_code == 1) {
									list($members, $formatted_members) = $app->group_details_json($selected_group);
									
									$member_i = 0;
									foreach ($api_response->members as $remote_member) {
										$recommended_entity_type = $app->check_set_entity_type($remote_member->entity_type);
										$recommended_entity = $app->check_set_entity($recommended_entity_type['entity_type_id'], $remote_member->entity_name);
										
										$db_image = $app->set_entity_image_from_url($remote_member->image_url, $recommended_entity['entity_id'], $error_message);
										$member_i++;
									}
									
									echo "Images were successfully imported.<br/>\n";
								}
								else echo "Peer returned an invalid status code.<br/>\n";
							}
							else echo "Failed to decode the response.<br/>\n";
						}
					}
					
					echo "<a href=\"/groups/\">&larr; All Groups</a><br/><br/>\n";
					
					$memberships = $app->run_query("SELECT * FROM option_group_memberships m JOIN entities en ON m.entity_id=en.entity_id WHERE m.option_group_id=:option_group_id ORDER BY m.membership_id ASC;", ['option_group_id' => $selected_group['group_id']]);
					
					while ($membership = $memberships->fetch()) {
						echo $membership['entity_name']."<br/>\n";
					}
					
					echo '<br/><a href="" onclick="thisPageManager.new_group_member('.$selected_group['group_id'].'); return false;">Add Another</a><br/>';
					
					echo '<br/><a href="" onclick="thisPageManager.fetch_group_images_from_peer('.$selected_group['group_id'].'); return false;">Fetch images from a peer</a><br/>';
				}
				else {
					$import_groups_dir = AppSettings::srcPath()."/lib/groups/";
					
					if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "import_from_file" && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
						$import_group_description = $_REQUEST['group'];
						$error_message = "";
						$app->import_group_from_file($import_group_description, $error_message);
						
						if (!empty($error_message)) echo "<b>$error_message</b><br/>\n";
					}
					?>
					<b>Groups</b><br/>
					<?php
					$group_names = [];
					
					$db_groups = $app->run_query("SELECT * FROM option_groups ORDER BY group_id ASC;");
					
					while ($db_group = $db_groups->fetch()) {
						echo '<div class="row"><div class="col-sm-6"><a href="/groups/?group_id='.$db_group['group_id'].'">Edit</a>&nbsp;&nbsp;&nbsp;'.$db_group['description']."</div></div>\n";
						array_push($group_names, $db_group['description']);
					}
					
					if (is_dir($import_groups_dir)) {
						if ($import_groups_dh = opendir($import_groups_dir)) {
							echo "<br/><b>Import groups from file:</b><br/>\n";
							
							while (($import_file = readdir($import_groups_dh)) !== false) {
								if (!in_array($import_file, [".", ".."])) {
									$import_group_name = explode(".csv", $import_file)[0];
									
									if (in_array($import_group_name, $group_names)) {
										echo "<b>Already imported</b> &nbsp;&nbsp; $import_file";
									}
									else {
										echo "<a href=\"/groups/?action=import_from_file&group=".urlencode($import_group_name)."&synchronizer_token=".$thisuser->get_synchronizer_token()."\">Import</a> &nbsp;&nbsp; $import_file";
									}
									
									echo "<br/>\n";
								}
							}
						}
					}
				}
				?>
			</div>
		</div>
	</div>
	<?php
	include(AppSettings::srcPath().'/includes/html_stop.php');
}
?>