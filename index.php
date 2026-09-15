<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval

//Codebase version. Installs before 0.3 can only read an unquoted number here when checking for updates.
$version = 0.4;

//Query results are checked where they are used, so stop mysqli throwing exceptions (the default from PHP 8.1)
mysqli_report(MYSQLI_REPORT_OFF);

//Check database has been configured
if (file_exists("settings/db.php")) {
  include("settings/db.php");
} else {
  print("<p>settings/db.php does not exist!</p>");
  print("<p>Refer to <a href='https://ontomasticon.github.io/installation.html'>Installation instructions.</a></p>");
  exit;
}

if ($db->connect_error) {
  print("<p>Could not connect to the database.</p>");
  exit;
}

//Exchange text with the database as UTF-8, whatever the server's default character set.
//Otherwise characters such as curly quotes can come back as invalid UTF-8.
$db->set_charset("utf8mb4");

// Load core functions
require("core/core.php");

// Load configuration. Its base_url gives the path the site is installed at, which routing and the session cookie need.
$GLOBALS["ontomasticon"]["config"] = getConfig($db);

// Start the session before any output is sent. The cookie is limited to the site's own path,
// so sites installed in different subdirectories of one domain don't share a login.
session_start(array(
  "cookie_httponly" => TRUE,
  "cookie_samesite" => "Lax",
  "cookie_secure" => requestIsHttps(),
  "cookie_path" => basePath()."/",
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

// Accounts still using the default password may only change it or log out
if (isset($_SESSION["user"]) && !empty($_SESSION["must_change_password"])) {
  $page = $GLOBALS["ontomasticon"]["pageInfo"];
  $allowed = in_array($page["page_type"], array("api", "ping"))
    || ($page["page_type"] == "user" && $page["active_page"] == "settings")
    || ($page["page_type"] == "user" && $page["active_page"] == "login" && !isset($_POST['submit']));
  if (!$allowed) {
    header("Location: ".sitePath("/user/settings"));
    exit;
  }
}

$GLOBALS["ontomasticon"]["language"] = detectLanguage();
$GLOBALS["ontomasticon"]["cv_count"] = CVcount($db);
$GLOBALS["ontomasticon"]["CVs"] = getCVs($db);

// The site's own addresses also identify its vocabularies and terms. Clients that ask for
// JSON-LD or Turtle (see requestedFormat()) get that there instead of the HTML page.
if (in_array($GLOBALS["ontomasticon"]["pageInfo"]["page_type"], array("home", "cv", "term"))) {
  header("Vary: Accept");
  if (requestedFormat() != "html") {
    template("linked-data.php");
    exit;
  }
}

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
