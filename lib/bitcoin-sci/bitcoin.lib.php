<?php
/**
* Bitcoin utility functions class (extended for sci lib)
*
* @author theymos (functionality)
* @author Mike Gogulski (http://www.gogulski.com/)
* @author Jacob Bruce (private/public/mini key generation)
* @author Jouke (compressed key handling)
* (encapsulation, string abstraction, PHPDoc)
*
* hex input must be in uppercase, with no leading 0x
*/
require_once(dirname(__FILE__).'/ecc-lib/auto_load.php');
require_once(dirname(__FILE__).'/Crypt/Random.php');

if (!extension_loaded('bcmath')) {
  die("Failed to load the 'bitcoin-sci' library. Please try 'yum install php-bcmath' or 'apt-get install php-bcmath'. Then restart your web server.");
}

class bitcoin {

  private static $hexchars = "0123456789ABCDEF";
  private static $base58chars = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
  
  private static $smallest_key = '10000000000000000';

  public static $address_version = "00";
  public static $privkey_version = "80";
  
  /*
   If the following 2 functions give you an error it's because you need PHP 5.3 in
   order to support late static binding. You can replace static::$address_version
   and static::$privkey_version with self::$address_version and self::$privkey_version
   to remove this error but the alt-coin classes will no longer work properly.
  */

  public static function get_address_version() {
    return static::$address_version;
  } 
  
  public static function get_privkey_version() {
    return static::$privkey_version;
  }
  
  /**
* Remove leading "0x" from a hex value if present.
*
* @param string $string
* @return string
* @access public
*/
  public static function remove0x($string) {
    if (substr($string, 0, 2) == "0x" || substr($string, 0, 2) == "0X") {
      $string = substr($string, 2);
    }
    return $string;
  }
  
  /**
* Get crytographically secure random numbers within a range.
*
* @param string $min
* @param string $max
* @return string
* @access public
*/
  public static function cryptoRandomRange($min, $max) {
	$range = bcsub($max, $min);
	if ($range == 0) return $min;
	$bytes = (int) (log($range, 2) / 8) + 1;
	do {
	  $rnd = self::bchexdec(bin2hex(crypt_random_string($bytes)));
	} while (bccomp($rnd, $range) == 1);
	return bcadd($min, $rnd);
  }
  
