<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// Functions to handle page selection and rendering.

function activePage() {
  $ret = array();
  $path = explode('?', $_SERVER['REQUEST_URI'])[0];
  //Route by the path within the site, when it is installed in a subdirectory
  $base = basePath();
  if ($base != "" && ($path == $base || strpos($path, $base."/") === 0)) {
    $path = substr($path, strlen($base));
  }
  $parts = explode('/', ($path == "") ? "/" : $path);
  switch ($parts[1]) {
    case "api":
      $ret["page_type"] = "api";
      $ret["active_page"] = (isset($parts[2])) ? $parts[2] : "";
      break;
    case "cv":
      $ret["page_type"] = "cv";
      $ret["active_page"] = (isset($parts[2])) ? $parts[2] : "";
      break;
    case "ping":
      $ret["page_type"] = "ping";
      $ret["active_page"] = "";
      break;
    case "update":
      //Shortcut for the database update page
      $ret["page_type"] = "admin";
      $ret["active_page"] = "update";
      $ret["active_subpage"] = null;
      $ret["active_subsubpage"] = null;
      break;
    case "user":
      $ret["page_type"] = "user";
      if (isset($parts[2])) {
        $ret["active_page"] = $parts[2];
      } else {
        $ret["active_page"] = "";
      }
      break;
    case "admin":
      $ret["page_type"] = "admin";
      $ret["active_page"] = (isset($parts[2])) ? $parts[2] : "";
      if (isset($parts[3])) {
        $ret["active_subpage"] = $parts[3];
        if (isset($parts[4])) {
          $ret["active_subsubpage"] = $parts[4];
        } else {
          $ret["active_subsubpage"] = null;
        }
      } else {
        $ret["active_subpage"] = null;
        $ret["active_subsubpage"] = null;
      }
      break;
    case "robots.txt":
    case "sitemap.xml":
    case "favicon.ico":
      //Files that crawlers and browsers ask for at the top of a site, which index.php makes
      $ret["page_type"] = $parts[1];
      break;
    case "settings":
      if (isset($parts[2]) && $parts[2] == "user.css" && file_exists("settings/user.css")) {
        header('Content-Type: text/css');
        readfile("settings/user.css");
        exit;
      }
      $ret["page_type"] = "home";
      break;
    default:
      if ($parts[1] == "") {
        $ret["page_type"] = "home";
      } else {
        //A term outside a vocabulary, whose URI is the site address followed by its shortname (or id)
        $ret["page_type"] = "term";
        $ret["active_page"] = $parts[1];
      }
  }
  return($ret);
}

//The first path segments that activePage() sends somewhere other than a term's page. Keep this in step with its cases.
function reservedRouteSegments() {
  return(array("api", "cv", "ping", "update", "user", "admin", "settings", "robots.txt", "sitemap.xml", "favicon.ico"));
}

//The RDF format asked for with ?format=: "jsonld" for format=jsonld, "turtle" for format=ttl, otherwise NULL
function formatParameter() {
  $formats = array("jsonld" => "jsonld", "ttl" => "turtle");
  if (isset($_GET["format"]) && is_string($_GET["format"]) && isset($formats[$_GET["format"]])) {
    return($formats[$_GET["format"]]);
  }
  return(null);
}

//The items of an Accept or Accept-Language header, in the order given, each as array(value, quality):
//the value in lower case, and the quality given with ;q=, or 1 if there is none
function headerPreferences($header) {
  $preferences = array();
  foreach (explode(",", (string)$header) as $item) {
    $parameters = explode(";", $item);
    $q = 1.0;
    foreach (array_slice($parameters, 1) as $parameter) {
      $pair = explode("=", $parameter, 2);
      if (strtolower(trim($pair[0])) == "q" && isset($pair[1])) {
        $q = (float)trim($pair[1]);
      }
    }
    $preferences[] = array(strtolower(trim($parameters[0])), $q);
  }
  return($preferences);
}

