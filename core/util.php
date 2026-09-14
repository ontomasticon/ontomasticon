<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// Miscellanous utility functions

//Escape a value for output in HTML text or attributes
function h($s) {
  return(htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'));
}

//Text from stored HTML, for formats that can't contain HTML: tags are removed, entities
//decoded, and runs of whitespace (including between paragraphs) become single spaces
function plainText($html) {
  $text = preg_replace('#<(br|/?(p|div|li|ul|ol|h[1-6]|tr|td|th|table|blockquote))\b[^>]*>#i', ' ', (string)$html);
  $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, "UTF-8");
  return(trim(preg_replace('/\s+/', ' ', $text)));
}

//A value as JSON. Bytes that aren't valid UTF-8 are replaced, because json_encode()
//would otherwise return FALSE and the response would be empty.
function toJSON($value, $flags = 0) {
  return(json_encode($value, $flags | JSON_INVALID_UTF8_SUBSTITUTE));
}

//Text with any bytes that aren't valid UTF-8 replaced by the replacement character, U+FFFD
function validUTF8($text) {
  return(htmlspecialchars_decode(htmlspecialchars((string)$text, ENT_NOQUOTES | ENT_SUBSTITUTE, "UTF-8"), ENT_NOQUOTES));
}

//Escaped address for a form that posts back to the current page. Uses the address
//the visitor requested, as PHP_SELF is /index.php under most rewrite configurations.
function formAction() {
  return(h($_SERVER['REQUEST_URI']));
}

//Run a parameterised query. Returns a mysqli_result for queries that
//return rows, TRUE for other successful queries and FALSE on failure.
function dbQuery($sql, $params = array()) {
  global $db;
  $stmt = $db->prepare($sql);
  if (!$stmt) {
    $GLOBALS["ontomasticon"]["db_error"] = $db->error;
    return(FALSE);
  }
  if (count($params) > 0) {
    $stmt->bind_param(str_repeat("s", count($params)), ...$params);
  }
  if (!$stmt->execute()) {
    $GLOBALS["ontomasticon"]["db_error"] = $stmt->error;
    return(FALSE);
  }
  $result = $stmt->get_result();
  return(($result === FALSE) ? TRUE : $result);
}

//The error from the last dbQuery() that failed
function dbError() {
  return(isset($GLOBALS["ontomasticon"]["db_error"]) ? $GLOBALS["ontomasticon"]["db_error"] : "");
}

//Show an error on the page. The text is escaped.
function printError($text) {
  print "<div class='error'><p>".h($text)."</p></div>";
}

//Show a confirmation on the page. The text is escaped.
function printStatus($text) {
  print "<p class='status'>".h($text)."</p>";
}

//Report whether a database write succeeded, and return the result
function reportSaved($result, $success = "Saved.") {
  if ($result) {
    printStatus(t($success));
    return(TRUE);
  }
  printError(t("Could not save changes").": ".dbError());
  return(FALSE);
}

//The site's address, with a trailing slash. The base_url setting may start with
//http:// or https://; if it has neither, https:// is assumed.
function siteURL() {
  $base = $GLOBALS["ontomasticon"]["config"]["base_url"];
  if (preg_match('#^https?://#i', $base)) {
    return($base);
  }
  return("https://".$base);
}

//Check whether an IP address is in a list of addresses and CIDR ranges (IPv4 or IPv6)
function ipInRanges($ip, $ranges) {
  $address = @inet_pton($ip);
  if ($address === FALSE) {
    return(FALSE);
  }
  foreach ($ranges as $range) {
    $parts = explode("/", $range);
    $subnet = @inet_pton($parts[0]);
    if ($subnet === FALSE || strlen($subnet) != strlen($address)) {
      continue;
    }
    $bits = isset($parts[1]) ? intval($parts[1]) : strlen($address) * 8;
    //Compare the whole bytes of the prefix, then any remaining bits
    $bytes = intdiv($bits, 8);
    if (substr($address, 0, $bytes) !== substr($subnet, 0, $bytes)) {
      continue;
    }
    $remainder = $bits % 8;
    if ($remainder == 0) {
      return(TRUE);
    }
    $mask = (0xFF << (8 - $remainder)) & 0xFF;
    if ((ord($address[$bytes]) & $mask) == (ord($subnet[$bytes]) & $mask)) {
      return(TRUE);
    }
  }
  return(FALSE);
}

//Whether the request came from a CDN or reverse proxy listed in $trusted_proxies (settings/db.php)
function fromTrustedProxy() {
  global $trusted_proxies;
  return(!empty($trusted_proxies) && ipInRanges($_SERVER["REMOTE_ADDR"], $trusted_proxies));
}

