<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// Code to handle retreiving, editing and saving terms

function getTerm($shortname) {
  return(loadTerm("shortname", $shortname));
}

function getTermByID($id) {
  return(loadTerm("id", $id));
}

//Load a term by its shortname or id, with its parent and broader terms given as shortnames
function loadTerm($column, $value) {
  if ($column == "id") {
    $sql = "SELECT * FROM `terms` WHERE `id` = ?;";
  } else {
    $sql = "SELECT * FROM `terms` WHERE `shortname` = ?;";
  }
  $result = dbQuery($sql, array($value));
  if ($result) {
    $ret = $result->fetch_assoc();
    if ($result->num_rows == 0) {return(null);}
    $result->close();
    $ret["parent"] = termShortname($ret["parent"]);
    $ret["broader"] = termShortname($ret["broader"]);
    return($ret);
  } else {
    return(null);
  }
}

function getTerms($cv=null) {
  if ($cv != null) {
    $sql = "SELECT * FROM `terms` WHERE `cv` = ? AND `invalid_reason` IS NULL ORDER BY `shortname`;";
    $result = dbQuery($sql, array($cv));
  }  else {
    $sql = "SELECT * FROM `terms` WHERE `cv` IS NULL AND `invalid_reason` IS NULL;";
    $result = dbQuery($sql);
  }

  $ret = array();
  if ($result) {
    $ret = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
  }

  //Fetch related terms for the whole list at once, rather than for each term
  $ids = array_column($ret, "id");
  $broaderIds = array();
  foreach ($ret as $row) {
    if ($row["broader"] != "") {
      $broaderIds[] = $row["broader"];
    }
  }
  $broaderIds = array_values(array_unique($broaderIds));

  $children = termsGroupedBy("parent", "SELECT * FROM `terms` WHERE `parent` IN (%s) ORDER BY `invalid_reason`;", $ids);
  $narrower = termsGroupedBy("broader", "SELECT * FROM `terms` WHERE `broader` IN (%s) AND `invalid_reason` IS NULL ORDER BY `shortname`;", $ids);
  $broader  = termsGroupedBy("id", "SELECT * FROM `terms` WHERE `id` IN (%s) AND `invalid_reason` IS NULL;", $broaderIds);

  $out = array();
  foreach ($ret as $row) {
    $row["children"] = isset($children[$row["id"]]) ? $children[$row["id"]] : array();
    $row["narrower"] = isset($narrower[$row["id"]]) ? $narrower[$row["id"]] : array();
    if ($row["broader"] != "") {
      $row["broader"] = isset($broader[$row["broader"]]) ? $broader[$row["broader"]] : array();
    }
    $out[] = $row;
  }
  return($out);
}

//Run a query for terms matching a list of ids, grouped by one of their columns.
//$sql must contain a single %s where the id placeholders go.
function termsGroupedBy($column, $sql, $ids) {
  $grouped = array();
  if (count($ids) == 0) {
    return($grouped);
  }
  $placeholders = implode(", ", array_fill(0, count($ids), "?"));
  $result = dbQuery(sprintf($sql, $placeholders), $ids);
  if ($result) {
    foreach ($result->fetch_all(MYSQLI_ASSOC) as $term) {
      $grouped[$term[$column]][] = $term;
    }
    $result->close();
  }
  return($grouped);
}

//The URI of a term given as a row of the terms table, or a link to it
function term2URI($term, $link=FALSE) {
  $out = Term::fromRow($term)->uri();
  return(($link) ? l($out, $out) : $out);
}

//Look up the id of a term from its shortname, or NULL if there is no match
function termID($shortname) {
  $result = dbQuery("SELECT `id` FROM `terms` WHERE `shortname` = ?;", array($shortname));
  if ($result && $row = $result->fetch_assoc()) {
    return($row["id"]);
  }
  return(null);
}

//Look up the shortname of a term from its id, or NULL if there is no match
function termShortname($id) {
  if ($id === null || $id === "") {
    return(null);
  }
  $result = dbQuery("SELECT `shortname` FROM `terms` WHERE `id` = ?;", array($id));
  if ($result && $row = $result->fetch_assoc()) {
    return($row["shortname"]);
  }
  return(null);
}

