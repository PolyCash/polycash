<?php
function new_db_conn(&$skip_select_db) {
	$conn = new PDO("mysql:host=".$GLOBALS['mysql_server'].";charset=utf8", $GLOBALS['mysql_user'], $GLOBALS['mysql_password']) or die("Error, failed to connect to the database.");
	if (empty($skip_select_db)) {
		$conn->query("USE ".$GLOBALS['mysql_database']) or die("Error accessing the '".$GLOBALS['mysql_database']."' database, please visit <a href=\"install.php?key=\">install.php</a>.");
	}
	return $conn;
}
?>
