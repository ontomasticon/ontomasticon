<?php
//format=jsonld or format=ttl returns the term as a SKOS concept in JSON-LD or Turtle;
//otherwise the term's database row is returned as JSON
$format = formatParameter();
//The term whose URI this is, exactly: the site's base URL (including any subdirectory), then the term's shortname,
//or cv/cv_name#name for a term in a vocabulary, where name is its id if the term is opaque
$found = (isset($_GET["term"]) && is_string($_GET["term"])) ? Term::findByURI($_GET["term"]) : null;

if ($format !== null) {
  //Or the term with a shortname
  if (!isset($_GET["term"]) && isset($_GET["shortname"])) {
    $found = Term::find($_GET["shortname"]);
  }
  if ($found != null) {
    Term::loadRelations(array($found));
  }
  printRDF(($found == null) ? null : termJSONLD($found), $format);
  exit;
}

header('Content-Type: application/json; charset=utf-8');
if (isset($_GET["term"])) {
  $term = ($found == null) ? null : getTermByID($found->id);
} else {
  $term = isset($_GET["shortname"]) ? getTerm($_GET["shortname"]) : null;
}
if ($term != null) {
  $term["url"] = Term::fromRow($term)->uri();
  //Values are returned as strings, as they were before the database code used prepared statements
  foreach ($term as $key => $value) {
    if ($value !== null) {
      $term[$key] = (string)$value;
    }
  }
}

print(($term == null) ? "null" : toJSON($term));
exit;
