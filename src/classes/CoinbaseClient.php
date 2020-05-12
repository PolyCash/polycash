<?php
class CoinbaseClient {
	private $api_base_url = 'https://api.pro.coinbase.com';
	private $access_key;
	private $passphrase;
	
	public function __construct($access_key, $secret, $passphrase) {
		$this->access_key = $access_key;
		$this->secret = $secret;
		$this->passphrase = $passphrase;
	}
	
	public function signature($uri='', $request_params='', $timestamp=false, $method='GET') {
        $request_params_str = is_string($request_params) ? $request_params : json_encode($request_params);
        $timestamp = $timestamp ? $timestamp : time();
		
        $what = $timestamp.$method.$uri.$request_params_str;
		
        return base64_encode(hash_hmac("sha256", $what, base64_decode($this->secret), true));
    }
	
	public function apiRequest($uri, $request_method, $request_params) {
		$request_method = strtoupper($request_method);
		
		if (!empty($request_params) && !is_string($request_params)) $request_params = json_encode($request_params);
		
		if ($request_method == "GET" && strlen($request_params) > 0) {
			$uri .= "?".http_build_query(json_decode($request_params));
		}
		
		$url = $this->api_base_url.$uri;
		
		$ch = curl_init();
		
		curl_setopt_array($ch, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $request_method,
			CURLOPT_HEADER => 1
		]);
		
		$curl_headers = [
			"accept: application/json",
			"content-type: application/json",
			"user-agent: curl",
			"CB-ACCESS-KEY: ".$this->access_key,
			"CB-ACCESS-SIGN: ".$this->signature($uri, $request_params, time(), $request_method),
			"CB-ACCESS-TIMESTAMP: ".time(),
			"CB-ACCESS-PASSPHRASE: ".$this->passphrase
		];
		
		if (!empty($request_params)) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $request_params);
			array_push($curl_headers, "Content-Length: ".strlen($request_params));
		}
		
		curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
		
		$api_response = curl_exec($ch);
		$error_message = curl_error($ch);
		
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$headers_str = substr($api_response, 0, $header_size);
		$body_str = substr($api_response, $header_size);
		
		$header_lines = explode("\n", $headers_str);
		$header_arr = [];
		
		foreach ($header_lines as $header_line) {
			$first_colon = strpos($header_line, ':');
			$header_param = trim(substr($header_line, 0, $first_colon));
			
			if (!empty($header_param)) {
				$header_val = trim(substr($header_line, $first_colon+1, strlen($header_line)-$first_colon));
				$header_arr[$header_param] = $header_val;
			}
		}
		
		$api_response = json_decode($body_str);
		
		return [$api_response, $header_arr, $error_message];
	}
}
