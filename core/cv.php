<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// Code to handle Controlled Vocabularies (CVs)

function getCVs() {
  global $db;
  $ret = array();
  $sql = "SELECT * FROM ".table("cv").";";
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
  $prefix = isset($_POST['prefix']) ? trim($_POST['prefix']) : "";
  if (prefixError($prefix) !== null) {
    printError(prefixError($prefix));
    return(FALSE);
  }

  $sql = "UPDATE ".table("cv")." SET `name` = ?, `description` = ?, `reference` = ?, `prefix` = ? WHERE `shortname` = ?;";
  $ok = reportSaved(dbQuery($sql, array($name, $description, $reference, ($prefix == "") ? null : $prefix, $CV)));

  $GLOBALS["ontomasticon"]["CVs"] = getCVs($db);
  return($ok);
}

function addCV() {
  global $db;
  $shortname = trim($_POST['shortname']);
  $name = trim($_POST['name']);
  $description = trim($_POST['description']);
  $reference = trim($_POST['reference']);
  $prefix = isset($_POST['prefix']) ? trim($_POST['prefix']) : "";

  if ($shortname == "") {
    printError(t("Not saved. A short name is required."));
    return(FALSE);
  }
  if (!validShortname($shortname)) {
    printError(t("Not saved. A short name can only use the letters A to Z, digits, hyphens, underscores and full stops, and can't start with a full stop."));
    return(FALSE);
  }
  $existing = dbQuery("SELECT `shortname` FROM ".table("cv")." WHERE `shortname` = ?;", array($shortname));
  if ($existing && $existing->num_rows > 0) {
    printError(t("Not saved. There is already a controlled vocabulary with the short name")." ".$shortname);
    return(FALSE);
  }

  if (prefixError($prefix) !== null) {
    printError(prefixError($prefix));
    return(FALSE);
  }

  $sql = "INSERT INTO ".table("cv")." (`shortname`, `name`, `description`, `reference`, `prefix`) VALUES (?, ?, ?, ?, ?);";
  $ok = reportSaved(dbQuery($sql, array($shortname, $name, $description, $reference, ($prefix == "") ? null : $prefix)), "Controlled vocabulary added.");

  $GLOBALS["ontomasticon"]["CVs"] = getCVs($db);
  return($ok);
}

//Why a vocabulary can't be deleted, or NULL if it can. Synonyms are only shown with the term they are a synonym of,
//so synonyms outside the vocabulary of its terms would be left out of sight, and have to be dealt with first.
function cvDeleteError($CV) {
  $sql  = "SELECT `t`.`shortname` FROM ".table("terms")." AS `t` JOIN ".table("terms")." AS `d` ON `t`.`parent` = `d`.`id` ";
  $sql .= "WHERE `d`.`cv` = ? AND `t`.`invalid_reason` = 'Synonym' AND (`t`.`cv` IS NULL OR `t`.`cv` <> ?) ORDER BY `t`.`shortname`;";
  $result = dbQuery($sql, array($CV, $CV));
  $synonyms = ($result) ? array_column($result->fetch_all(MYSQLI_ASSOC), "shortname") : array();
  if (count($synonyms) == 0) {
    return(null);
  }
  return(t("Not deleted. Terms outside this controlled vocabulary are synonyms of its terms. Change their parent, or delete them, first:")." ".implode(", ", $synonyms));
}

function deleteCV() {
  global $db;
  $CV = $GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"];
  $error = cvDeleteError($CV);
  if ($error !== null) {
    printError($error);
    return(FALSE);
  }

  $db->begin_transaction();
  //Unlink terms elsewhere that refer to this vocabulary's terms, so they don't point at missing terms
  $ok = dbQuery("UPDATE ".table("terms")." AS `t` JOIN ".table("terms")." AS `d` ON `t`.`parent` = `d`.`id` SET `t`.`parent` = NULL WHERE `d`.`cv` = ?;", array($CV))
    && dbQuery("UPDATE ".table("terms")." AS `t` JOIN ".table("terms")." AS `d` ON `t`.`broader` = `d`.`id` SET `t`.`broader` = NULL WHERE `d`.`cv` = ?;", array($CV))
    && dbQuery("DELETE FROM ".table("related_terms")." WHERE `term` IN (SELECT `id` FROM ".table("terms")." WHERE `cv` = ?) OR `related` IN (SELECT `id` FROM ".table("terms")." WHERE `cv` = ?);", array($CV, $CV))
    //Properties whose values came from this vocabulary no longer say where their values come from
    && dbQuery("UPDATE ".table("terms")." SET `range_cv` = NULL WHERE `range_cv` = ?;", array($CV))
    && dbQuery("DELETE FROM ".table("terms")." WHERE `cv` = ?;", array($CV))
    && dbQuery("DELETE FROM ".table("cv")." WHERE `shortname` = ?;", array($CV));
  if ($ok) {
    $db->commit();
    return(TRUE);
  }
  $error = dbError();
  $db->rollback();
  printError(t("Could not delete").": ".$error);
  return(FALSE);
}
