<?php
class jsonRPCClient {
	private $url;
	
	public function __construct($url) {
		$this->url = $url;
	}
	
	public function __call($method,$params) {
		if (!is_scalar($method)) {
			throw new Exception('Invalid method name for JSON RPC call.');
		}
		
		if (is_array($params)) {
			$params = array_values($params);
		} else {
			throw new Exception('JSON RPC params must be given as array.');
		}
		
		$request = [
			'method' => $method,
			'params' => $params,
			'id' => 1
		];
		$request = json_encode($request);
		
		$ch = curl_init($this->url);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
		$response_raw = curl_exec($ch);
		curl_close($ch);
		
		if ($response_raw === false) return false;
		else {
			if (!$response = json_decode($response_raw,true)) {
				throw new Exception("RPC call $method failed");
			}
			
			if (!is_null($response['error'])) return $response['error'];
			else return $response['result'];
		}
	}
}
?>