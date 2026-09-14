<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// Code to handle Controlled Vocabularies (CVs)

function CVcount() {
  global $db;
  $sql = "SELECT COUNT(*) AS `count` FROM `cv`;";
  $result = $db->query($sql);
  if ($result) {
    $result = $db->query($sql);
    if ($result) {
      $row = $result->fetch_array(MYSQLI_ASSOC);
      $cv_count = $row["count"];
      $result->close();
    }
  } else {
    $cv_count = 0;
  }
  return($cv_count);
}

function getCVs() {
  global $db;
  $ret = array();
  $sql = "SELECT * FROM `cv`;";
  $result = $db->query($sql);
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $ret[$row["shortname"]] = $row;
    }
    $result->close();
  }
  return($ret);
}

function printCVs($CVs) {
  $out  = "<h2>".t("Controlled Vocabularies")."</h2>";
  $out .= "<table>";
  $out .= "<tr><th>".t("Short name")."</th><th>".t("Name")."</th></tr>";
  foreach ($CVs as $CV) {
    $out .= "<tr>";
    $out .= "<td>".l($CV["shortname"], "/cv/".$CV["shortname"])."</td>";
    $out .= "<td>".h($CV["name"])."</td>";
    $out .= "<td>".cvEditLink($CV["shortname"])."</td>";
    $out .= "</tr>";
  }
  $out .= "</table>";
  print($out);
}

function editCV() {
  global $db;
  $CV = $GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"];

  $name = trim($_POST['name']);
  $description = trim($_POST['description']);
  $reference = trim($_POST['reference']);

  $sql = "UPDATE `cv` SET `name` = ?, `description` = ?, `reference` = ? WHERE `shortname` = ?;";
  dbQuery($sql, array($name, $description, $reference, $CV));

  $GLOBALS["ontomasticon"]["CVs"] = getCVs($db);
}

function addCV() {
  global $db;
  $shortname = trim($_POST['shortname']);
  $name = trim($_POST['name']);
  $description = trim($_POST['description']);
  $reference = trim($_POST['reference']);

  $sql = "INSERT INTO `cv` (`shortname`, `name`, `description`, `reference`) VALUES (?, ?, ?, ?);";
  dbQuery($sql, array($shortname, $name, $description, $reference));

  $GLOBALS["ontomasticon"]["CVs"] = getCVs($db);
}

function deleteCV() {
  $CV = $GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"];

  dbQuery("DELETE FROM `terms` WHERE `cv` = ?;", array($CV));
  dbQuery("DELETE FROM `cv` WHERE `shortname` = ?;", array($CV));
}
