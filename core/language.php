<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// Functions to handle language selection and translation.

function detectLanguage(){
  $lang = $GLOBALS["ontomasticon"]["config"]["default_lang"];

  //Only accept plain language codes, as the value is used in file paths
  if (isset($_GET["lang"]) && preg_match('/^[A-Za-z0-9_-]+$/', $_GET["lang"])) {
    $lang = $_GET["lang"];
  }
  return($lang);
}

//Translate interface items
function t($text) {
  $lang = detectLanguage();
  if ($lang == $GLOBALS["ontomasticon"]["config"]["default_lang"]) {
    return $text;
  } elseif (file_exists("lang/".$lang.".php")) {
    if (!isset($GLOBALS["ontomasticon"]["language_data"])) {
      include("lang/".$lang.".php");
      $GLOBALS["ontomasticon"]["language_data"] = call_user_func("lang_".$lang);
    }
    if (isset($GLOBALS["ontomasticon"]["language_data"][$text])) {
      return($GLOBALS["ontomasticon"]["language_data"][$text]);
    } else {
      return($text);
    }
  }
}

//Translate user-provided content (config variables)
function tu($type) {
  $lang = detectLanguage();
  if ($lang == $GLOBALS["ontomasticon"]["config"]["default_lang"]) {
    return($GLOBALS["ontomasticon"]["config"][$type]);
  } elseif (isset($GLOBALS["ontomasticon"]["config"][$type."_".$lang])) {
    return($GLOBALS["ontomasticon"]["config"][$type."_".$lang]);
  } else {
    return($GLOBALS["ontomasticon"]["config"][$type]);
  }
}
