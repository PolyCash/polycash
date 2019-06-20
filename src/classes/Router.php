<?php
class Router {
	public static function Send404() {
		header("HTTP/1.0 404 Not Found");
		echo "404 - Page not found";
		die();
	}
}
?>