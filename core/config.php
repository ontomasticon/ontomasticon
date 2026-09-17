<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// Functions to handle saving, retrieving and checking configuration variables.

//Settings administrators can change on the configuration page
function editableConfigKeys() {
  return(array("site_name", "author", "publisher", "default_lang", "languages", "base_url", "description", "glossary_display", "license", "prefix", "mcp_server", "mcp_guidance"));
}

//A configuration setting, or an empty string if it isn't set (for example before the database update that adds it)
function configValue($key) {
  return(isset($GLOBALS["ontomasticon"]["config"][$key]) ? (string)$GLOBALS["ontomasticon"]["config"][$key] : "");
}

//The error for a namespace prefix that can't be used in RDF, or NULL if it can. An empty prefix means there is none.
function prefixError($prefix) {
  if ($prefix == "" || preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,19}$/D', $prefix) === 1) {
    return(null);
  }
  return(t("Not saved. A namespace prefix must start with a letter, use only letters, digits, hyphens and underscores, and be at most 20 characters long."));
}

/**
 * Saves a user configuration submitted via $_POST to the database and
 * sets the global config variable to match. Nothing is saved if a setting isn't valid.
 *
 * @return Boolean Whether the configuration was saved
 */
function saveConfig() {
  global $db;
  $vals = array();
  foreach (editableConfigKeys() as $key) {
    $vals[$key] = isset($_POST[$key]) ? trim($_POST[$key]) : "";
  }
  if ($vals["license"] != "" && preg_match('#^https?://\S+$#iD', $vals["license"]) !== 1) {
    printError(t("Not saved. The license must be a web address starting with http:// or https://"));
    return(FALSE);
  }
  if (prefixError($vals["prefix"]) !== null) {
    printError(prefixError($vals["prefix"]));
    return(FALSE);
  }
  //Browsers send the lines of a text box separated by \r\n, which are saved as new lines
  $vals["mcp_guidance"] = str_replace(array("\r\n", "\r"), "\n", $vals["mcp_guidance"]);
  if (mcpGuidanceError($vals["mcp_guidance"]) !== null) {
    printError(mcpGuidanceError($vals["mcp_guidance"]));
    return(FALSE);
  }
  $languages = preg_split('/[\s,]+/', $vals["languages"], -1, PREG_SPLIT_NO_EMPTY);
  if (count(array_filter($languages, "validLanguageCode")) != count($languages)) {
    printError(t("Not saved. Other languages must be language codes, such as fr or pt-BR, separated by spaces."));
    return(FALSE);
  }
  $vals["languages"] = implode(" ", $languages);
  //A ticked checkbox is saved as "1", and one that isn't as an empty string
  foreach (array("glossary_display", "mcp_server") as $checkbox) {
    $vals[$checkbox] = ($vals[$checkbox] == "") ? "" : "1";
  }

  $ok = TRUE;
  foreach ($vals as $key => $val) {
    //A setting added by a later version may not have a row yet
    $ok = dbQuery("INSERT INTO ".table("config")." (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?;", array($key, $val, $val)) && $ok;
  }
  reportSaved($ok);
  $GLOBALS["ontomasticon"]["config"] = getConfig($db);
  return($ok);
}

/**
 * Retrieve the current configuration from the database.
 *
 * @return Array of configuration variables
 */
function getConfig() {
  global $db;
  $config = array();
  $sql = "SELECT * FROM ".table("config").";";
  $result = $db->query($sql);
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $config[$row["key"]] = $row["value"];
      if ($row["key"] == "base_url") {
        if (substr($row["value"], -1) != "/") {
          $config[$row["key"]] .= "/";
        }
      }
    }
    $result->close();
  }
  global $version;
  $config["version"] = $version;
  return(checkConfig($config));
}

/**
 * Applies configuration settings that change how the site runs. This must
 * not print anything, as it runs before headers are sent and on API requests.
 * Configuration problems are reported to admins by adminSanity().
 *
 * @param Array   $config Site configuration variables as an array, e.g. from getConfig()

 * @return Array $config Site configuration variables as an array.
 */
function checkConfig($config) {
  if ($config["mode"] == "debug") {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
  }
  return($config);
}

/**
 * Record the version the database schema matches, after an update step.
 *
 * @param String $v Version the database now matches
 *
 * @return String $v, for tracking progress through the update steps
 */
function setDBVersion($v) {
  dbQuery("UPDATE ".table("config")." SET `value` = ? WHERE `key` = 'version_db';", array($v));
  dbQuery("UPDATE ".table("config")." SET `value` = ? WHERE `key` = 'version';", array($v));
  return($v);
}
