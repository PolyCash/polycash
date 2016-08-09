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
	You can help secure the EmpireCoin network by hosting an EmpireCoin node.  First, please install EmpireCoin Web using the link below.
	<div class="row">
		<div class="col-md-6">
			<h2>EmpireCoin Web</h2>
			<p>
			EmpireCoin Web is a front-end wallet for the EmpireCoin protocol.  You can also use EmpireCoin Web to create and run custom EmpireCoin games.  To get started, please install PHP, MySQL and Apache on your computer.  If you're on Windows you can get PHP, MySQL and Apache by installing WAMP or XAMPP. Then, open your web directory and download our latest source code from Github.
			</p>
			<a target="_blank" class="btn btn-success" href="https://github.com/TeamEmpireCoin/empirecoin-web">Download EmpireCoin Web</a>
		</div>
		<div class="col-md-6">
			<h2>EmpireCoin Core</h2>
			<p>
			EmpireCoin Core is a back-end program which handles peer to peer interactions between EmpireCoin nodes.  EmpireCoin Core is based on the original Bitcoin source code.  To get started, please visit our Github page, build the EmpireCoin daemon from source and then start mining on our testnet. To avoid compile errors, please use Ubuntu.
			</p>
			<a target="_blank" class="btn btn-success" href="https://github.com/TeamEmpireCoin/EmpireCoin">Download EmpireCoin Core</a>
		</div>
	</div>
</div>
<?php
include('includes/html_stop.php');
?>