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

  foreach ($vals as $key => $val) {
    dbQuery("UPDATE `config` SET `value` = ? WHERE `key` = ?;", array($val, $key));
  }
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
 * Checks that the configuration variables supplied as a parameter are
 * consistent with normal (i.e. secure) site operation. Warnings (in HTML)
 * are generated for any incosistency.
 *
 * @param Array   $config Site configuration variables as an array, e.g. from getConfig()

 * @return Array $config Site configuration variables as an array.
 */
function checkConfig($config) {
  if (is_dir("inst")) {
    $out  = "<div class='error'>";
    $out .= "<p>For security please delete the inst directory.</p>";
    $out .= "</div>";
    print $out;
  }
  if ((float)$config['version_db'] < (float)$config["version"]) {
    if (userAllow("administer")) {
      $out  = "<div class='error'>";
      $out .= "<p>You need to run the database update script.</p>";
      $out .= "</div>";
      print $out;
    }
  }
  if ((float)$config['version_db'] > (float)$config["version"]) {
    if (userAllow("administer")) {
      $out  = "<div class='error'>";
      $out .= "<p>The database is running a more recent version than the code base. Please upgrade.</p>";
      $out .= "</div>";
      print $out;
    }
  }
  if ($config["mode"] == "debug") {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
  }
  return($config);
}
