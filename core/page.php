<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// Functions to handle page selection and rendering.

function activePage() {
  $ret = array();
  $parts = explode('/', explode('?',$_SERVER['REQUEST_URI'])[0]);
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

//The format a request asks for: "jsonld" if it gives ?format=jsonld, or its Accept header
//prefers JSON-LD to HTML, and otherwise "html". Browsers, and clients that accept both equally, get HTML.
function requestedFormat() {
  if (isset($_GET["format"]) && $_GET["format"] == "jsonld") {
    return("jsonld");
  }
  if (empty($_SERVER["HTTP_ACCEPT"])) {
    return("html");
  }
  $formats = array("text/html" => "html", "application/xhtml+xml" => "html", "application/ld+json" => "jsonld");
  $quality = array("html" => -1, "jsonld" => -1);
  foreach (explode(",", $_SERVER["HTTP_ACCEPT"]) as $range) {
    $parameters = explode(";", $range);
    $type = strtolower(trim($parameters[0]));
    $q = 1.0;
    foreach (array_slice($parameters, 1) as $parameter) {
      $pair = explode("=", $parameter, 2);
      if (strtolower(trim($pair[0])) == "q" && isset($pair[1])) {
        $q = (float)trim($pair[1]);
      }
    }
    if (isset($formats[$type])) {
      $quality[$formats[$type]] = max($quality[$formats[$type]], $q);
    }
  }
  return(($quality["jsonld"] > 0 && $quality["jsonld"] > $quality["html"]) ? "jsonld" : "html");
}

//The address of the JSON-LD describing the current page, or NULL if there is none
function linkedDataURL() {
  $page = $GLOBALS["ontomasticon"]["pageInfo"];
  switch ($page["page_type"]) {
    case "home":
      return("/api/cv/");
    case "cv":
      return(($page["active_page"] == "") ? null : "/api/cv/?shortname=".rawurlencode($page["active_page"]));
    case "term":
      return("/api/term/?term=".rawurlencode(siteURL().rawurldecode($page["active_page"]))."&format=jsonld");
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