//The format a request asks for: "jsonld" or "turtle" if it gives ?format=jsonld or ?format=ttl, or its
//Accept header prefers JSON-LD or Turtle to HTML, and otherwise "html". Browsers, and clients that accept
//HTML as much as RDF, get HTML. JSON-LD is chosen when it is as acceptable as Turtle.
function requestedFormat() {
  if (formatParameter() !== null) {
    return(formatParameter());
  }
  if (empty($_SERVER["HTTP_ACCEPT"])) {
    return("html");
  }
  $formats = array("text/html" => "html", "application/xhtml+xml" => "html", "application/ld+json" => "jsonld", "text/turtle" => "turtle");
  $quality = array("html" => -1, "jsonld" => -1, "turtle" => -1);
  foreach (headerPreferences($_SERVER["HTTP_ACCEPT"]) as list($type, $q)) {
    if (isset($formats[$type])) {
      $quality[$formats[$type]] = max($quality[$formats[$type]], $q);
    }
  }
  //An RDF format has to be more acceptable than the format chosen so far, starting with HTML
  $best = "html";
  foreach (array("jsonld", "turtle") as $format) {
    if ($quality[$format] > 0 && $quality[$format] > $quality[$best]) {
      $best = $format;
    }
  }
  return($best);
}

//The address of the RDF describing the current page, in JSON-LD or with $format "turtle" in Turtle,
//or NULL if there is none
function linkedDataURL($format = "jsonld") {
  $page = $GLOBALS["ontomasticon"]["pageInfo"];
  $turtle = ($format == "turtle");
  switch ($page["page_type"]) {
    case "home":
      return(sitePath(($turtle) ? "/api/cv/?format=ttl" : "/api/cv/"));
    case "cv":
      if ($page["active_page"] == "") {
        return(null);
      }
      return(sitePath("/api/cv/?shortname=".rawurlencode($page["active_page"]).(($turtle) ? "&format=ttl" : "")));
    case "term":
      return(sitePath("/api/term/?term=").rawurlencode(siteURL().rawurldecode($page["active_page"]))."&format=".(($turtle) ? "ttl" : "jsonld"));
  }
  return(null);
}

//The term the current address is a page for, with its related terms loaded, or NULL if there is none
function currentPageTerm() {
  return(isset($GLOBALS["ontomasticon"]["pageTerm"]) ? $GLOBALS["ontomasticon"]["pageTerm"] : null);
}

//The terms of the home page (the site's terms that aren't in a vocabulary, which an address that should be a term's but
//isn't lists too) or of a vocabulary's page, including deprecated ones, with their related terms loaded. The page lists
//them and describes them in its head. None for other pages.
function currentPageTerms() {
  return(isset($GLOBALS["ontomasticon"]["pageTerms"]) ? $GLOBALS["ontomasticon"]["pageTerms"] : array());
}

//The controlled vocabulary the current address is a page for, as a row of the cv table, or NULL if there is none
function currentPageVocabulary() {
  $page = $GLOBALS["ontomasticon"]["pageInfo"];
  if ($page["page_type"] != "cv" || $page["active_page"] == "" || !isset($GLOBALS["ontomasticon"]["CVs"][$page["active_page"]])) {
    return(null);
  }
  return($GLOBALS["ontomasticon"]["CVs"][$page["active_page"]]);
}

//Whether the current address should be a term's or a vocabulary's, but no term or vocabulary has it
function pageNotFound() {
  $page = $GLOBALS["ontomasticon"]["pageInfo"];
  return(($page["page_type"] == "term" && currentPageTerm() === null)
    || ($page["page_type"] == "cv" && $page["active_page"] != "" && currentPageVocabulary() === null));
}

//Whether the current page is the results of a search: the home page with ?q=
function searchPage() {
  return($GLOBALS["ontomasticon"]["pageInfo"]["page_type"] == "home" && searchQuery() !== "");
}

