<?php
class Analytics {
	public static function get_chart_js(&$app, $mode, $from_date, $to_date, $chart_title) {
		$num_days = round((strtotime($to_date) - strtotime($from_date))/(3600*24));
		$time_endpoint = strtotime($from_date);
		$chl = "";
		
		for ($i=0; $i<=$num_days; $i++) {
			$day_start = $time_endpoint+($i*3600*24);
			$day_end = $day_start+(3600*24);
			
			if ($mode == "signups") $qty_query = "SELECT COUNT(*) FROM users WHERE time_created >= '".$day_start."' AND time_created < '".$day_end."';";
			else if ($mode == "logins") $qty_query = "SELECT COUNT(DISTINCT(user_id)) FROM user_sessions WHERE login_time >= '".$day_start."' AND login_time < '".$day_end."';";
			else if ($mode == "currency_conversions") $qty_query = "SELECT COUNT(*) FROM currency_invoice_ios WHERE time_created >= '".$day_start."' AND time_created <= '".$day_end."';";
			else if ($mode == "emails") $qty_query = "SELECT COUNT(*) FROM async_email_deliveries WHERE time_created >= '".$day_start."' AND time_created <= '".$day_end."';";
			else if ($mode == "viewers") $qty_query = "SELECT COUNT(DISTINCT(viewer_id)) FROM pageviews WHERE pageview_date='".date("Y-m-d", ($day_start+$day_end)/2)."';";
			
			$qty = $app->run_query($qty_query)->fetch(PDO::FETCH_NUM)[0];
			
			$qty_array[$i] = $qty;
			
			if ($i == $num_days) {
				$chl .= "Today|";
			}
			else {
				$chl .= date("l", $day_start)." ".date("(n/j)", $day_start)."|";
			}
		}
		
		$js = "const ctx_".$mode." = document.getElementById('chart_".$mode."');
		  new Chart(ctx_".$mode.", {
			type: 'bar',
			data: {
			  labels: ".json_encode(explode("|", $chl)).",
			  datasets: [{
				label: ".json_encode($chart_title).",
				data: ".json_encode($qty_array).",
				borderWidth: 1
			  }]
			},
			options: {
			  maintainAspectRatio: false,
			  scales: {
				y: {
				  beginAtZero: true
				}
			  }
			}
		  });";
		
		return $js;
	}
}