  /**
* Generate a random string from base58 alphabet
*
* @param integer $length
* @return string
* @access public
*/
  public static function randomString($length=16) {
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
      $randomString .= self::$base58chars[self::cryptoRandomRange(0, strlen(self::$base58chars)-1)];
    }
    return $randomString;
  }
  
  /**
* Use BC Math to convert hex to decimal string
* (copied from bcmath_Utils.php for easier access)
*
* @param string $hex
* @return string
* @access public
*/
  public static function bchexdec($hex) {
	$len = strlen($hex);
	$dec = '';
	for ($i = 1; $i <= $len; $i++)
		$dec = bcadd($dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));

	return $dec;
  }

  /**
* Convert a hex string into a (big) integer
*
* @param string $hex
* @return int
* @access private
*/
  private static function decodeHex($hex) {
    $hex = strtoupper($hex);
    $return = "0";
    for ($i = 0; $i < strlen($hex); $i++) {
      $current = (string) strpos(self::$hexchars, $hex[$i]);
      $return = (string) bcmul($return, "16", 0);
      $return = (string) bcadd($return, $current, 0);
    }
    return $return;
  }

  /**
* Convert an integer into a hex string
*
* @param int $dec
* @return string
* @access private
*/
  private static function encodeHex($dec) {
    $return = "";
    while (bccomp($dec, 0) == 1) {
      $dv = (string) bcdiv($dec, "16", 0);
      $rem = (integer) bcmod($dec, "16");
      $dec = $dv;
      $return = $return . self::$hexchars[$rem];
    }
    return strrev($return);
  }

  /**
* Convert a Base58-encoded integer into the equivalent hex string representation
*
* @param string $base58
* @return string
* @access private
*/
  public static function decodeBase58($base58) {
    $origbase58 = $base58;

    $return = "0";
    for ($i = 0; $i < strlen($base58); $i++) {
      $current = (string) strpos(self::$base58chars, $base58[$i]);
      $return = (string) bcmul($return, "58", 0);
      $return = (string) bcadd($return, $current, 0);
    }

    $return = self::encodeHex($return);

    //leading zeros
    for ($i = 0; $i < strlen($origbase58) && $origbase58[$i] == "1"; $i++) {
      $return = "00" . $return;
    }

    if (strlen($return) % 2 != 0) {
      $return = "0" . $return;
    }

    return $return;
  }

  /**
* Convert a hex string representation of an integer into the equivalent Base58 representation
*
* @param string $hex
* @return string
* @access private
*/
  public static function encodeBase58($hex) {
    if (strlen($hex) % 2 != 0) {
      throw new Exception("encodeBase58: uneven number of hex characters");
    }
    $orighex = $hex;

    $hex = self::decodeHex($hex);
    $return = "";
    while (bccomp($hex, 0) == 1) {
      $dv = (string) bcdiv($hex, "58", 0);
      $rem = (integer) bcmod($hex, "58");
      $hex = $dv;
      $return = $return . self::$base58chars[$rem];
    }
    $return = strrev($return);

    //leading zeros
    for ($i = 0; $i < strlen($orighex) && substr($orighex, $i, 2) == "00"; $i += 2) {
      $return = "1" . $return;
    }

    return $return;
  }

  /**
* Convert a 160-bit hash to an address
*
* @author theymos
* @param string $hash160
* @param string $addressversion
* @return string address
* @access public
*/
  public static function hash160ToAddress($hash160, $addressversion='null', $compressed=false) {
    $addressversion = ($addressversion=='null') ? self::get_address_version() : $addressversion;
	$hash160 = $addressversion . $hash160;
	$hash160 = ($compressed) ? $hash160."01" : $hash160;
    $check = @pack("H*", $hash160);
    $check = hash("sha256", hash("sha256", $check, true));
    $check = substr($check, 0, 8);
    $hash160 = strtoupper($hash160 . $check);
    return self::encodeBase58($hash160);
  }

  /**
* Convert an address to 160-bit hash
*
* @author theymos
* @param string $addr
* @return string 160-bit hash
* @access public
*/
  public static function addressToHash160($addr) {
    $addr = self::decodeBase58($addr);
    $addr = substr($addr, 2, strlen($addr) - 10);
    return $addr;
  }

  /**
* Determine if a string is a valid address
*
* @author theymos
* @param string $addr String to test
* @return boolean
* @access public
*/
  public static function checkAddress($addr) {
    $addr = self::decodeBase58($addr);
    if (strlen($addr) != 50) {
      return false;
    }
    $version = substr($addr, 0, 2);
    if (hexdec($version) > hexdec(self::get_address_version())) {
      return false;
    }
    $check = substr($addr, 0, strlen($addr) - 8);
    $check = @pack("H*", $check);
    $check = strtoupper(hash("sha256", hash("sha256", $check, true)));
    $check = substr($check, 0, 8);
    return $check == substr($addr, strlen($addr) - 8);
  }

  /**
* Convert the input to its 160-bit hash
*
* @param string $data
* @return string
* @access private
*/
  public static function hash160($data) {
    $data = @pack("H*", $data);
    return strtoupper(hash("ripemd160", hash("sha256", $data, true)));
  }

  /**
* Convert a public key into a 160-bit hash
*
* @param string $pubkey
* @return string
* @access public
*/
  public static function pubKeyToAddress($pubkey) {
    return self::hash160ToAddress(self::hash160($pubkey));
  }
  
  /**
* Get public key from a private key
*
* @author Jacob Bruce
* @param string $privKey
* @return string
* @access public
*/
  public static function privKeyToPubKey($privKey) {
	  
    $g = SECcurve::generator_secp256k1();
    
	$privKey = self::decodeHex($privKey);  
    $secretG = Point::mul($privKey, $g);
	
	$xHex = self::encodeHex($secretG->getX());  
	$yHex = self::encodeHex($secretG->getY());

	$xHex = str_pad($xHex, 64, '0', STR_PAD_LEFT);
	$yHex = str_pad($yHex, 64, '0', STR_PAD_LEFT);
	  
	return '04'.$xHex.$yHex;
  }
  
  /**
* Get address from a private key
*
* @author Jacob Bruce
* @param string $privKey
* @return string
* @access public
*/
  public static function privKeyToAddress($privKey) {

	$pubKey = self::privKeyToPubKey($privKey);
	$pubAdd = self::pubKeyToAddress($pubKey);
	  
	if (self::checkAddress($pubAdd)) { 
	  return $pubAdd; 
	} else { 
	  return 'invalid pub address'; 
	}
  }
  
  /**
* Generate a new private key
*
* @author Jacob Bruce
* @return string
* @access public
*/
  public static function getNewPrivKey() {

    $g = SECcurve::generator_secp256k1();
    $n = $g->getOrder();
	
    do {
      $privKey = self::cryptoRandomRange(1, $n);
      $privKeyHex = self::encodeHex($privKey);
	
	} while ((bccomp($privKey, self::$smallest_key) == -1) || (strlen($privKeyHex) > 64));
	
	return str_pad($privKeyHex, 64, '0', STR_PAD_LEFT);
  }
  
  /**
* Generate a new pair of public and private keys
*
* @author Jacob Bruce
* @return associative array ('privKey', 'PubKey') 
* @access public
*/
  public static function getNewKeyPair() {
  
	$privKey = self::getNewPrivKey(); 
	$pubKey = self::privKeyToPubKey($privKey);
	
	return array(
	  'privKey' => $privKey,
	  'pubKey' => $pubKey
	);
  }
  
  /**
* Generate a new set of keys
*
* @author Jacob Bruce
* @return associative array ('privKey', 'pubKey', 'privWIF', 'pubAdd') 
* @access public
*/
  public static function getNewKeySet() {
    do {
      $keyPair = self::getNewKeyPair();	
	  $privWIF = self::privKeyToWIF($keyPair['privKey']);
	  $pubAdd = self::pubKeyToAddress($keyPair['pubKey']);
	
	} while (!self::checkAddress($pubAdd));
	
	return array(
	  'privKey' => $keyPair['privKey'],
	  'pubKey' => $keyPair['pubKey'],
	  'privWIF' => $privWIF,
	  'pubAdd' => $pubAdd
	);
  }
  
  /**
* Convert private key to Wallet Import Format (WIF)
*
* @author Jacob Bruce
* @param string $privKey
* @return string
* @access public
*/
  public static function privKeyToWIF($privKey) {
    return self::hash160ToAddress($privKey, self::get_privkey_version());
  }
  
  /**
* Convert Wallet Import Format (WIF) to private key
*
* @author Jacob Bruce
* @param string $WIF
* @return string
* @access public
*/
  public static function WIFtoPrivKey($WIF) {
    return self::addressToHash160($WIF);
  }
  
  /**
* Checks for typos in the mini key
*
* @author Jacob Bruce
* @param string $miniKey
* @param string $klen key length
* @return boolean
* @access public
*/
  public static function checkMiniKey($miniKey, $klen=22) {
    if (strlen($miniKey) != $klen) { return false; }
	$miniHash = hash('sha256', $miniKey.'?');
  	if ($miniHash[0] == 0x00) {
	  return true;
	} else {
	  return false;
	}
  }

  /**
* Generate a new mini private key
*
* @author Jacob Bruce
* @param string $klen key length
* @return string
* @access public
*/
  public static function getNewMiniKey($klen=22) {
    $miniKey = 'S';
	do {
	  $cand = $miniKey.self::randomString($klen-1);
	  if (self::checkMiniKey($cand, $klen)) {
	    $miniKey = $cand;
	  }
	} while ($miniKey == 'S');
    return $miniKey;
  }
  
  /**
* Convert mini key to Wallet Import Format
*
* @author Jacob Bruce
* @param string $miniKey
* @param string $pkVer
* @return string
* @access public
*/
  public static function miniKeyToWIF($miniKey) {
    return self::privKeyToWIF(hash('sha256', $miniKey));
  }
  
  /**
* Get address from a mini private key
*
* @author Jacob Bruce
* @param string $miniKey
* @return string
* @access public
*/
  public static function miniKeyToAddress($miniKey) {
  
    if (!self::checkMiniKey($miniKey)) {
	  return 'invalid mini key';
	}
	  
	$privKey = hash('sha256', $miniKey);
    return self::privKeyToAddress($privKey);
  }

  /**
* Get compressed public key from a private key
*
* @author Jouke
* @param string $privKey
* @return string
* @access public
*/
  public static function privKeyToPubCompKey($privKey) {

    $g = SECcurve::generator_secp256k1();
	
    $privKey = self::decodeHex($privKey);
    $secretG = Point::mul($privKey, $g);
    $xHex = self::encodeHex($secretG->getX());
    $y = $secretG->getY();
	
	$pre = (bcmod($y,2)) ? "03" : "02";
    $xHex = str_pad($xHex, 64, '0', STR_PAD_LEFT);
    return $pre.$xHex;
  }
  
  /**
* Get compressed address from a private key
*
* @author Jouke
* @param string $privKey
* @return string
* @access public
*/
  public static function privKeyToCompAddress($privKey) {

    $pubKey = self::privKeyToPubCompKey($privKey);
    $pubAdd = self::pubKeyToAddress($pubKey);

    if (self::checkAddress($pubAdd)) {
      return $pubAdd;
    } else {
      return 'invalid pub address';
    }
  }

  /**
* Convert private key to compressed WIF
*
* @author Jouke
* @param string $privKey
* @return string
* @access public
*/
  public static function privKeyToCompWIF($privKey) {
    return self::hash160ToAddress($privKey, self::get_privkey_version(), true);
  }

  /**
* Convert compressed WIF to private key
*
* @author Jouke
* @param string $WIF
* @return string
* @access public
*/
  public static function CompWIFtoPrivKey($WIF) {
    return substr(self::addressToHash160($WIF), 0, -2);
  }
  
}
?>