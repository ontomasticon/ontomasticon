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
    if ((float)$config["version_db"] < (float)$config["version"]) {
      $ret["Database update"] = "You need to run the ".l("database update script", "/admin/update").".";
    }
    if ((float)$config["version_db"] > (float)$config["version"]) {
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
