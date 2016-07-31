<?php
include("../includes/connect.php");

for ($i=0; $i<100; $i++) {
	echo "$i: ".$app->option_index_to_voting_chars($i, 100)."<br/>\n";
}
?>