<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval

//Codebase version
$version = 0.2;

//Check database has been configured
if (file_exists("settings/db.php")) {
  include("settings/db.php");
} else {
  print("<p>settings/db.php does not exist!</p>");
  print("<p>Refer to <a href='https://ontomasticon.github.io/installation.html'>Installation instructions.</a></p>");
  exit;
}

// Load core functions
require("core/core.php");

// Start the session before any output is sent
session_start(array(
  "cookie_httponly" => TRUE,
  "cookie_samesite" => "Lax",
  "cookie_secure" => (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] != "off"),
  "use_strict_mode" => TRUE
));

// Ignore form submissions that don't carry this session's CSRF token
$GLOBALS["ontomasticon"]["csrf_failed"] = FALSE;
if ($_SERVER["REQUEST_METHOD"] == "POST" && !csrfValid()) {
  $_POST = array();
  $GLOBALS["ontomasticon"]["csrf_failed"] = TRUE;
}

$GLOBALS["ontomasticon"]["pageInfo"] = activePage();

// Log in or out before any output, as both change the session id
if ($GLOBALS["ontomasticon"]["pageInfo"]["page_type"] == "user" && $GLOBALS["ontomasticon"]["pageInfo"]["active_page"] == "login") {
  if (isset($_POST['submit'])) {
    $GLOBALS["ontomasticon"]["login_message"] = login();
  }
  if (isset($_POST['logout'])) {
    $GLOBALS["ontomasticon"]["login_message"] = logout();
  }
}

// Load configuration
$GLOBALS["ontomasticon"]["config"] = getConfig($db);
$GLOBALS["ontomasticon"]["language"] = detectLanguage();
$GLOBALS["ontomasticon"]["cv_count"] = CVcount($db);
$GLOBALS["ontomasticon"]["CVs"] = getCVs($db);

// Load correct page template
switch($GLOBALS["ontomasticon"]["pageInfo"]["page_type"]) {
  case "api":
    template("api.php");
    break;
  case "ping":
    print "pong";
    break;
  default:
    template("core.php");
}
