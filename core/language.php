<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// Functions to handle language selection and translation.

//Whether a value is a plain language code, such as fr or pt-BR. Only these are used, as the code is used in file paths.
function validLanguageCode($code) {
  return(is_string($code) && preg_match('/^[A-Za-z0-9_-]+$/D', $code) === 1);
}

//The languages the site is offered in: its default language, then those in the languages setting
function siteLanguages() {
  $languages = array_merge(array(configValue("default_lang")), preg_split('/[\s,]+/', configValue("languages"), -1, PREG_SPLIT_NO_EMPTY));
  return(array_values(array_unique(array_filter($languages, "validLanguageCode"))));
}

//The language to show the page in: one chosen with ?lang=, or chosen earlier in the visit, or else
//the one of the site's languages the browser prefers, or else the site's default language
function detectLanguage(){
  if (isset($_GET["lang"]) && validLanguageCode($_GET["lang"])) {
    return($_GET["lang"]);
  }
  if (isset($_SESSION["lang"]) && in_array($_SESSION["lang"], siteLanguages(), TRUE)) {
    return($_SESSION["lang"]);
  }
  $browserLanguage = browserLanguage();
  return(($browserLanguage !== null) ? $browserLanguage : configValue("default_lang"));
}

//The language of the current page, as chosen when the request started, or by detectLanguage() before then
function currentLanguage() {
  return(isset($GLOBALS["ontomasticon"]["language"]) ? $GLOBALS["ontomasticon"]["language"] : detectLanguage());
}

//Keep a language chosen with ?lang= for the rest of the visit, if it is one the site is offered in
function rememberLanguage() {
  if (isset($_GET["lang"]) && in_array($_GET["lang"], siteLanguages(), TRUE)) {
    $_SESSION["lang"] = $_GET["lang"];
  }
}

//The site language the browser's Accept-Language header prefers, or NULL if it accepts none of them.
//A language with a region, such as en-GB, also matches the language without one, and the other way round.
function browserLanguage() {
  if (empty($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
    return(null);
  }
  $ranges = array();
  foreach (headerPreferences($_SERVER["HTTP_ACCEPT_LANGUAGE"]) as $position => $preference) {
    if ($preference[0] != "" && $preference[0] != "*" && $preference[1] > 0) {
      $ranges[] = array($preference[0], $preference[1], $position);
    }
  }
  //Most preferred first, keeping the order they were given in when equally preferred
  usort($ranges, function($a, $b) {
    return(($a[1] == $b[1]) ? $a[2] - $b[2] : (($a[1] < $b[1]) ? 1 : -1));
  });
  $languages = siteLanguages();
  foreach ($ranges as $range) {
    foreach ($languages as $language) {
      if (strtolower($language) == $range[0]) {
        return($language);
      }
    }
    $primary = preg_split('/[-_]/', $range[0])[0];
    foreach ($languages as $language) {
      if (strtolower(preg_split('/[-_]/', $language)[0]) == $primary) {
        return($language);
      }
    }
  }
  return(null);
}

//Links for switching the page to each of the site's languages, or nothing if it only has one
function languageSwitcher() {
  $languages = siteLanguages();
  if (count($languages) < 2) {
    return("");
  }
  $path = explode("?", $_SERVER["REQUEST_URI"])[0];
  $links = array();
  foreach ($languages as $language) {
    if ($language == currentLanguage()) {
      $links[] = "<strong lang='".h($language)."'>".h($language)."</strong>";
    } else {
      $address = $path."?".http_build_query(array_merge($_GET, array("lang" => $language)));
      $links[] = "<a href='".h($address)."' hreflang='".h($language)."' lang='".h($language)."'>".h($language)."</a>";
    }
  }
  return("<div id='languages'>".h(t("Language")).": ".implode(" | ", $links)."</div>");
}

//Translate interface items
function t($text) {
  $lang = currentLanguage();
  if ($lang == configValue("default_lang")) {
    return $text;
  } elseif (file_exists("lang/".$lang.".php")) {
    if (!isset($GLOBALS["ontomasticon"]["language_data"])) {
      include("lang/".$lang.".php");
      //Function names can't contain hyphens, so lang/pt-BR.php defines lang_pt_BR()
      $GLOBALS["ontomasticon"]["language_data"] = call_user_func("lang_".str_replace("-", "_", $lang));
    }
    if (isset($GLOBALS["ontomasticon"]["language_data"][$text])) {
      return($GLOBALS["ontomasticon"]["language_data"][$text]);
    } else {
      return($text);
    }
  } else {
    //No translation file for this language
    return($text);
  }
}

//Translate user-provided content (config variables)
function tu($type) {
  $lang = currentLanguage();
  if ($lang == configValue("default_lang")) {
    return(configValue($type));
  } elseif (isset($GLOBALS["ontomasticon"]["config"][$type."_".$lang])) {
    return($GLOBALS["ontomasticon"]["config"][$type."_".$lang]);
  } else {
    return(configValue($type));
  }
}
