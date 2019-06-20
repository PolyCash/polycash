<?php
require_once(dirname(__FILE__).'/bitcoin.lib.php');

// IMPORTANT: only Litecoin is supported in the payment gateway.
// WARNING: most of these are untested. Use with caution.

class litecoin extends bitcoin {
  public static $address_version = "30"; //48
  public static $privkey_version = "B0"; //176
}

class bbqcoin extends bitcoin {
  public static $address_version = "05"; //85
  public static $privkey_version = "D5"; //213
}

class bitbar extends bitcoin {
  public static $address_version = "19"; //25
  public static $privkey_version = "99"; //153
}

class bytecoin extends bitcoin {
  public static $address_version = "12"; //18
  public static $privkey_version = "80"; //128
}

class chncoin extends bitcoin {
  public static $address_version = "1C"; //28
  public static $privkey_version = "9C"; //156
}

class devcoin extends bitcoin {
  public static $address_version = "00"; //0
  public static $privkey_version = "80"; //128
}

class feathercoin extends bitcoin {
  public static $address_version = "0E"; //14
  public static $privkey_version = "8E"; //142
}

class freicoin extends bitcoin {
  public static $address_version = "00"; //0
  public static $privkey_version = "80"; //128
}

class junkcoin extends bitcoin {
  public static $address_version = "10"; //16
  public static $privkey_version = "90"; //90
}

class mincoin extends bitcoin {
  public static $address_version = "32"; //50
  public static $privkey_version = "B2"; //178
}

class namecoin extends bitcoin {
  public static $address_version = "34"; //52
  public static $privkey_version = "B4"; //180
}

class novacoin extends bitcoin {
  public static $address_version = "08"; //8
  public static $privkey_version = "88"; //136
}

class onecoin extends bitcoin {
  public static $address_version = "73"; //115
  public static $privkey_version = "F3"; //243
}

class ppcoin extends bitcoin {
  public static $address_version = "37"; //55
  public static $privkey_version = "B7"; //183
}

class smallchange extends bitcoin {
  public static $address_version = "3E"; //62
  public static $privkey_version = "BE"; //190
}

class terracoin extends bitcoin {
  public static $address_version = "00"; //0
  public static $privkey_version = "80"; //128
}

class yacoin extends bitcoin {
  public static $address_version = "4D"; //77
  public static $privkey_version = "CD"; //205
}
?>