<?php
include("includes/connect.php");
include("includes/get_session.php");
$viewer_id = insert_pageview($thisuser);

$pagetitle = "EmpireCoin - Vote for your empire in the very first decentralized blockchain voting game.";
$nav_tab_selected = "home";
include('includes/html_start.php');
?>	
<div class="container-fluid nopadding">
	<div class="top_banner" id="home_carousel">
		<div class="carouselText">EmpireCoin</div>
	</div>
</div>
<div class="container" style="max-width: 1000px;">
	<p style="margin-top: 15px;">
		<div class="row">
			<div class="col-sm-2">
				<center>
					<img src="/img/logo/icon-150x150.png" style="width: 100%; max-width: 100px;" />
				</center>
			</div>
			<div class="col-sm-10">
				Welcome to EmpireCoin, the first decentralized voting game on the planet!  With EmpireCoin, you can bet money against players from around the world every hour in an epic and never-ending struggle for power.  By correctly voting your coins you'll win money, but if you're wrong you won't lose anything.  Do you love gambling, sports betting or speculating on currencies and stocks?  Stop playing rigged games and get in on the first betting game where money is created from thin air and given out to the players. Start building your empire today in this massively multiplayer online game of chance!
			</div>
		</div>
		
		<h1>Rules of the Game</h1>
		<ol class="rules_list">
			<li>
				In EmpireCoin, a voting round is concluded approximately every hour, with one of these 16 nations winning each round:
				<div style="max-width: 900px; margin: 8x 0px;">
					<?php
					$nation_q = "SELECT * FROM nations ORDER BY vote_id ASC;";
					$nation_r = run_query($nation_q);
					$n_counter = 1;
					while ($nation = mysql_fetch_array($nation_r)) { ?>
						<div class="nation_box">
							<div class="nation_flag <?php echo strtolower(str_replace(' ', '', $nation['name'])); ?>"></div>
							<div class="nation_flag_label"><?php echo $n_counter.". ".$nation['name']; ?></div>
						</div>
						<?php
						$n_counter++;
					}
					?>
				</div>
			</li>
			<li>Blocks are mined approximately every 6 minutes with the SHA256 algorithm. Miners receive 25 empirecoins per block.</li>
			<li>A voting round is concluded after every 10th block is mined.  Upon the conclusion of a round, 750 empirecoins are divided up and given out to voters who voted correctly in that round, in proportion to the amounts of their votes.</li>
			<li>Every nation has a force multiplier which is initially set to 16.  The numerator of each force multiplier is increased by 1 after every voting round, and winning a round increases the denominator of that nation's force multiplier by 1.</li>
			<li>The voting score for each nation is calculated upon the conclusion of a round as it's force multiplier times the sum of its coin votes during that round. This voting score determines the winning nation for the round.</li>
			<li>The eligible nation with the highest voting score wins the round.  In the case of an exact tie of voting scores, the nation with the lowest ID number wins.</li>
			<li>If any nation achieves greater than 50% of the coin votes in a particular round, it is disqualified from winning that round for tyranny of the majority and the nation with the next highest voting score wins the round.</li>
		</ol>
		<br/>
	</p>
</div>

<script type="text/javascript">
function Image(id) {
	this.imageId = id;
	this.imageSrc = '/img/carousel/'+id+'.jpg';
}
function ImageCarousel(containerElementId) {
	this.numPhotos = 16;
	this.currentPhotoId = -1;
	this.slideTime = 10000;
	this.widthToHeight = Math.round(1800/570, 6);
	this.containerElementId = containerElementId;
	this.images = new Array();
	var _this = this;
	
	this.initialize = function() {
		for (var imageId=0; imageId<this.numPhotos; imageId++) {
			this.images[imageId] = new Image(imageId);
			$('<img />').attr('src',this.images[imageId].imageSrc).appendTo('body').css('display','none');
			$('#'+this.containerElementId).append('<div id="'+this.containerElementId+'_image'+imageId+'" class="carouselImage" style="background-image: url(\''+this.images[imageId].imageSrc+'\');"></div>');
		}
		
		this.nextPhoto();
	};
	
	this.nextPhoto = function() {
		var prevPhotoId = this.currentPhotoId;
		var curPhotoId = prevPhotoId + 1;
		if (curPhotoId == this.numPhotos) curPhotoId = 0;
		
		if (prevPhotoId == -1) {}
		else $('#'+this.containerElementId+'_image'+prevPhotoId).fadeOut('slow');
		
		$('#'+this.containerElementId+'_image'+curPhotoId).fadeIn('slow');
		this.currentPhotoId = curPhotoId;
		
		setTimeout(function() {_this.nextPhoto()}, this.slideTime);
	};
}

var homeCarousel;

$(document).ready(function() {
	homeCarousel = new ImageCarousel('home_carousel');
	homeCarousel.initialize();
});

$(".navbar-toggle").click(function(event) {
	$(".navbar-collapse").toggle('in');
});
</script>
<?php
include('includes/html_stop.php');
?>