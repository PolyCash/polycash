<?php
/**
* Bitcoin SCI class
*
* @author Jacob Bruce
* www.bitfreak.info
*	Modified By Shayan Eskandari
*	Theshayan.com
*/

// requires AES.php, RSA.php & config.php

class bitsci {

  public static function curl_simple_post($url_str, $ver_ssl=true) {
	
    // Initializing cURL
    $ch = curl_init();
  
    // Setting curl options
    curl_setopt($ch, CURLOPT_URL, $url_str);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $ver_ssl);
    curl_setopt($ch, CURLOPT_USERAGENT, "PHP/".phpversion());

    // Getting jSON result string
    $result = curl_exec($ch); 
  
    // close cURL and json file
    curl_close($ch);

    // return cURL result
    return $result;
  
  }
  
  public static function send_btc_request($api_sel, $addr_str, $confirmations) {
  
    if ($api_sel === 1 || $confirmation=0) {
      $api_url = 'https://blockchain.info/q/addressbalance/'.$addr_str.'?confirmations='.$confirmations;
	  $result = self::curl_simple_post($api_url);
	  if (is_numeric($result)) {
	    return $result / 100000000;
	  } else {
	    return $result;
	  }
	} else {
	  return self::curl_simple_post('https://blockexplorer.com/q/getreceivedbyaddress/'.$addr_str.'/'.$confirmations);
	}
  }
  
  public static function send_ltc_request($api_sel, $addr_str, $confirmations) {
  
    if ($api_sel === 1) {
	  return self::curl_simple_post('http://explorer.litecoin.net/chain/Litecoin/q/getreceivedbyaddress/'.$addr_str.'/'.$confirmations, false);
	} else {
	  return self::curl_simple_post('http://litecoinscout.com/chain/Litecoin/q/getreceivedbyaddress/'.$addr_str.'/'.$confirmations, false);
	}
  }
 
  public static function send_request($api_sel, $addr_str, $confirmations, $currency='btc') {
	switch ($currency) {
	  case 'btc': return self::send_btc_request($api_sel, $addr_str, $confirmations); break;
	  case 'ltc': return self::send_ltc_request($api_sel, $addr_str, $confirmations); break;
	  default: return 'ERROR: unknown currency';
	}
  }
  
  public static function get_balance($addr_str, $confirmations=CONF_NUM, $change_api=true, $currency='btc') {
	
	// select an API based on session state
	if (!empty($_SESSION['api_sel'])) {
	  $api_sel = $_SESSION['api_sel'];
	} else {
	  $api_sel = 1;
	}
	
	// change between API's every few calls
	if ($change_api) {
      if (!empty($_SESSION['call_num'])) {
	    if ($_SESSION['call_num'] > 2) {
	      $_SESSION['call_num'] = 1;
		  $api_sel = ($api_sel === 1) ? 2 : 1;
		  $_SESSION['api_sel'] = $api_sel;
	    } else {
	      $_SESSION['call_num']++;
	    }
	  } else {
	    $_SESSION['call_num'] = 1;
	  }
	}

	// send request to selected API
    $result = self::send_request($api_sel, $addr_str, $confirmations, $currency);

	// if API is offline then try alternative
	if ($result === false) {
	  $api_sel = ($api_sel === 1) ? 2 : 1;
	  $_SESSION['api_sel'] = $api_sel;
	  $result = self::send_request($api_sel, $addr_str, $confirmations, $currency);
	}

	return $result;
  }
  
  public static function check_payment($price, $addr_str, $confirmations=CONF_NUM, $p_variance=0, $change_api=true, $currency='btc') {
  
	$balance = self::get_balance($addr_str, $confirmations, $change_api, $currency);
	$str_start = explode(' ', $balance);

	if ($balance === false) {
	  return 'e1';
	} elseif (($balance === 'ERROR: invalid address') || ($balance === 'Checksum does not validate')) {
	  return 'e2';
	} elseif (($str_start[0] === 'ERROR:') || !is_numeric($balance)) {
	  return 'e3';
	} elseif ($balance > 0) {
	  if (($balance + $p_variance) < $price) {
	    return 'e4';
	  } else {
	    return true;
	  }
	} else {
	  return false;
	}
  }
  
  public static function rsa_encrypt($input_str, $key) {
  
    $rsa = new Crypt_RSA();
 
    $rsa->setPrivateKeyFormat(CRYPT_RSA_PRIVATE_FORMAT_PKCS1);
    $rsa->setPublicKeyFormat(CRYPT_RSA_PUBLIC_FORMAT_PKCS1);
    $rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);

	$public_key = array(
		'n' => new Math_BigInteger($key, 16),
		'e' => new Math_BigInteger('65537', 10)
	);
	
	$rsa->loadKey($public_key, CRYPT_RSA_PUBLIC_FORMAT_RAW);

    return $rsa->encrypt($input_str);	
  }
  
  public static function encrypt_data($input_str, $key=SEC_STR) {
  
    $aes = new Crypt_AES();
    $aes->setKey($key);	

    return $aes->encrypt($input_str);	
  }
  
  public static function decrypt_data($input_str, $key=SEC_STR) {
  
    $aes = new Crypt_AES();
    $aes->setKey($key);	

    return $aes->decrypt($input_str);	
  }
  
  public static function build_pay_query($pubAdd, $price, $quantity, $item, $seller, $success_url, $cancel_url, $note, $baggage, $dollar_amount) {
  
    $td = implode('|', array($pubAdd, $price, $quantity, $item, $seller, $success_url, $cancel_url, $note, $baggage, $dollar_amount));
	return base64_encode(self::encrypt_data($td));
  }
  
  public static function btc_num_format($num, $dec=8, $sep=SEP_STR) {
  
    return number_format($num, $dec, '.', $sep);	
  }
 
  public static function JSONtoAmount($value) {
  
    return round(value * 1e8);
  }
}
?>