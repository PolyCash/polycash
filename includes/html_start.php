<?php
if (empty($nav_tab_selected)) $nav_tab_selected = "";
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="apple-mobile-web-app-capable" content="yes" />
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	
	<title><?php if (!empty($pagetitle)) echo $pagetitle; ?></title>
	
	<link rel="stylesheet" type="text/css" href="/css/style.css<?php if (!empty($GLOBALS['cachebuster'])) echo '?v='.$GLOBALS['cachebuster']; ?>" />
	<link rel="stylesheet" type="text/css" href="/css/AdminLTE.min.css">
	<link rel="stylesheet" type="text/css" href="/css/skin-blue.min.css">
	<link rel="stylesheet" type="text/css" href="/css/jquery.ui.css" />
	<link rel="stylesheet" type="text/css" href="/css/jquery.nouislider.css" />
	<link rel="stylesheet" type="text/css" href="/css/bootstrap.min.css" media="screen" />
	<link rel="stylesheet" type="text/css" href="/css/fontawesome-all.min.css" media="screen" />
	<?php
	if ($GLOBALS['signup_captcha_required']) { ?>
	<script type="text/javascript" src="https://www.google.com/jsapi"></script>
	<?php
	}
	?>
	<script type="text/javascript" src="/js/jquery-1.11.3.js"></script>
	<script type="text/javascript" src="/js/bootstrap.min.js"></script>
	<script type="text/javascript" src="/js/jquery.ui.js"></script>
	<script type="text/javascript" src="/js/jquery.nouislider.js"></script>
	<script type="text/javascript" src="/js/sha256.js"></script>
	<script type="text/javascript" src="/js/main.js<?php if (!empty($GLOBALS['cachebuster'])) echo '?v='.$GLOBALS['cachebuster']; ?>"></script>
	<script type="text/javascript" src="/js/adminlte.min.js"></script>
	<script type="text/javascript" src="/js/tiny.editor.js"></script>
	<script type="text/javascript" src="/js/chart.js"></script>
	<script type="text/javascript" src="/js/maskedinput.js"></script>
	<script type="text/javascript" src="/js/qrcam.js"></script>
	<?php
	if (!empty($include_crypto_js)) { ?>
	<script type="text/javascript" src="/js/base64.lib.js" ></script>
	<script type="text/javascript" src="/js/rsa/prng4.js"></script>
	<script type="text/javascript" src="/js/rsa/rng.js"></script>
	<script type="text/javascript" src="/js/rsa/rsa.js"></script>
	<script type="text/javascript" src="/js/rsa/rsa2.js"></script>
	<script type="text/javascript" src="/js/rsa/base64.js"></script>
	<script type="text/javascript" src="/js/rsa/jsbn.js"></script>
	<script type="text/javascript" src="/js/rsa/jsbn2.js"></script>
	<?php
	}
	?>
	<meta property="og:image" content="<?php echo $GLOBALS['base_url']; ?>/images/logo/icon-150x150.png"/>
