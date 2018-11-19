<?php
include("includes/connect.php");
include("includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if (!$thisuser) {
	$redirect_url = $app->get_redirect_url($_SERVER['REQUEST_URI']);
	$redirect_key = $redirect_url['redirect_key'];
	
	include("includes/html_start.php");
	?>
	<div class="container-fluid">
	<?php
	include("includes/html_login.php");
	?>
	</div>
	<?php
	include('includes/html_stop.php');
}
else {
	$nav_tab_selected = "install";
	$pagetitle = "Manage groups";
	include('includes/html_start.php');
	
	$selected_group = false;
	
	if (!empty($_REQUEST['group_id'])) {
		$group_q = "SELECT * FROM option_groups WHERE group_id='".((int)$_REQUEST['group_id'])."';";
		$group_r = $app->run_query($group_q);
		if ($group_r->rowCount()) {
			$selected_group = $group_r->fetch();
		}
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
					if ($_REQUEST['action'] == "add_member") {
						$member_name = urldecode($_REQUEST['member_name']);
						$general_entity_type = $app->check_set_entity_type("general entity");
						$member_entity = $app->check_set_entity($general_entity_type['entity_type_id'], $member_name);
						
						$member_q = "INSERT INTO option_group_memberships SET option_group_id='".$selected_group['group_id']."', entity_id='".$member_entity['entity_id']."';";
						$member_r = $app->run_query($member_q);
					}
					
					echo "<a href=\"/groups/\">&larr; All Groups</a><br/><br/>\n";
					
					$membership_q = "SELECT * FROM option_group_memberships m JOIN entities en ON m.entity_id=en.entity_id WHERE m.option_group_id='".$selected_group['group_id']."' ORDER BY m.membership_id ASC;";
					$membership_r = $app->run_query($membership_q);
					
					while ($membership = $membership_r->fetch()) {
						echo $membership['entity_name']."<br/>\n";
					}
					echo '<a href="" onclick="new_group_member('.$selected_group['group_id'].'); return false;">Add Another</a><br/>';
				}
				else {
					$import_groups_dir = realpath(dirname(__FILE__))."/lib/groups/";
					
					if ($_REQUEST['action'] == "import_from_file") {
						$import_group_description = $_REQUEST['group'];
						$error_message = "";
						$app->import_group_from_file($import_group_description, $error_message);
						
						if (!empty($error_message)) echo "<b>$error_message</b><br/>\n";
					}
					?>
					<b>Groups</b><br/>
					<?php
					$group_names = [];
					
					$group_q = "SELECT * FROM option_groups ORDER BY group_id ASC;";
					$group_r = $app->run_query($group_q);
					
					while ($db_group = $group_r->fetch()) {
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
										echo "<a href=\"/groups/?action=import_from_file&group=".urlencode($import_group_name)."\">Import</a> &nbsp;&nbsp; $import_file";
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
}
?>