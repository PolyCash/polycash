<?php
include("includes/connect.php");
include("includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$pagetitle = "Download ".$GLOBALS['coin_brand_name'];
$nav_tab_selected = "download";
include('includes/html_start.php');
?>
<div class="container-fluid">
	<div class="panel panel-default" style="margin-top: 15px;">
		<div class="panel-heading">
			<div class="panel-title">Download <?php echo $GLOBALS['coin_brand_name']; ?></div>
		</div>
		<div class="panel-body">
			<div class="row">
				<div class="col-md-6">
					<h2>PolyCash</h2>
					<p>
					PolyCash is a web app front-end for the PolyCash protocol.  To get started, please install PHP, MySQL and Apache on your computer.  If you're on Windows you can get PHP, MySQL and Apache by installing <a target="_blank" href="http://www.wampserver.com/en/">WAMP</a> or <a target="_blank" href="https://www.apachefriends.org/download.html">XAMPP</a>. Then, open your web directory and download our latest source code from Github.
					</p>
					<a target="_blank" class="btn btn-success" href="https://github.com/PolyCash/polycash">Download PolyCash</a>
				</div>
				<div class="col-md-6">
					<h2>EmpireCoin</h2>
					<p>
					EmpireCoin is an experimental blockchain which implements one game based on the CoinBlock protocol.  EmpireCoin is based on the original Bitcoin source code.  To get started, please visit our Github page, build the EmpireCoin daemon from source and then start mining on our testnet. To avoid compile errors, please use Ubuntu.
					</p>
					<a target="_blank" class="btn btn-success" href="https://github.com/TeamEmpireCoin/EmpireCoin">Download EmpireCoin</a>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
include('includes/html_stop.php');
?>