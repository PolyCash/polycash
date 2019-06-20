<?php
/**
* General Functions
*
* @author Jacob Bruce
* www.bitfreak.info
*/

// unset cookies
function unset_cookies() {
  if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
      $parts = explode('=', $cookie);
      $name = trim($parts[0]);
      setcookie($name, '', time()-1000);
      setcookie($name, '', time()-1000, '/');
    }
  }
}

function fwrite_stream($fp, $string) {
    for ($written = 0; $written < strlen($string); $written += $fwrite) {
        $fwrite = fwrite($fp, substr($string, $written));
        if ($fwrite === false) {
            return $written;
        }
    }
    return $written;
}

function rand_str() {
  return md5(uniqid(rand(), true));
}

function hextostr($x) {
  $s = '';
  foreach(explode("\n",trim(chunk_split($x,3))) as $h) $s.=chr(hexdec($h));
  return($s);
}

function strtohex($x) {
  $s = '';
  foreach(str_split($x) as $c) $s.='%'.sprintf("%02X",ord($c));
  return($s);
}

function chartostr($x) {
  $s = '';
  foreach (explode(',', $x) as $h) { $s.=chr($h); }
  return($s);
}

function strtochar($x) {
  $s = '';
  foreach (str_split($x) as $c) { $s.=ord($c).','; };
  return(trim($s, ','));
}

// replaces empty/null array values with 'N/A'
function fluff_array($array) {
  foreach ($array as $key => $value) {
    if (($array[$key] == NULL) || (empty($array[$key]) && 
	   (isset($array[$key]) && ($array[$key] != 0)))) {
		
      $array[$key] = 'N/A';  
    }
  }
  return $array;
}

// redirection function - tries PHP, then JS, then HTML
function redirect($url) {
    if (!headers_sent()) { 
        header('Location: '.$url); 
		exit;
    } else {
        echo "<script type='text/javascript'>";
        echo "window.location.href='".$url."';";
        echo "</script>";
        echo "<noscript>";
        echo "<meta http-equiv='refresh' content='0;url=$url' />";
        echo "</noscript>";
    }
}