//The page's title: the search, or the name of the term or vocabulary the page is for, if any, then the site's name
function pageTitle() {
  $site = tu("site_name");
  if (searchPage()) {
    return(t("Search results for")." “".searchQuery()."” – ".$site);
  }
  $term = currentPageTerm();
  $vocabulary = currentPageVocabulary();
  if ($term !== null && $term->name != "") {
    return($term->name." – ".$site);
  }
  if ($vocabulary !== null && $vocabulary["name"] != "") {
    return($vocabulary["name"]." – ".$site);
  }
  return($site);
}

//The page's description for search engines, as plain text: the definition of the term, or the description of the
//vocabulary, it is for, or otherwise the site's description
function pageDescription() {
  $text = "";
  if (currentPageTerm() !== null) {
    $text = plainText(currentPageTerm()->description);
  } elseif (currentPageVocabulary() !== null) {
    $text = plainText(currentPageVocabulary()["description"]);
  }
  if ($text == "") {
    $text = plainText(tu("description"));
  }
  return(shortText($text, 300));
}

//The address search engines should use for the current page: a term's or vocabulary's URI, or the site's address for the
//home page. NULL for other pages, including addresses that aren't found.
function canonicalURL() {
  $page = $GLOBALS["ontomasticon"]["pageInfo"];
  if ($page["page_type"] == "home") {
    return(siteURL());
  }
  if (currentPageTerm() !== null) {
    return(currentPageTerm()->uri());
  }
  if (currentPageVocabulary() !== null) {
    return((new Vocabulary(currentPageVocabulary()["shortname"]))->uri());
  }
  return(null);
}

//The schema.org data for the current page, as an array ready for schemaOrgScript(): the site's terms as a set on the
//home page, a term on its page, and a vocabulary with its terms on its page. NULL for other pages, including addresses that aren't found.
function pageStructuredData() {
  $page = $GLOBALS["ontomasticon"]["pageInfo"];
  if ($page["page_type"] == "home") {
    return(schemaOrgTermSetJSONLD(Vocabulary::site(), currentPageTerms()));
  }
  if (currentPageTerm() !== null) {
    $term = currentPageTerm();
    $vocabulary = ($term->cv == null) ? Vocabulary::site() : Vocabulary::find($term->cv, $GLOBALS["ontomasticon"]["CVs"]);
    return(schemaOrgTermJSONLD($term, ($vocabulary === null) ? $term->vocabulary() : $vocabulary));
  }
  if (currentPageVocabulary() !== null) {
    return(schemaOrgTermSetJSONLD(Vocabulary::fromRow(currentPageVocabulary()), currentPageTerms()));
  }
  return(null);
}

function printFooter() {
  $out  = "<p>".t("Powered by")." ";
  $out .= l("Ontomasticon", "https://ontomasticon.github.io")." ";
  $out .= t("version")." ".$GLOBALS["ontomasticon"]["config"]["version"];
  $out .= "</p>";
  print($out);
}

function printCitation() {
  $out  = h($GLOBALS["ontomasticon"]["config"]["author"]);
  $out .= " (".date("Y").") ";
  $out .= h(tu("site_name"))." ";
  $out .= "(".h(siteURL())."). ";
  $out .= t("Accessed on")." ".date("F j, Y, g:i a").".";
  print($out);
}

function logInOut() {
  if (isset($_SESSION["user"])) {
    return(l("Logout", "/user/login"));
  } else {
    return(l("Login", "/user/login"));
  }
}

function adminLink() {
  if (userAllow("administer")) {
    return (l("Administration", "/admin/config"));
  } elseif (userAllow("edit-terms")) {
    return (l("Administration", "/admin/term/add"));
  }
}

function userLink() {
  if (isset($_SESSION["user"])) {
    return (l("User", "/user"));
  }
}

function termEditLink($sn) {
  if (userAllow("edit-terms")) {
    $ret = "[".l("edit", "/admin/term/edit/".$sn)."]";
    return($ret);
  }
}

function cvEditLink($sn) {
  if (userAllow("edit-cvs")) {
    $ret = "[".l("edit", "/admin/cv/edit/".$sn)."]";
    return($ret);
  }
}
