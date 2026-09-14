<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// Code to handle retreiving, editing and saving terms

function getTerm($shortname) {
  $result = dbQuery("SELECT * FROM `terms` WHERE `shortname` = ?;", array($shortname));
  if ($result) {
    $ret = $result->fetch_assoc();
    if ($result->num_rows == 0) {return(null);}
    $result->close();
    if ($ret["parent"] != "") {
      $res = dbQuery("SELECT `shortname` FROM `terms` WHERE `id` = ?;", array($ret["parent"]));
      $ret["parent"] = $res->fetch_assoc()["shortname"];
      $res->close();
    }
    if ($ret["broader"] != "") {
      $res = dbQuery("SELECT `shortname` FROM `terms` WHERE `id` = ?;", array($ret["broader"]));
      $ret["broader"] = $res->fetch_assoc()["shortname"];
      $res->close();
    }
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

  //Need to add child terms
  $out = array();
  foreach ($ret as $row) {
    $sql = "SELECT * FROM `terms` WHERE `parent` = ? ORDER BY `invalid_reason`;";
    $result = dbQuery($sql, array($row["id"]));
    if ($result) {
      $row["children"] = $result->fetch_all(MYSQLI_ASSOC);
      $result->close();
    }
    $sql = "SELECT * FROM `terms` WHERE `broader` = ? AND `invalid_reason` IS NULL ORDER BY `shortname`;";
    $result = dbQuery($sql, array($row["id"]));
    if ($result) {
      $row["narrower"] = $result->fetch_all(MYSQLI_ASSOC);
      $result->close();
    }
    if ($row["broader"] != "") {
      $sql = "SELECT * FROM `terms` WHERE `id` = ? AND `invalid_reason` IS NULL;";
      $result = dbQuery($sql, array($row["broader"]));
      if ($result) {
        $row["broader"] = $result->fetch_all(MYSQLI_ASSOC);
        $result->close();
      }
    }
  $out[] = $row;
  }
  return($out);
}

function term2URI($term, $link=FALSE) {
  global $db;
  $config = getConfig($db);
  $out = "https://".$config["base_url"];
  if ($term['cv'] == null) {
    if ($term["opaque"] == 0) {
      $out .= $term["shortname"];
    } else {
      $out .= $term["id"];
    }
  } else {
    if ($term["opaque"] == 0 ) {
      $out .= "cv/".$term["cv"]."#".$term["shortname"];
    } else {
      $out .= "cv/".$term["cv"]."#".$term["id"];
    }
  }
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

function editTerm() {
  $shortname = $GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"];
  $name = trim($_POST['name']);
  $description = trim($_POST['description']);
  $language = trim($_POST['language']);
  $opaque = (isset($_POST["opaque"]) ? 1 : 0);
  $cv = ((!isset($_POST["cv"]) || $_POST["cv"]=="none") ? "" : trim($_POST['cv']));
  $invalid = ((!isset($_POST["invalid"]) || $_POST["invalid"]=="none") ? "" : trim($_POST['invalid']));
  $parent = ($_POST["parent"] != "") ? termID(trim($_POST['parent'])) : null;
  $broader = ($_POST["broader"] != "") ? termID(trim($_POST['broader'])) : null;
  $reference = trim($_POST['reference']);

  $sql  = "UPDATE `terms` SET `name` = ?, `description` = ?, `language` = ?, `opaque` = ?, ";
  $sql .= "`invalid_reason` = ?, `cv` = ?, `parent` = ?, `broader` = ?, `reference` = ? ";
  $sql .= "WHERE `shortname` = ?;";
  dbQuery($sql, array(
    $name,
    $description,
    $language,
    $opaque,
    ($invalid == "") ? null : $invalid,
    ($cv == "") ? null : $cv,
    $parent,
    $broader,
    $reference,
    $shortname
  ));
}

function addTerm() {
  $shortname = trim($_POST['shortname']);
  $name = trim($_POST['name']);
  $description = trim($_POST['description']);
  $language = trim($_POST['language']);
  $opaque = (isset($_POST["opaque"]) ? 1 : 0);
  $cv = ((!isset($_POST["cv"]) || $_POST["cv"]=="none") ? "" : trim($_POST['cv']));
  $invalid = ((!isset($_POST["invalid"]) || $_POST["invalid"]=="none") ? "" : trim($_POST['invalid']));
  $parent = ($_POST["parent"] != "") ? termID(trim($_POST['parent'])) : null;
  $broader = ($_POST["broader"] != "") ? termID(trim($_POST['broader'])) : null;

  $sql  = "INSERT INTO `terms` (`shortname`, `name`, `description`, `language`, `opaque`, `invalid_reason`, `cv`, `parent`, `broader`) ";
  $sql .= "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?);";
  dbQuery($sql, array(
    $shortname,
    $name,
    $description,
    $language,
    $opaque,
    ($invalid == "") ? null : $invalid,
    ($cv == "") ? null : $cv,
    $parent,
    $broader
  ));
}

function deleteTerm() {
  $sn = $GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"];
  dbQuery("DELETE FROM `terms` WHERE `shortname` = ?;", array($sn));
}
