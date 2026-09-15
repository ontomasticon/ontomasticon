<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval

//Codebase version. Quoted, as versions such as 0.4.2 aren't numbers; installs before 0.3 can only read an
//unquoted number here when checking for updates, so they won't be told about this version.
$version = "0.4.3";

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

if (!validTablePrefix(tablePrefix())) {
  print("<p>The table prefix in settings/db.php may only use letters A to Z, digits and underscores.</p>");
  exit;
}

// Load configuration. Its base_url gives the path the site is installed at, which routing and the session cookie need.
$GLOBALS["ontomasticon"]["config"] = getConfig($db);

$GLOBALS["ontomasticon"]["pageInfo"] = activePage();

// Start a session only for the visitors who need one (see sessionNeeded()). PHP tells browsers not to keep pages that
// use a session; other pages may be kept for a few minutes, but not once the visitor's cookies change, as on logging in.
if (sessionNeeded($GLOBALS["ontomasticon"]["pageInfo"])) {
  startSession();
} elseif (in_array($_SERVER["REQUEST_METHOD"], array("GET", "HEAD"), TRUE)) {
  header("Cache-Control: public, max-age=".PUBLIC_CACHE_SECONDS);
  header("Vary: Cookie", FALSE);
}

// Ignore form submissions that don't carry this session's CSRF token
$GLOBALS["ontomasticon"]["csrf_failed"] = FALSE;
if ($_SERVER["REQUEST_METHOD"] == "POST" && !csrfValid()) {
  $_POST = array();
  $GLOBALS["ontomasticon"]["csrf_failed"] = TRUE;
}

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

rememberLanguage();
$GLOBALS["ontomasticon"]["language"] = detectLanguage();
$GLOBALS["ontomasticon"]["cv_count"] = CVcount($db);
$GLOBALS["ontomasticon"]["CVs"] = getCVs($db);

// The site's own addresses also identify its vocabularies and terms. Clients that ask for
// JSON-LD or Turtle (see requestedFormat()) get that there instead of the HTML page.
if (in_array($GLOBALS["ontomasticon"]["pageInfo"]["page_type"], array("home", "cv", "term"))) {
  header("Vary: Accept", FALSE);
  if (requestedFormat() != "html") {
    template("linked-data.php");
    exit;
  }
}

// A term outside a vocabulary has a page at its URI. An address that should be a term's or a vocabulary's, but isn't,
// is not found, though its page still shows the site's terms or vocabularies, as an old link may be meant for one.
if ($GLOBALS["ontomasticon"]["pageInfo"]["page_type"] == "term") {
  $pageTerm = Term::findByURI(siteURL().rawurldecode($GLOBALS["ontomasticon"]["pageInfo"]["active_page"]));
  $GLOBALS["ontomasticon"]["pageTerm"] = ($pageTerm == null) ? null : getTermForPage($pageTerm->id);
}
if (pageNotFound()) {
  http_response_code(404);
}

// Load correct page template
switch($GLOBALS["ontomasticon"]["pageInfo"]["page_type"]) {
  case "api":
    template("api.php");
    break;
  case "ping":
    print "pong";
    break;
  case "robots.txt":
    template("robots.php");
    break;
  case "sitemap.xml":
    template("sitemap.php");
    break;
  case "favicon.ico":
    //Browsers ask for this whether or not a page names an icon
    header("Content-Type: image/png");
    readfile("images/ontomasticon.png");
    break;
  default:
    //Pages are shown in the language the browser prefers, unless one has been chosen
    header("Vary: Accept-Language", FALSE);
    template("core.php");
}
