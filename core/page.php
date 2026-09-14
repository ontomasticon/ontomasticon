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
      $ret["active_page"] = $parts[2];
      break;
    case "ping":
      $ret["page_type"] = "ping";
      $ret["active_page"] = "";
      break;
    case "update":
      $ret["page_type"] = "update";
      $ret["active_page"] = "";
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
      $ret["active_page"] = $parts[2];
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
      if ($parts[2]== "user.css") {
        header('Content-Type: text/css');
        readfile("settings/user.css");
        exit;
      }
      break;
    default:
      $ret["page_type"] = "home";
  }
  return($ret);
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
  $out .= "(https://".h($GLOBALS["ontomasticon"]["config"]["base_url"])."). ";
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
