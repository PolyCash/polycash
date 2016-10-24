<?php
include("includes/connect.php");
include("includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$pagetitle = "Download ".$GLOBALS['coin_brand_name'];
$nav_tab_selected = "download";
include('includes/html_start.php');
?>
<div class="container" style="max-width: 1000px; padding-top: 10px;">
	<h1>Download <?php echo $GLOBALS['coin_brand_name']; ?></h1>
	<div class="row">
		<div class="col-md-6">
			<h2>CoinBlock</h2>
			<p>
			CoinBlock is a web app front-end for the CoinBlock protocol.  To get started, please install PHP, MySQL and Apache on your computer.  If you're on Windows you can get PHP, MySQL and Apache by installing <a target="_blank" href="http://www.wampserver.com/en/">WAMP</a> or <a target="_blank" href="https://www.apachefriends.org/download.html">XAMPP</a>. Then, open your web directory and download our latest source code from Github.
			</p>
			<a target="_blank" class="btn btn-success" href="https://github.com/CoinBlockOrg/coinblock.git">Download CoinBlock</a>
		</div>
		<div class="col-md-6">
			<h2>EmpireCoin</h2>
			<p>
			EmpireCoin is an experimental blockchain which implements one game based on the CoinBlock protocol.  EmpireCoin is based on the original Bitcoin source code.  To get started, please visit our Github page, build the EmpireCoin daemon from source and then start mining on our testnet. To avoid compile errors, please use Ubuntu.
			</p>
			<a target="_blank" class="btn btn-success" href="https://github.com/TeamEmpireCoin/EmpireCoin">Download EmpireCoin Core</a>
		</div>
	</div>
</div>
<?php
include('includes/html_stop.php');
?>