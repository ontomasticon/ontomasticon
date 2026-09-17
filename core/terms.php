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
    $sql = "SELECT * FROM ".table("terms")." WHERE `id` = ?;";
  } else {
    $sql = "SELECT * FROM ".table("terms")." WHERE `shortname` = ?;";
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

//The terms in a list that aren't deprecated, as pages list them. Deprecated terms, such as synonyms, are shown in the
//entries of the terms they link to instead.
function validTerms($terms) {
  return(array_values(array_filter($terms, function($term) { return(!$term->isDeprecated()); })));
}

//A term's child terms in the order its entry lists them: those that aren't deprecated first, then the others, such as
//its synonyms, each in order of short name
function termPageChildren($term) {
  $children = $term->children();
  return(array_merge(validTerms($children), array_values(array_filter($children, function($child) { return($child->isDeprecated()); }))));
}

//The most characters of a search that are used
define("SEARCH_QUERY_LENGTH", 100);

//The text searched for with ?q= (see searchText()), or "" if there is none
function searchQuery() {
  if (!isset($_GET["q"]) || !is_string($_GET["q"])) {
    return("");
  }
  return(searchText($_GET["q"]));
}

//Text to search for, without spaces around it and cut to SEARCH_QUERY_LENGTH characters
function searchText($text) {
  preg_match('/^.{0,'.SEARCH_QUERY_LENGTH.'}/us', validUTF8(trim($text)), $matches);
  return(trim($matches[0]));
}

//A pattern for LIKE ... ESCAPE '|' matching text that contains $text, or that starts with it if $start is TRUE.
//LIKE's wildcards in $text only match themselves.
function likePattern($text, $start = FALSE) {
  $escaped = str_replace(array("|", "%", "_"), array("||", "|%", "|_"), $text);
  return((($start) ? "" : "%").$escaped."%");
}

//Terms whose name, short name or acronym contains $query, to suggest as a visitor types a search: at most $limit, those
//whose name, short name or acronym starts with it first, then in order of name. A synonym leads to the term it is a
//synonym of, and only one suggestion leads to each term. Each is array("name", "shortname", "acronym" (or NULL), "uri"
//(of the term it leads to), "vocabulary" (the name of that term's vocabulary, or NULL), "synonym_of" (the name of that
//term for a synonym, or NULL)).
function termSuggestions($query, $limit = 10) {
  if ($query === "") {
    return(array());
  }
  $contains = likePattern($query);
  $starts = likePattern($query, TRUE);
  $sql  = "SELECT * FROM ".table("terms")." WHERE (`invalid_reason` IS NULL OR `invalid_reason` = 'Synonym') ";
  $sql .= "AND (`name` LIKE ? ESCAPE '|' OR `shortname` LIKE ? ESCAPE '|' OR `acronym` LIKE ? ESCAPE '|') ";
  //Synonyms of terms already suggested are left out, so there are spare rows to fill the list
  $sql .= "ORDER BY (`name` LIKE ? ESCAPE '|' OR `shortname` LIKE ? ESCAPE '|' OR `acronym` LIKE ? ESCAPE '|') DESC, `name`, `shortname` LIMIT ".((int)$limit * 2).";";
  $matches = Term::fromResult(dbQuery($sql, array($contains, $contains, $contains, $starts, $starts, $starts)));

  //The terms that synonyms lead to, unless they are deprecated too
  $parentIDs = array();
  foreach ($matches as $term) {
    if ($term->isSynonym() && $term->parentID != "") {
      $parentIDs[] = $term->parentID;
    }
  }
  $parents = array();
  foreach (Term::findByIDs($parentIDs) as $parent) {
    if (!$parent->isDeprecated()) {
      $parents[$parent->id] = $parent;
    }
  }
  $CVs = isset($GLOBALS["ontomasticon"]["CVs"]) ? $GLOBALS["ontomasticon"]["CVs"] : array();

  $suggestions = array();
  foreach ($matches as $term) {
    $target = $term;
    $synonymOf = null;
    if ($term->isSynonym()) {
      if (!isset($parents[$term->parentID])) {
        continue;
      }
      $target = $parents[$term->parentID];
      $synonymOf = glossaryLabel($target);
    }
    if (isset($suggestions[$target->id]) || count($suggestions) >= $limit) {
      continue;
    }
    $suggestions[$target->id] = array(
      "name" => glossaryLabel($term),
      "shortname" => $term->shortname,
      "acronym" => ($term->acronym != "") ? $term->acronym : null,
      "uri" => $target->uri(),
      "vocabulary" => ($target->cv != "" && isset($CVs[$target->cv])) ? $CVs[$target->cv]["name"] : null,
      "synonym_of" => $synonymOf
    );
  }
  return(array_values($suggestions));
}

//Valid terms for the page of results of a search, with their related terms loaded (see Term::loadRelations()): those whose
//name, short name, acronym or definition contains $query, or that have a synonym whose name, short name or acronym does. Terms
//whose name starts with it come first, then in order of name. At most $limit terms are given, or all of them if it is NULL.
function searchTerms($query, $limit = null) {
  if ($query === "") {
    return(array());
  }
  $contains = likePattern($query);
  $sql  = "SELECT * FROM ".table("terms")." WHERE `invalid_reason` IS NULL ";
  $sql .= "AND (`name` LIKE ? ESCAPE '|' OR `shortname` LIKE ? ESCAPE '|' OR `acronym` LIKE ? ESCAPE '|' OR `description` LIKE ? ESCAPE '|' ";
  $sql .= "OR `id` IN (SELECT `parent` FROM ".table("terms")." WHERE `invalid_reason` = 'Synonym' AND (`name` LIKE ? ESCAPE '|' OR `shortname` LIKE ? ESCAPE '|' OR `acronym` LIKE ? ESCAPE '|'))) ";
  $sql .= "ORDER BY `name` LIKE ? ESCAPE '|' DESC, `name`, `shortname`".(($limit === null) ? "" : " LIMIT ".(int)$limit).";";
  $terms = Term::fromResult(dbQuery($sql, array($contains, $contains, $contains, $contains, $contains, $contains, $contains, likePattern($query, TRUE))));
  Term::loadRelations($terms);
  return($terms);
}

//Whether the site is a glossary. A glossary lists its terms in alphabetical order under a heading for each letter, with
//links to the letters above and below the list and its terms' acronyms listed too, and gives the words for its terms as
//lexical entries in RDF (see termLexicalEntries()). The setting was first only for showing terms, hence its name.
function isGlossary() {
  return(configValue("glossary_display") == "1");
}

//The entries of a glossary for its terms, each as array("label" => the words listed, "term" => the term, "see" => whether
//the entry only points to the term's own entry): an entry for each term under its name (see glossaryLabel()), and one for
//each term with an acronym other than its name, listing the acronym as well
function glossaryEntries($terms) {
  $entries = array();
  foreach ($terms as $term) {
    $entries[] = array("label" => glossaryLabel($term), "term" => $term, "see" => FALSE);
    $acronym = trim((string)$term->acronym);
    if ($acronym != "" && strcasecmp($acronym, glossaryLabel($term)) != 0) {
      $entries[] = array("label" => $acronym, "term" => $term, "see" => TRUE);
    }
  }
  return($entries);
}

//The name a term is listed under in a glossary: its name, or its shortname if it has none
function glossaryLabel($term) {
  $name = trim((string)$term->name);
  return(($name == "") ? (string)$term->shortname : $name);
}

//The letter from A to Z an entry with this label is listed under in a glossary, or "#" for a label that doesn't start with one of them
function glossaryLetter($label) {
  $letter = strtoupper(substr($label, 0, 1));
  return((preg_match('/^[A-Z]$/D', $letter) === 1) ? $letter : "#");
}

//The id of a letter's heading in a glossary. Shortnames can't contain a colon, so it can't be the same as a term's id.
function glossaryAnchor($letter) {
  return("glossary:".(($letter == "#") ? "other" : $letter));
}

//Entries of a glossary (see glossaryEntries()) grouped by glossaryLetter(), as letter => entries, with the letters in order
//("#" first) and the entries under each in alphabetical order, ignoring case
function glossaryGroups($entries) {
  usort($entries, function($a, $b) {
    $compare = strnatcasecmp($a["label"], $b["label"]);
    return(($compare != 0) ? $compare : strcmp($a["term"]->shortname, $b["term"]->shortname));
  });
  $byLetter = array();
  foreach ($entries as $entry) {
    $byLetter[glossaryLetter($entry["label"])][] = $entry;
  }
  $groups = array();
  foreach (array_merge(array("#"), range("A", "Z")) as $letter) {
    if (isset($byLetter[$letter])) {
      $groups[$letter] = $byLetter[$letter];
    }
  }
  return($groups);
}

//Links to each letter of a glossary, given as glossaryGroups(). Every letter from A to Z is shown, but only those with
//terms are links; "#" is only shown if some terms are listed under it.
function glossaryIndex($groups) {
  $letters = array_merge(isset($groups["#"]) ? array("#") : array(), range("A", "Z"));
  $items = array();
  foreach ($letters as $letter) {
    if (isset($groups[$letter])) {
      $items[] = '<a href="#'.h(glossaryAnchor($letter)).'">'.h($letter).'</a>';
    } else {
      $items[] = '<span class="glossary-index-empty">'.h($letter).'</span>';
    }
  }
  return('<nav class="glossary-index" aria-label="'.h(t("Terms by letter")).'">'.implode(" ", $items).'</nav>');
}

//Look up the id of a term from its shortname, or NULL if there is no match
function termID($shortname) {
  $result = dbQuery("SELECT `id` FROM ".table("terms")." WHERE `shortname` = ?;", array($shortname));
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
  $result = dbQuery("SELECT `shortname` FROM ".table("terms")." WHERE `id` = ?;", array($id));
  if ($result && $row = $result->fetch_assoc()) {
    return($row["shortname"]);
  }
  return(null);
}

//The short names in a list typed into a form, separated by commas, spaces or new lines, each given once
function shortnameList($text) {
  $names = preg_split('/[\s,]+/', trim(is_string($text) ? $text : ""), -1, PREG_SPLIT_NO_EMPTY);
  return(array_values(array_unique($names)));
}

//Look up the ids of the parent, broader and related terms named in a term form, for the term with this shortname and,
//once it has been saved, this id, as array("parent" => an id or NULL, "broader" => an id or NULL, "related" => ids).
//Prints an error and returns NULL if a named term doesn't exist, is the term itself, or would make a loop of parent or
//broader terms, which would break the hierarchy in RDF.
function termRelations($shortname, $id = null) {
  $ids = array();
  foreach (array("parent", "broader") as $field) {
    $related = trim($_POST[$field]);
    $ids[$field] = ($related == "") ? null : termID($related);
    if ($related == "") {
      continue;
    }
    if (strcasecmp($related, $shortname) == 0 || ($id !== null && $ids[$field] == $id)) {
      printError(t("Not saved. A term can't be its own parent or broader term."));
      return(null);
    }
    if ($ids[$field] === null) {
      printError(t("Not saved. There is no term with the short name")." ".$related);
      return(null);
    }
    if ($id !== null && termLinksReach($field, $ids[$field], $id)) {
      printError(t("Not saved. Following the parent or broader terms up from this term leads back to the term being saved, which would make a loop:")." ".$related);
      return(null);
    }
  }
  //Related terms are named in a list (see shortnameList())
  $ids["related"] = array();
  foreach (shortnameList(isset($_POST["related"]) ? $_POST["related"] : "") as $name) {
    $relatedID = termID($name);
    if (strcasecmp($name, $shortname) == 0 || ($id !== null && $relatedID == $id)) {
      printError(t("Not saved. A term can't be related to itself."));
      return(null);
    }
    if ($relatedID === null) {
      printError(t("Not saved. There is no term with the short name")." ".$name);
      return(null);
    }
    $ids["related"][] = $relatedID;
  }
  $ids["related"] = array_values(array_unique($ids["related"]));
  return($ids);
}

//Make the terms with the ids in $relatedIDs the related terms of the term with id $id, in place of any it had.
//Being related goes both ways, so a row is saved for each way round, and each term is listed as related to the other.
//Returns FALSE if the database refuses a change.
function saveRelatedTerms($id, $relatedIDs) {
  if (!dbQuery("DELETE FROM ".table("related_terms")." WHERE `term` = ? OR `related` = ?;", array($id, $id))) {
    return(FALSE);
  }
  foreach ($relatedIDs as $relatedID) {
    if (!dbQuery("INSERT INTO ".table("related_terms")." (`term`, `related`) VALUES (?, ?), (?, ?);", array($id, $relatedID, $relatedID, $id))) {
      return(FALSE);
    }
  }
  return(TRUE);
}

//The short names of the related terms of the term with an id, in alphabetical order, as a term form lists them
function relatedTermShortnames($id) {
  $sql  = "SELECT `t`.`shortname` FROM ".table("related_terms")." AS `r` JOIN ".table("terms")." AS `t` ON `t`.`id` = `r`.`related` ";
  $sql .= "WHERE `r`.`term` = ? ORDER BY `t`.`shortname`;";
  $result = dbQuery($sql, array($id));
  return(($result) ? array_column($result->fetch_all(MYSQLI_ASSOC), "shortname") : array());
}

//Whether following parent (or broader) links up from the term with id $fromID reaches the term with id $targetID.
//Stops at a loop saved before loops were refused, so it always ends.
function termLinksReach($column, $fromID, $targetID) {
  $sql = "SELECT ".(($column == "parent") ? "`parent`" : "`broader`")." AS `next` FROM ".table("terms")." WHERE `id` = ?;";
  $seen = array();
  $id = $fromID;
  while ($id !== null && !isset($seen[$id])) {
    if ($id == $targetID) {
      return(TRUE);
    }
    $seen[$id] = TRUE;
    $result = dbQuery($sql, array($id));
    $row = ($result) ? $result->fetch_assoc() : null;
    $id = ($row == null) ? null : $row["next"];
  }
  return(FALSE);
}

//The most characters a term's language can have: the size of the database column, and the length RFC 5646 asks
//systems that store language tags to allow
define("TERM_LANGUAGE_LENGTH", 35);

//The error explaining why a term can't have a language, or NULL if it can. A term can have no language, but a
//language it has must be a language tag that RDF can use, and short enough for the database.
function termLanguageError($language) {
  if ((string)$language === "") {
    return(null);
  }
  if (!validLanguageTag($language)) {
    return(t("Not saved. The language must be a language tag such as en, pt-BR or zh-Hant, with hyphens rather than underscores."));
  }
  if (strlen($language) > TERM_LANGUAGE_LENGTH) {
    return(t("Not saved. A language tag can be at most 35 characters long."));
  }
  return(null);
}

//The most characters a term's acronym can have: the size of the database column
define("TERM_ACRONYM_LENGTH", 50);

//The error explaining why a term can't have an acronym, or NULL if it can. No acronym is always allowed. Bytes are
//counted, as the mbstring extension isn't always available, so an acronym of other scripts may be refused a little early.
function termAcronymError($acronym) {
  if (strlen((string)$acronym) > TERM_ACRONYM_LENGTH) {
    return(t("Not saved. An acronym can be at most 50 characters long."));
  }
  return(null);
}

//Whether the terms table's language column is narrower than TERM_LANGUAGE_LENGTH, as it was before the database
//update that widens it
function termLanguageColumnTooNarrow() {
  global $db;
  $result = $db->query("SHOW COLUMNS FROM ".table("terms")." LIKE 'language';");
  $row = ($result) ? $result->fetch_assoc() : null;
  if ($row == null || preg_match('/\(([0-9]+)\)/', $row["Type"], $matches) !== 1) {
    return(FALSE);
  }
  return((int)$matches[1] < TERM_LANGUAGE_LENGTH);
}

//Why the term with an id can't be deleted, or NULL if it can. Synonyms are only shown with the term they are a
//synonym of, so deleting that term would leave them out of sight. They have to be given another parent or deleted first.
function termDeleteError($id) {
  if ($id === null) {
    return(null);
  }
  $result = dbQuery("SELECT `shortname` FROM ".table("terms")." WHERE `parent` = ? AND `invalid_reason` = 'Synonym' ORDER BY `shortname`;", array($id));
  $synonyms = ($result) ? array_column($result->fetch_all(MYSQLI_ASSOC), "shortname") : array();
  if (count($synonyms) == 0) {
    return(null);
  }
  return(t("Not deleted. These terms are synonyms of this term. Change their parent, or delete them, first:")." ".implode(", ", $synonyms));
}

//Whether a term outside a vocabulary with this shortname would have a URI that never reaches the term's page:
//one whose first path segment activePage() routes elsewhere, a file or directory at the top of the install (which
//the web server serves or forbids rather than passing to index.php), or a name ending in .php (which
//nginx.conf.example passes to PHP rather than to index.php). Compared without case, as some file systems ignore it.
function reservedTermShortname($shortname) {
  $name = strtolower($shortname);
  if (substr($name, -4) == ".php") {
    return(TRUE);
  }
  $files = scandir(dirname(__DIR__));
  $reserved = array_merge(reservedRouteSegments(), ($files === FALSE) ? array() : $files);
  return(in_array($name, array_map("strtolower", $reserved), TRUE));
}

//The error explaining why a term can't have a shortname with the vocabulary and opaque setting it would be
//saved with, or NULL if it can. Opaque terms are named by their id in URIs, so their shortname is never a problem.
function termShortnameClash($shortname, $cv, $opaque) {
  if ($opaque) {
    return(null);
  }
  //Term::findByURI() reads a final segment of digits as a possible id, so "42" would share a URI with opaque term 42
  if (preg_match('/^[0-9]+$/D', $shortname) === 1) {
    return(t("Not saved. A short name made only of digits can only be used for an opaque term, as it could be mistaken for another term's id."));
  }
  if ($cv == "" && reservedTermShortname($shortname)) {
    return(t("Not saved. The site's own pages or files already use this address, so the short name can only be used for a term in a controlled vocabulary or an opaque term:")." ".$shortname);
  }
  return(null);
}

//The types a term can have, with their names. Every term is a SKOS concept; a property is also a characteristic
//that is measured or recorded, and a class is also a kind of thing.
function termTypeLabels() {
  return(array("concept" => "Concept", "property" => "Property", "class" => "Class"));
}

function termTypes() {
  return(array_keys(termTypeLabels()));
}

//A term type from a form or the database. Anything that isn't one of termTypes(), including no type, is a concept.
function termType($type) {
  return(in_array($type, termTypes(), TRUE) ? $type : "concept");
}

//The datatypes a property's values can have, with their names and XML Schema datatypes
function termDatatypes() {
  return(array(
    "decimal" => array("label" => "Numbers", "iri" => "http://www.w3.org/2001/XMLSchema#decimal"),
    "integer" => array("label" => "Whole numbers", "iri" => "http://www.w3.org/2001/XMLSchema#integer"),
    "string" => array("label" => "Text", "iri" => "http://www.w3.org/2001/XMLSchema#string"),
    "boolean" => array("label" => "Yes or no", "iri" => "http://www.w3.org/2001/XMLSchema#boolean"),
    "date" => array("label" => "Dates", "iri" => "http://www.w3.org/2001/XMLSchema#date")
  ));
}

//The Values choice on a term form for a row of the terms table: "cv:" or "datatype:" followed by a name, or "" for neither
function termValuesChoice($row) {
  if (isset($row["range_cv"]) && $row["range_cv"] != "") {
    return("cv:".$row["range_cv"]);
  }
  if (isset($row["datatype"]) && $row["datatype"] != "") {
    return("datatype:".$row["datatype"]);
  }
  return("");
}

//Where the values of a term of $type come from, from the Values choice on a term form, as array("range_cv" => a
//vocabulary's shortname or NULL, "datatype" => one of termDatatypes() or NULL). Only properties have values, so other
//terms have neither. Prints an error and returns NULL if the choice is a vocabulary or datatype the site doesn't have.
function termValues($type) {
  $none = array("range_cv" => null, "datatype" => null);
  $choice = (isset($_POST["values"]) && is_string($_POST["values"])) ? trim($_POST["values"]) : "";
  if ($type != "property" || $choice == "") {
    return($none);
  }
  $parts = explode(":", $choice, 2);
  $CVs = isset($GLOBALS["ontomasticon"]["CVs"]) ? $GLOBALS["ontomasticon"]["CVs"] : array();
  if (count($parts) == 2 && $parts[0] == "cv" && isset($CVs[$parts[1]])) {
    return(array("range_cv" => $parts[1], "datatype" => null));
  }
  if (count($parts) == 2 && $parts[0] == "datatype" && isset(termDatatypes()[$parts[1]])) {
    return(array("range_cv" => null, "datatype" => $parts[1]));
  }
  printError(t("Not saved. The values must come from a controlled vocabulary or a datatype the site has."));
  return(null);
}

function editTerm() {
  global $db;
  $shortname = $GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"];
  $current = getTerm($shortname);
  $relations = termRelations($shortname, ($current == null) ? null : $current["id"]);
  if ($relations === null) {
    return(FALSE);
  }
  $name = trim($_POST['name']);
  $acronym = isset($_POST["acronym"]) ? trim($_POST["acronym"]) : "";
  if (termAcronymError($acronym) !== null) {
    printError(termAcronymError($acronym));
    return(FALSE);
  }
  $description = trim($_POST['description']);
  $language = trim($_POST['language']);
  if (termLanguageError($language) !== null) {
    printError(termLanguageError($language));
    return(FALSE);
  }
  $opaque = (isset($_POST["opaque"]) ? 1 : 0);
  $cv = ((!isset($_POST["cv"]) || $_POST["cv"]=="none") ? "" : trim($_POST['cv']));
  $invalid = ((!isset($_POST["invalid"]) || $_POST["invalid"]=="none") ? "" : trim($_POST['invalid']));
  //One reference per line (see referenceList())
  $reference = implode("\n", referenceList($_POST['reference']));
  //Moving a term out of a vocabulary, or making it not opaque, can give it a URI it can't use. A term saved with
  //such a URI before this was checked can still be edited, as long as the edit doesn't add a different problem.
  $clash = termShortnameClash($shortname, $cv, $opaque);
  if ($current != null && $clash !== null && $clash !== termShortnameClash($shortname, $current["cv"], $current["opaque"])) {
    printError($clash);
    return(FALSE);
  }

  $type = termType(isset($_POST["type"]) ? $_POST["type"] : "concept");
  $values = termValues($type);
  if ($values === null) {
    return(FALSE);
  }

  $sql  = "UPDATE ".table("terms")." SET `name` = ?, `acronym` = ?, `description` = ?, `language` = ?, `opaque` = ?, `type` = ?, `range_cv` = ?, `datatype` = ?, ";
  $sql .= "`invalid_reason` = ?, `cv` = ?, `parent` = ?, `broader` = ?, `reference` = ?, `modified` = UTC_TIMESTAMP() ";
  $sql .= "WHERE `shortname` = ?;";
  //The term and its related terms are saved together, or not at all
  $db->begin_transaction();
  $ok = dbQuery($sql, array(
    $name,
    ($acronym == "") ? null : $acronym,
    $description,
    $language,
    $opaque,
    $type,
    $values["range_cv"],
    $values["datatype"],
    ($invalid == "") ? null : $invalid,
    ($cv == "") ? null : $cv,
    $relations["parent"],
    $relations["broader"],
    $reference,
    $shortname
  )) && ($current == null || saveRelatedTerms($current["id"], $relations["related"]));
  if ($ok) {
    $db->commit();
  } else {
    $db->rollback();
  }
  return(reportSaved($ok));
}

function addTerm() {
  global $db;
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
  $relations = termRelations($shortname);
  if ($relations === null) {
    return(FALSE);
  }
  $name = trim($_POST['name']);
  $acronym = isset($_POST["acronym"]) ? trim($_POST["acronym"]) : "";
  if (termAcronymError($acronym) !== null) {
    printError(termAcronymError($acronym));
    return(FALSE);
  }
  $description = trim($_POST['description']);
  $language = trim($_POST['language']);
  if (termLanguageError($language) !== null) {
    printError(termLanguageError($language));
    return(FALSE);
  }
  $opaque = (isset($_POST["opaque"]) ? 1 : 0);
  $cv = ((!isset($_POST["cv"]) || $_POST["cv"]=="none") ? "" : trim($_POST['cv']));
  $invalid = ((!isset($_POST["invalid"]) || $_POST["invalid"]=="none") ? "" : trim($_POST['invalid']));
  //One reference per line (see referenceList())
  $reference = implode("\n", referenceList($_POST['reference']));
  $clash = termShortnameClash($shortname, $cv, $opaque);
  if ($clash !== null) {
    printError($clash);
    return(FALSE);
  }

  $type = termType(isset($_POST["type"]) ? $_POST["type"] : "concept");
  $values = termValues($type);
  if ($values === null) {
    return(FALSE);
  }

  $sql  = "INSERT INTO ".table("terms")." (`shortname`, `name`, `acronym`, `description`, `language`, `opaque`, `type`, `range_cv`, `datatype`, `invalid_reason`, `cv`, `parent`, `broader`, `reference`, `created`, `modified`) ";
  $sql .= "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP());";
  //The term and its related terms are saved together, or not at all
  $db->begin_transaction();
  $ok = dbQuery($sql, array(
    $shortname,
    $name,
    ($acronym == "") ? null : $acronym,
    $description,
    $language,
    $opaque,
    $type,
    $values["range_cv"],
    $values["datatype"],
    ($invalid == "") ? null : $invalid,
    ($cv == "") ? null : $cv,
    $relations["parent"],
    $relations["broader"],
    $reference
  )) && saveRelatedTerms(termID($shortname), $relations["related"]);
  if ($ok) {
    $db->commit();
  } else {
    $db->rollback();
  }
  return(reportSaved($ok, "Term added."));
}

function deleteTerm() {
  global $db;
  $id = termID($GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"]);
  if ($id === null) {
    printError(t("No matching term found"));
    return(FALSE);
  }
  $error = termDeleteError($id);
  if ($error !== null) {
    printError($error);
    return(FALSE);
  }
  //Unlink the related and narrower terms that refer to this one, so they don't point at a missing term
  $db->begin_transaction();
  $ok = dbQuery("UPDATE ".table("terms")." SET `parent` = NULL WHERE `parent` = ?;", array($id))
    && dbQuery("UPDATE ".table("terms")." SET `broader` = NULL WHERE `broader` = ?;", array($id))
    && dbQuery("DELETE FROM ".table("related_terms")." WHERE `term` = ? OR `related` = ?;", array($id, $id))
    && dbQuery("DELETE FROM ".table("terms")." WHERE `id` = ?;", array($id));
  if ($ok) {
    $db->commit();
    return(TRUE);
  }
  $error = dbError();
  $db->rollback();
  printError(t("Could not delete").": ".$error);
  return(FALSE);
}