</head>
<body class="hold-transition skin-blue sidebar-mini">
<div class="wrapper">
	<header class="main-header">
		<a href="/" class="logo">
			<span class="logo-mini"><b>P</b>C</span>
			<span class="logo-lg"><b>Poly</b>Cash</span>
		</a>

		<!-- Header Navbar -->
		<nav class="navbar navbar-static-top" role="navigation">
			<!-- Sidebar toggle button-->
			<a href="#" class="fa fa-bars" data-toggle="push-menu" role="button" style="padding: 15px; line-height: 20px; color: #fff; text-decoration: none;">
				<span class="sr-only">Toggle navigation</span>
			</a>
			<!-- Navbar Right Menu -->
			<div class="navbar-custom-menu">
				<ul class="nav navbar-nav">
					<?php
					if (empty($thisuser)) {
						if (empty($redirect_url)) $redirect_url = $app->get_redirect_url($_SERVER['REQUEST_URI']);
						echo '<li><a href="/accounts/?redirect_key='.$redirect_url['redirect_key'].'">Log In</a></li>';
					}
					if (!empty($thisuser)) { ?>
						<li class="dropdown user user-menu">
							<a href="/profile/" class="dropdown-toggle" data-toggle="dropdown">
							  <span class="hidden-xs"><?php echo $thisuser->db_user['username']; ?></span>
							</a>
							<ul class="dropdown-menu">
							  <!-- User image -->
							  <li class="user-header">
								<p>
								  <?php echo $thisuser->db_user['username']; ?>
								  <small>Joined <?php echo date("M, Y", $thisuser->db_user['time_created']); ?></small>
								</p>
							  </li>
							  <!-- Menu Footer-->
							  <li class="user-footer">
								<div class="pull-left">
								  <a href="/profile/" class="btn btn-sm btn-primary">Profile</a>
								</div>
								<div class="pull-right">
								  <a href="/wallet/?action=logout" class="btn btn-sm btn-success">Sign out</a>
								</div>
							  </li>
							</ul>
						</li>
						<?php
					}
					?>
				</ul>
			</div>
		</nav>
	</header>
	
	<aside class="main-sidebar">
		<section class="sidebar">
			<?php
			if ($nav_tab_selected == "cards" || $nav_tab_selected == "accounts") { ?>
				<ul class="sidebar-menu" data-widget="tree">
					<li class="header">Cards</li>
					<li id="section_link_cards"><a href="/cards/?start_section=cards"<?php if ($nav_subtab_selected == "cards") echo ' onclick="open_page_section(\'cards\'); return false;"'; ?>><i class="fa fa-address-book"></i> <span>My Cards</span></a></li>
					<li id="section_link_add_card"><a href="/cards/?start_section=add_card"<?php if ($nav_subtab_selected == "cards") echo ' onclick="open_page_section(\'add_card\'); return false;"'; ?>><i class="fa fa-link"></i> <span>Connect a Card</span></a></li>
					<li id="section_link_withdraw_btc"><a href="/cards/?start_section=withdraw_btc"<?php if ($nav_subtab_selected == "cards") echo ' onclick="open_page_section(\'withdraw_btc\'); return false;"'; ?>><i class="fa fa-exchange-alt"></i> <span>Withdraw Bitcoins</span></a></li>
					<li<?php if ($nav_subtab_selected == "manage") echo ' class="active"'; ?>><a href="/cards/?action=manage"><i class="fa fa-print"></i> <span>Print cards</span></a></li>
					<li<?php if ($nav_subtab_selected == "create") echo ' class="active"'; ?>><a href="/cards/?action=create"><i class="fa fa-plus-circle"></i> <span>Create cards</span></a></li>
					<?php if (empty($thisuser)) { ?><li<?php if ($nav_subtab_selected == "redeem") echo ' class="active"'; ?>><a href="/redeem/"><i class="fa fa-money"></i> <span>Redeem a card</span></a></li><?php } ?>
				</ul>
				<?php
			}
			else if (!empty($game)) { ?>
				<ul class="sidebar-menu" data-widget="tree">
					<li class="header"><?php echo $game->db_game['name']; ?></li>
					<li<?php if ($nav_tab_selected == "game_page") echo ' class="active"'; ?>><a href="/<?php echo $game->db_game['url_identifier']; ?>/"><i class="fa fa-question-circle"></i> <span>About</span></a></li>
					<li id="tabcell0"><a <?php if ($nav_tab_selected == "wallet") echo 'href="" onclick="tab_clicked(0); return false;"'; else echo 'href="/wallet/'.$game->db_game['url_identifier'].'/?initial_tab=0"'; ?>><i class="fa fa-play"></i> <span>Play Now</span></a></li>
					<li id="tabcell1"><a <?php if ($nav_tab_selected == "wallet") echo 'href="" onclick="tab_clicked(1); return false;"'; else echo 'href="/wallet/'.$game->db_game['url_identifier'].'/?initial_tab=1"'; ?>><i class="fa fa-users"></i> <span>Players</span></a></li>
					<li id="tabcell2"><a <?php if ($nav_tab_selected == "wallet") echo 'href="" onclick="tab_clicked(2); return false;"'; else echo 'href="/wallet/'.$game->db_game['url_identifier'].'/?initial_tab=2"'; ?>><i class="fa fa-cogs"></i> <span>Settings</span></a></li>
					<li id="tabcell4"><a <?php if ($nav_tab_selected == "wallet") echo 'href="" onclick="tab_clicked(4); return false;"'; else echo 'href="/wallet/'.$game->db_game['url_identifier'].'/?initial_tab=4"'; ?>><i class="fa fa-exchange-alt"></i> <span>Deposit or Withdraw</span></a></li>
					<li id="tabcell5"><a <?php if ($nav_tab_selected == "wallet") echo 'href="" onclick="tab_clicked(5); return false;"'; else echo 'href="/wallet/'.$game->db_game['url_identifier'].'/?initial_tab=5"'; ?>><i class="fa fa-envelope"></i> <span>Invitations</span></a></li>
					<li<?php if ($nav_tab_selected == "explorer" && $explore_mode == "my_bets") echo ' class="active"'; ?>><a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/my_bets/"><i class="fa fa-chart-area"></i> <span>My Bets</span></a></li>
					<li<?php if ($nav_tab_selected == "explorer" && $explore_mode != "my_bets") echo ' class="active"'; ?>><a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/events/"><i class="fa fa-cube"></i> <span>Explorer</span></a></li>
					<?php if ($app->user_can_edit_game($thisuser, $game)) { ?>
					<li<?php if ($nav_tab_selected == "manage_game") echo ' class="active"'; ?>><a href="/manage/<?php echo $game->db_game['url_identifier']; ?>/"><i class="fa fa-edit"></i> <span>Manage this Game</span></a></li>
					<?php } ?>
				</ul>
				<?php
			}
			?>
			<ul class="sidebar-menu" data-widget="tree">
				<?php
				if (!empty($thisuser)) {
					$cardcount_q = "SELECT COUNT(*) FROM cards WHERE user_id='".$thisuser->db_user['user_id']."' AND status='claimed';";
					$cardcount_r = $app->run_query($cardcount_q);
					$cardcount = $cardcount_r->fetch();
					$cardcount = $cardcount['COUNT(*)'];
				}
				else $cardcount = 0;
				?>
				<li class="header">Navigation</li>
				<li<?php if ($nav_tab_selected == "home") echo ' class="active"'; ?>><a href="/"><i class="fa fa-home"></i> <span>Home</span></a></li>
				<?php
				if (file_exists("pages/about.php")) { ?>
					<li<?php if ($nav_tab_selected == "about") echo ' class="active"'; ?>><a href="/about/"><i class="fa fa-question"></i> <span>About</span></a></li>
					<?php
				}
				?>
				<li<?php if ($nav_tab_selected == "directory" && empty($selected_category)) echo ' class="active"'; ?>><a href="/directory/"><i class="fa fa-sitemap"></i> <span>Browse Games</span></a></li>
				<li<?php if ($nav_tab_selected == "wallet" && empty($game)) echo ' class="active"'; ?>><a href="/wallet/"><i class="fa fa-cubes"></i> <span>My Games</span></a></li>
				<li<?php if ($nav_tab_selected == "accounts") echo ' class="active"'; ?>><a href="/accounts/"><i class="fa fa-user-circle"></i> <span>My Accounts</span></a></li>
				<li<?php if ($nav_tab_selected == "cards") echo ' class="active"'; ?>><a href="/cards/"><i class="fa fa-id-card"></i> <span>My Cards</span><?php
				if ($cardcount > 0) echo '<span class="pull-right-container"><small class="label pull-right bg-red">'.$cardcount.'</small></span>';
				?></a></li>
				<li<?php if ($nav_tab_selected == "download") echo ' class="active"'; ?>><a target="_blank" href="https://github.com/polycash/polycash"><i class="fa fa-download"></i> <span>Download</span></a></li>
				<li<?php if ($nav_tab_selected == "explorer") echo ' class="active"'; ?>><a href="/explorer/<?php if (!empty($game)) echo "games/".$game->db_game['url_identifier']."/blocks/"; ?>"><i class="fa fa-cube"></i> <span>Blockchain Explorer</span></a></li>
				<li<?php if ($nav_tab_selected == "api") echo ' class="active"'; ?>><a href="/api/"><i class="fa fa-code"></i> <span>API</span></a></li>
				<li<?php if ($nav_tab_selected == "manage") echo ' class="active"'; ?>><a href="/manage/"><i class="fa fa-plus-circle"></i> <span>Create a New Game</span></a></li>
			</ul>
			<?php
			if (empty($game) && $nav_tab_selected != "cards" && $nav_tab_selected != "accounts") {
				?>
				<ul class="sidebar-menu" data-widget="tree">
					<li class="header">Categories</li>
					<?php
					$q = "SELECT * FROM categories WHERE category_level=0 ORDER BY display_rank ASC;";
					$r = $app->run_query($q);
					
					while ($db_category = $r->fetch()) {
						$qq = "SELECT * FROM categories WHERE parent_category_id='".$db_category['category_id']."' ORDER BY display_rank ASC;";
						$rr = $app->run_query($qq);
						
						if ($rr->rowCount() > 0) {
							echo '<li class="treeview';
							if (!empty($selected_category) && $selected_category['category_id'] == $db_category['category_id']) echo " active";
							echo '">';
							echo '<a href="#"><i class="fa fa-';
							if (!empty($db_category['icon_name'])) echo $db_category['icon_name'];
							else echo "link";
							echo '"></i> <span>'.$db_category['category_name'].'</span>';
							echo '<span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>';
							echo '</a><ul class="treeview-menu">';
							echo '<li><a href="/'.$db_category['url_identifier'].'/">All</a></li>'."\n";
							while ($subcategory = $rr->fetch()) {
								echo '<li';
								if (!empty($selected_subcategory) && $selected_subcategory['category_id'] == $subcategory['category_id']) echo ' class="active"';
								echo '><a href="/'.$db_category['url_identifier'].'/'.$subcategory['url_identifier'].'/">'.$subcategory['category_name']."</a></li>\n";
							}
							echo '</ul>';
							echo "</li>\n";
						}
						else {
							echo "<li";
							if (!empty($selected_category) && $selected_category['category_id'] == $db_category['category_id']) echo ' class="active"';
							echo "><a href=\"/".$db_category['url_identifier']."/\"><i class=\"fa fa-";
							if (!empty($db_category['icon_name'])) echo $db_category['icon_name'];
							else echo "link";
							echo "\"></i> <span>".$db_category['category_name']."</span></a></li>\n";
						}
					}
					?>
				</ul>
				<?php
			}
			?>
			<!-- /.sidebar-menu -->
		</section>
		<!-- /.sidebar -->
	</aside>
	<div class="content-wrapper">