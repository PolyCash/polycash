<?php
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$address_id = (int) $_REQUEST['address_id'];
$currency_address = $app->run_query("SELECT * FROM currency_addresses WHERE currency_address_id='".$address_id."';")->fetch();
$app->reset_currency_address($currency_address);
?>