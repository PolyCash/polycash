<?php
include("includes/connect.php");
include("includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$pagetitle = $GLOBALS['coin_brand_name']." - Prediction markets & blockchain gaming platform.";
$nav_tab_selected = "home";
include('includes/html_start.php');
?>
<div class="container-fluid nopadding">
	<div class="top_banner" id="home_carousel">
		<div class="carouselText"><h1><?php echo $GLOBALS['coin_brand_name']; ?></h1></div>
	</div>
</div>
<div class="container" style="max-width: 1000px; padding-top: 10px;">
	<div class="row">
		<div class="col-sm-2 text-center">
			<img alt="<?php echo $GLOBALS['coin_brand_name']; ?> Logo" id="home_logo" src="/images/logo/icon-150x150.png" />
		</div>
		<div class="col-sm-10">
			<div class="paragraph">
				Welcome to <?php echo $GLOBALS['coin_brand_name']; ?>, an open source prediction market & blockchain gaming platform. In <?php echo $GLOBALS['coin_brand_name']; ?> games the in-game currency inflates rapidly and players compete to win coins by strategically casting votes.  <?php echo $GLOBALS['coin_brand_name']; ?> supports a wide variety of game types.  Battle against a single opponent in a quick two player game, set up a daily fantasy sports game with your friends or join a distributed game and play against thousands of other players in real time.  Free games are available so that you can try <?php echo $GLOBALS['coin_brand_name']; ?> without any risk, but most games are played with real money.  You can buy in with bitcoins or dollars and then sell out at any time. Start building your empire today in this massively multiplayer online strategy game.
				<?php
				$whitepaper_fname = "EmpirecoinWhitepaper.pdf";
				if (is_file($whitepaper_fname)) {
					echo "  For more information, please read the <a href=\"".$whitepaper_fname."\">EmpireCoin Whitepaper</a>.";
				}
				?>
			</div>
		</div>
	</div>
	
	<script type="text/javascript">
	var games = new Array();
	</script>
	<?php
	$app->display_featured_games();
	?>
	
	<div class="paragraph">
		<h2>Create Your Own Coin</h2>
		Using the <?php echo $GLOBALS['coin_brand_name']; ?> platform, anyone can create an escrow-backed blockchain game, running on top of the bitcoin blockchain or a centralized game server.  To create your own coin game, select the parameters for your game, send out game_invitations and then launch your game.  Games can be free or paid.  For paid games, each player must contribute bitcoins to an escrow address.  Games end after a certain number of rounds and then the escrowed bitcoins are paid back to the players in proportion to their final in-game balances. 
	</div>
	<div class="paragraph">
		<h2>Get Involved</h2>
		Are you a developer?  We'd love some help testing, fixing bugs and building new features.  To get started, please visit our Github page, build the EmpireCoin daemon from source and then start mining on our testnet.  To avoid compile errors, please use Ubuntu.<br/>
		<a target="_blank" href="https://github.com/TeamEmpireCoin/EmpireCoin">https://github.com/TeamEmpireCoin/EmpireCoin</a>
	</div>
	
	<center><a href="" onclick="$('#homepage_more_info').toggle('fast'); return false;">Continue reading about <?php echo $GLOBALS['coin_brand_name']; ?></a></center>
	
	<div style="display: none;" id="homepage_more_info">
		<div class="paragraph">
			<h2>Strategy &amp; Gameplay</h2>
			The winning empire for an <?php echo $GLOBALS['coin_brand_name']; ?> voting round is determined entirely by the votes of the players.  Therefore, winning in <?php echo $GLOBALS['coin_brand_name']; ?> is all about colluding with other players and organizing against competing factions.  Players can form voting pools and vote together to influence the winning empire.
		</div>
		<div class="paragraph">
			<h2>Voting Pools</h2>
			<?php echo $GLOBALS['coin_brand_name']; ?>'s unique gameplay encourages stakeholders to cooperate and vote together against the other teams.  Groups can create voting pools by coding up an API endpoint which incorporates their custom voting logic.  You can assign your voting decisions to a voting pool by entering it's URL into your web wallet.
		</div>
		<div class="paragraph">
			<h2><?php echo $GLOBALS['coin_brand_name']; ?> API</h2>
			<?php echo $GLOBALS['coin_brand_name']; ?> makes it easy to set up an algorithmic voting strategy.  After signing in, choose from one of several automated voting strategies and then tweak parameters to optimize your strategy.  Or if you're a programmer, select the "Vote by API" option, download our example strategy script and then start writing code to fully customize your voting strategy.  To start coding your strategy, please read more about the <a href="/api/about/"><?php echo $GLOBALS['coin_brand_name']; ?> API</a>.
		</div>
		<?php /*<div class="paragraph">
			<h2>Proof of Burn Betting</h2>
			In addition to <?php echo $GLOBALS['coin_brand_name']; ?>'s gamified inflation, the <?php echo $GLOBALS['coin_brand_name']; ?> protocol also enables decentralized betting through a unique proof-of-burn protocol.  By sending coins to an address like "china_wins_round_777", anyone can place a bet against other bettors.  If correct, new coins will be created and sent to the winner.
		</div>*/ ?>
	</div>
</div>
<div class="navbar navbar-default" style="margin-top: 10px; margin-bottom: 0px; color: #fff;">
	<div class="container" style="max-width: 1000px;">
		<div class="row">
			<div class="col-md-6">
				<h2>Sign Up</h2>
				<p>
					To get started, please create a web wallet account.
				</p>
				<a class="btn btn-success" href="/wallet/">Create a Wallet</a>
			</div>
			<div class="col-md-6">
				<h2>Newsletter</h2>
				<p>
					If you'd like to receive important updates about this project, please subscribe to our newsletter.
				</p>
				<form onsubmit="newsletter_signup(); return false;">
					<div class="row">
						<div class="col-md-9">
							<input id="newsletter_email" class="form-control" placeholder="Enter your email address" />
						</div>
						<div class="col-md-3">
							<input type="submit" class="btn btn-primary" value="Subscribe" />
						</div>
					</div>
				</form>
				<br/><br/><br/>
			</div>
		</div>
	</div>
</div>
<script type="text/javascript">
//<![CDATA[
var homeCarousel;

var user_logged_in = <?php if (empty($thisuser)) echo 'false'; else echo 'true'; ?>;

$(document).ready(function() {
	homeCarousel = new ImageCarousel('home_carousel');
	homeCarousel.initialize();
});
//]]>
</script>
<?php
include('includes/html_stop.php');
?>
