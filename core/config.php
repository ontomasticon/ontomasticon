<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// Functions to handle saving, retrieving and checking configuration variables.

/**
 * Saves a user configuration submitted via $_POST to the database and
 * sets the global config variable to match.
 */
function saveConfig() {
  global $db;
  $vals = array();
  $vals["site_name"] = trim($_POST['site_name']);
  $vals["author"] = trim($_POST['author']);
  $vals["default_lang"] = trim($_POST['default_lang']);
  $vals["base_url"] = trim($_POST['base_url']);
  $vals["description"] = trim($_POST['description']);

  $ok = TRUE;
  foreach ($vals as $key => $val) {
    $ok = dbQuery("UPDATE `config` SET `value` = ? WHERE `key` = ?;", array($val, $key)) && $ok;
  }
  reportSaved($ok);
  $GLOBALS["ontomasticon"]["config"] = getConfig($db);
}

/**
 * Retrieve the current configuration from the database.
 *
 * @return Array of configuration variables
 */
function getConfig() {
  global $db;
  $config = array();
  $sql = "SELECT * FROM `config`;";
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
  dbQuery("UPDATE `config` SET `value` = ? WHERE `key` = 'version_db';", array($v));
  dbQuery("UPDATE `config` SET `value` = ? WHERE `key` = 'version';", array($v));
  return($v);
}
