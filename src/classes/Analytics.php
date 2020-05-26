<?php
class Analytics {
	public static function get_chart_url(&$app, $mode, $from_date, $to_date, $graph_width, $graph_height, $graph_colors) {
		$num_days = round((strtotime($to_date) - strtotime($from_date))/(3600*24));
		$time_endpoint = strtotime($from_date);
		$chl = "";
		$max = 1;
		$pv_array = "";
		
		$horz_pix_per_day = floor(($graph_width-10)/($num_days+1));
		$spacer_pix = ceil($horz_pix_per_day*0.4);
		$bar_pix = $horz_pix_per_day-$spacer_pix;
		$num_graphs = 1;
		
		for ($i=0; $i<=$num_days; $i++) {
			$day_start = $time_endpoint+($i*3600*24);
			$day_end = $day_start+(3600*24);
			
			if ($mode == "signups") $qty_query = "SELECT COUNT(*) FROM users WHERE time_created >= '".$day_start."' AND time_created < '".$day_end."';";
			else if ($mode == "logins") $qty_query = "SELECT COUNT(DISTINCT(user_id)) FROM user_sessions WHERE login_time >= '".$day_start."' AND login_time < '".$day_end."';";
			else if ($mode == "currency_conversions") $qty_query = "SELECT COUNT(*) FROM currency_invoice_ios WHERE time_created >= '".$day_start."' AND time_created <= '".$day_end."';";
			else if ($mode == "viewers") $qty_query = "SELECT COUNT(DISTINCT(viewer_id)) FROM pageviews WHERE pageview_date='".date("Y-m-d", ($day_start+$day_end)/2)."';";
			
			$qty = $app->run_query($qty_query)->fetch(PDO::FETCH_NUM)[0];
			
			$qty_array[$i] = $qty;
			if ($qty > $max) $max = $qty;
			
			if ($i == $num_days) {
				if ($horz_pix_per_day > 60)
					$chl .= date("l", $day_start)."%20(Today)|";
				else 
					$chl .= "Today|";
			}
			else {
				if ($horz_pix_per_day < 16)
					$chl .= "|";
				else if ($horz_pix_per_day < 32)
					$chl .= date("j", $day_start)."|";
				else if ($horz_pix_per_day < 60)
					$chl .= date("n/j", $day_start)."|";
				else if ($horz_pix_per_day < 94)
					$chl .= date("D", $day_start)."%20".date("(n/j)", $day_start)."|";
				else 
					$chl .= date("l", $day_start)."%20".date("(n/j)", $day_start)."|";
			}
		}
		
		$max = round($max*1.2);
		$chl = substr($chl, 0, strlen($chl)-1);
		$chm = substr($chm, 0, strlen($chm)-1);
		$pix_per_view = round(100/$max, 0);
		
		$chd = "";
		for ($i=0; $i<=$num_days; $i++) {
			$chd .= ($qty_array[$i]).",";
		}
		$chd = substr($chd, 0, strlen($chd)-1);
		$chm = "N,000000,0,-1,11";
		$graph_url = "http://chart.apis.google.com/chart?cht=bvs:nda&chxt=x&chls=3&chco=".$graph_colors."&chs=".$graph_width."x".$graph_height."&chbh=".$bar_pix.",".$spacer_pix."&chl=".$chl."&chm=".$chm."&chd=t:".$chd."&chds=0,".$max;
		
		return $graph_url;
	}
}
