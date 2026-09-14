<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// Miscellanous utility functions

//Escape a value for output in HTML text or attributes
function h($s) {
  return(htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'));
}

//Run a parameterised query. Returns a mysqli_result for queries that
//return rows, TRUE for other successful queries and FALSE on failure.
function dbQuery($sql, $params = array()) {
  global $db;
  $stmt = $db->prepare($sql);
  if (!$stmt) {
    return(FALSE);
  }
  if (count($params) > 0) {
    $stmt->bind_param(str_repeat("s", count($params)), ...$params);
  }
  if (!$stmt->execute()) {
    return(FALSE);
  }
  $result = $stmt->get_result();
  return(($result === FALSE) ? TRUE : $result);
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
  $h = fopen($url, "r");

  if ($h) {
    $sql = "UPDATE config SET value = UNIX_TIMESTAMP() WHERE `key` = 'update_check';";
    $db->query($sql);
    $sql = "UPDATE config SET value = 1 WHERE `key` = 'update_check_ok';";
    $db->query($sql);
    while (($line = fgets($h)) !== FALSE) {
      if (strpos($line, '$version') === 0) {
        $nv = substr($line, 11, strpos($line, ';')-11);
        $vd = floatval($nv) - floatval($GLOBALS["ontomasticon"]["config"]["version"]);
        if ($vd > 0) {
          $ua = 1;
        } else {
          $ua = 0;
        }
        $sql = "UPDATE config SET value = $ua WHERE `key` = 'update_available';";
        $db->query($sql);
      }
    }
  } else {
    $sql = "UPDATE config SET value = 0 WHERE `key` = 'update_check_ok';";
    $db->query($sql);
  }
  fclose($h);
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
  $sql = 'SELECT password FROM users WHERE id = 1;';
  $rs = $db->query($sql);
  $numrows = mysqli_num_rows($rs);
  if ($numrows == 1) {
    $pw = mysqli_fetch_assoc($rs);
    if ($pw["password"] == '$2y$04$oEaHJ.52kzQbFtQzC1zRdOuAkPc5J9il37vqMJofJvZMGqJtaMovW') {
      $ret["Admin Password"] = "Admin password is still default value.";
    }
  }

  if ($GLOBALS["ontomasticon"]["config"]["update_available"] == 1) {
    $ret["Update Available"] = "A new version is available.";
  }
  return($ret);
}