//Look up the ids of the parent and broader terms named in a term form.
//Prints an error and returns NULL if a named term doesn't exist.
function termRelations() {
  $ids = array();
  foreach (array("parent", "broader") as $field) {
    $shortname = trim($_POST[$field]);
    $ids[$field] = ($shortname == "") ? null : termID($shortname);
    if ($shortname != "" && $ids[$field] === null) {
      printError(t("Not saved. There is no term with the short name")." ".$shortname);
      return(null);
    }
  }
  return($ids);
}

function editTerm() {
  $relations = termRelations();
  if ($relations === null) {
    return(FALSE);
  }
  $shortname = $GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"];
  $name = trim($_POST['name']);
  $description = trim($_POST['description']);
  $language = trim($_POST['language']);
  $opaque = (isset($_POST["opaque"]) ? 1 : 0);
  $cv = ((!isset($_POST["cv"]) || $_POST["cv"]=="none") ? "" : trim($_POST['cv']));
  $invalid = ((!isset($_POST["invalid"]) || $_POST["invalid"]=="none") ? "" : trim($_POST['invalid']));
  $reference = trim($_POST['reference']);

  $sql  = "UPDATE `terms` SET `name` = ?, `description` = ?, `language` = ?, `opaque` = ?, ";
  $sql .= "`invalid_reason` = ?, `cv` = ?, `parent` = ?, `broader` = ?, `reference` = ? ";
  $sql .= "WHERE `shortname` = ?;";
  return(reportSaved(dbQuery($sql, array(
    $name,
    $description,
    $language,
    $opaque,
    ($invalid == "") ? null : $invalid,
    ($cv == "") ? null : $cv,
    $relations["parent"],
    $relations["broader"],
    $reference,
    $shortname
  ))));
}

function addTerm() {
  $shortname = trim($_POST['shortname']);
  if ($shortname == "") {
    printError(t("Not saved. A short name is required."));
    return(FALSE);
  }
  if (!validShortname($shortname)) {
    printError(t("Not saved. A short name can only use the letters A to Z, digits, hyphens, underscores and full stops, and can't start with a full stop."));
    return(FALSE);
  }
  if (termID($shortname) !== null) {
    printError(t("Not saved. There is already a term with the short name")." ".$shortname);
    return(FALSE);
  }
  $relations = termRelations();
  if ($relations === null) {
    return(FALSE);
  }
  $name = trim($_POST['name']);
  $description = trim($_POST['description']);
  $language = trim($_POST['language']);
  $opaque = (isset($_POST["opaque"]) ? 1 : 0);
  $cv = ((!isset($_POST["cv"]) || $_POST["cv"]=="none") ? "" : trim($_POST['cv']));
  $invalid = ((!isset($_POST["invalid"]) || $_POST["invalid"]=="none") ? "" : trim($_POST['invalid']));
  $reference = trim($_POST['reference']);

  $sql  = "INSERT INTO `terms` (`shortname`, `name`, `description`, `language`, `opaque`, `invalid_reason`, `cv`, `parent`, `broader`, `reference`) ";
  $sql .= "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?);";
  return(reportSaved(dbQuery($sql, array(
    $shortname,
    $name,
    $description,
    $language,
    $opaque,
    ($invalid == "") ? null : $invalid,
    ($cv == "") ? null : $cv,
    $relations["parent"],
    $relations["broader"],
    $reference
  )), "Term added."));
}

function deleteTerm() {
  global $db;
  $id = termID($GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"]);
  if ($id === null) {
    printError(t("No matching term found"));
    return(FALSE);
  }
  //Unlink terms that refer to this one, so they don't point at a missing term
  $db->begin_transaction();
  $ok = dbQuery("UPDATE `terms` SET `parent` = NULL WHERE `parent` = ?;", array($id))
    && dbQuery("UPDATE `terms` SET `broader` = NULL WHERE `broader` = ?;", array($id))
    && dbQuery("DELETE FROM `terms` WHERE `id` = ?;", array($id));
  if ($ok) {
    $db->commit();
    return(TRUE);
  }
  $error = dbError();
  $db->rollback();
  printError(t("Could not delete").": ".$error);
  return(FALSE);
}