// simple function to get remote IP
function get_remote_ip() {
  if (!empty($_SERVER['REMOTE_ADDR'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
  } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
  } else {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
  }
  return $ip;
}

// checks if string starts with substring
function starts_with($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

// checks if string ends with substring
function ends_with($haystack, $needle) {
    $length = strlen($needle);
    $start =  $length *-1; //negative
    return (substr($haystack, $start, $length) === $needle);
}

// convert number/boolean into yes/no string
function bool_str($value) {
  if ($value) {
    return 'yes'; 
  } else {
    return 'no';
  }
}

// store last page URL string as session variable
function set_last_page_url() {
    $page_root = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];	
  if (empty($_SERVER['QUERY_STRING'])) {
    $_SESSION['LAST_PAGE'] = $page_root;
  } else {
    $_SESSION['LAST_PAGE'] = $page_root.'?'.$_SERVER['QUERY_STRING'];
  }	
}

// send file directly to browser via binary stream
function send_file_to_browser($download_name, $file_name) {

  // must be fresh start
  if(headers_sent())
    die('Headers Sent'); 
	
  // file must exist
  if (!file_exists($file_name)) {
    die('file does not exist!');
  }

  $path_info = pathinfo($file_name);

  // check that extension key exists in array
  if (!array_key_exists('extension', $path_info)) {
    $path_info['extension'] = '';
  }
  
  // http://en.wikipedia.org/wiki/Mime_type#List_of_common_media_types
  switch (strtolower($path_info['extension'])) {
	case 'exe':
                $mime_type = "application/octet-stream";
                break;
    case 'doc':
	            $mime_type = "application/msword";
                break;
    case 'docx':
                $mime_type = "application/msword";
                break;
    case 'gif':
                $mime_type = "image/gif";
                break;
    case 'jpg':
                $mime_type = "image/jpg";
                break;
    case 'jpeg':
                $mime_type = "image/jpeg";
                break;
    case 'png':
                $mime_type = "image/png";
                break;
    case 'tiff':
                $mime_type = "image/tiff";
                break;
    case 'txt':
                $mime_type = "text/plain";
                break;
    case 'csv':
                $mime_type = "text/csv";
                break;
    case 'zip':
                $mime_type = "application/zip";
                break;
    case 'pdf':
                $mime_type = "application/pdf";
                break;
    case 'xls': 
	            $mime_type = "application/vnd.ms-excel";
                break;
    case 'ppt': 
	            $mime_type = "application/vnd.ms-powerpoint";
                break;
    case 'wav': 
	            $mime_type = "audio/vnd.wave";
                break;
    case 'mp3': 
	            $mime_type = "audio/mpeg";
                break;					
    case 'mpeg': 
	            $mime_type = "video/mpeg";
                break; 
    case 'mp4': 
	            $mime_type = "video/mp4";
                break; 
    case 'ogg': 
	            $mime_type = "video/ogg";
                break; 
    case 'mov': 
	            $mime_type = "video/quicktime";
                break; 
    case 'webm': 
	            $mime_type = "video/webm";
                break; 
    case 'mkv': 
	            $mime_type = "video/x-matroska";
                break; 
    case 'wmv': 
	            $mime_type = "video/x-ms-wmv";
                break; 
    case 'flv': 
	            $mime_type = "video/x-flv";
                break; 
    default: 
                $mime_type = "application/force-download";
                break;
  }

  header('Content-Description: File Transfer');
  header('Content-Type: '.$mime_type);
  header('Content-Disposition: attachment;filename="'.urlencode($download_name).'"');
  header('Content-Transfer-Encoding: binary');
  header('Expires: 0');
  header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
  header('Pragma: public');
  header('Content-Length: '.filesize($file_name));
  
  @ob_clean();
  flush();
  readfile($file_name);
  exit;
}

function sec2hms($sec, $padHours = false) {

    // start with a blank string
    $hms = "";
    
    // do the hours first: there are 3600 seconds in an hour, so if we divide
    // the total number of seconds by 3600 and throw away the remainder, we're
    // left with the number of hours in those seconds
    $hours = intval(intval($sec) / 3600); 

    // add hours to $hms (with a leading 0 if asked for)
    $hms .= ($padHours) 
          ? str_pad($hours, 2, "0", STR_PAD_LEFT). ":"
          : $hours. ":";
    
    // dividing the total seconds by 60 will give us the number of minutes
    // in total, but we're interested in *minutes past the hour* and to get
    // this, we have to divide by 60 again and then use the remainder
    $minutes = intval(($sec / 60) % 60); 

    // add minutes to $hms (with a leading 0 if needed)
    $hms .= str_pad($minutes, 2, "0", STR_PAD_LEFT). ":";

    // seconds past the minute are found by dividing the total number of seconds
    // by 60 and using the remainder
    $seconds = intval($sec % 60); 

    // add seconds to $hms (with a leading 0 if needed)
    $hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);

    // done!
    return $hms;
    
}

/**
 * Function to calculate date or time difference.
 * 
 * Function to calculate date or time difference. Returns an array or
 * false on error.
 *
 * @param        string                                 $start
 * @param        string                                 $end
 * @return       array
 */
function get_time_difference($start, $end) {
	
    $uts['start']  =  strtotime($start);
    $uts['end']    =  strtotime($end);
    if ($uts['start']!=false && $uts['end']!=false) {
        if ($uts['end'] >= $uts['start']) {
            $diff = $uts['end'] - $uts['start'];
			$days = intval((floor($diff/60/60/24)));
			$hours = intval((floor($diff/60/60)));
			$minutes = intval((floor($diff/60)));
            $seconds = intval($diff);            
            return(array('days'=>$days, 'hours'=>$hours, 'minutes'=>$minutes, 'seconds'=>$seconds));
        } else {
            return(false);
        }
    } else {
        return(false);
    }
    return(false);
}

// convert numerical error level code to string
function error_level_tostring($intval, $separator)
{
    $errorlevels = array(
        2047 => 'E_ALL',
        1024 => 'E_USER_NOTICE',
        512 => 'E_USER_WARNING',
        256 => 'E_USER_ERROR',
        128 => 'E_COMPILE_WARNING',
        64 => 'E_COMPILE_ERROR',
        32 => 'E_CORE_WARNING',
        16 => 'E_CORE_ERROR',
        8 => 'E_NOTICE',
        4 => 'E_PARSE',
        2 => 'E_WARNING',
        1 => 'E_ERROR');
    $result = '';
    foreach($errorlevels as $number => $name)
    {
        if (($intval & $number) == $number) {
            $result .= ($result != '' ? $separator : '').$name; }
    }
    return $result;
}
?>