//The visitor's IP address. Behind a trusted proxy this comes from X-Forwarded-For,
//which is ignored otherwise because anyone can set it.
function clientIP() {
  global $trusted_proxies;
  $ip = $_SERVER["REMOTE_ADDR"];
  if (!fromTrustedProxy() || empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
    return($ip);
  }
  //Each proxy appends the address it received the request from, so work back
  //from the end until reaching an address that isn't a trusted proxy
  $hops = array_reverse(array_map("trim", explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"])));
  foreach ($hops as $hop) {
    if (filter_var($hop, FILTER_VALIDATE_IP) === FALSE) {
      break;
    }
    $ip = $hop;
    if (!ipInRanges($hop, $trusted_proxies)) {
      break;
    }
  }
  return($ip);
}

//Whether the visitor connected over HTTPS, including via a trusted proxy
function requestIsHttps() {
  if (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] != "off") {
    return(TRUE);
  }
  return(fromTrustedProxy() && isset($_SERVER["HTTP_X_FORWARDED_PROTO"])
    && strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"]) == "https");
}

//Hyperlinking function
function l($text, $url) {
  if (substr($url, 0, 1) == '/') {
    if (isset($_GET["lang"])) {
      $url .= "?lang=".urlencode(detectLanguage());
    }
  }

  $ret  = "<a href='".h($url)."'>";
  $ret .= h(t($text));
  $ret .= "</a>";

  return($ret);
}

//Templating function
function template($t) {
  if (file_exists("settings/".$t)) {
    include("settings/".$t);
  } else {
    include("templates/".$t);
  }
}

//Odd-even striping helper
function oe($n) {
  if ($n == 1)  { return("odd");}
  if ($n == -1) { return("even");}
  return("neither");
}

//Check for updates
function checkUpdate() {
  $td = time() - intval($GLOBALS["ontomasticon"]["config"]["update_check"]);
  if ($td < 86400) {
    return;
  }
  global $db;
  $url = "https://raw.githubusercontent.com/ontomasticon/ontomasticon/master/index.php";
  //Short timeout, as this runs while an admin page is loading
  $context = stream_context_create(array("http" => array("timeout" => 5)));
  $h = @fopen($url, "r", FALSE, $context);

  //Record the attempt even if it fails, so it isn't retried on every page load
  $sql = "UPDATE config SET value = UNIX_TIMESTAMP() WHERE `key` = 'update_check';";
  $db->query($sql);

  if ($h) {
    $sql = "UPDATE config SET value = 1 WHERE `key` = 'update_check_ok';";
    $db->query($sql);
    while (($line = fgets($h)) !== FALSE) {
      if (strpos($line, '$version') === 0) {
        //Accepts both $version = 0.3; and $version = "0.3";
        $start = strpos($line, '=') + 1;
        $nv = trim(substr($line, $start, strpos($line, ';') - $start), " \t\"'");
        if (version_compare($nv, (string)$GLOBALS["ontomasticon"]["config"]["version"], ">")) {
          $ua = 1;
        } else {
          $ua = 0;
        }
        $sql = "UPDATE config SET value = $ua WHERE `key` = 'update_available';";
        $db->query($sql);
      }
    }
    fclose($h);
  } else {
    $sql = "UPDATE config SET value = 0 WHERE `key` = 'update_check_ok';";
    $db->query($sql);
  }
}

function bool2check($bool) {
  if ($bool == 1) {
    return "checked";
  } else {
    return "";
  }
}

function val2check($i, $c) {
  if ($i == $c) {
    return "checked";
  } else {
    return "";
  }
}

//System sanity check for admin user
function adminSanity() {
  checkUpdate();
  $ret = NULL;
  global $db;
  $config = $GLOBALS["ontomasticon"]["config"];

  if (is_dir("inst")) {
    $ret["Installer"] = "For security please delete the inst directory.";
  }

  //Not shown on the update page itself, which reports the result of updating
  $pageInfo = $GLOBALS["ontomasticon"]["pageInfo"];
  if (!($pageInfo["page_type"] == "admin" && $pageInfo["active_page"] == "update")) {
    $versionOrder = version_compare((string)$config["version_db"], (string)$config["version"]);
    if ($versionOrder < 0) {
      $ret["Database update"] = "You need to run the ".l("database update script", "/admin/update").".";
    }
    if ($versionOrder > 0) {
      $ret["Database update"] = "The database is running a more recent version than the code base. Please upgrade.";
    }
  }

  $sql = 'SELECT password FROM users WHERE id = 1;';
  $rs = $db->query($sql);
  $numrows = mysqli_num_rows($rs);
  if ($numrows == 1) {
    $pw = mysqli_fetch_assoc($rs);
    //Hashes are upgraded on login, so check the password rather than the installed hash
    if (password_verify("password", $pw["password"])) {
      $ret["Admin Password"] = "Admin password is still default value.";
    }
  }

  if ($config["update_available"] == 1) {
    $ret["Update Available"] = "A new version is available.";
  }
  return($ret);
}
