<?php
//format=jsonld or format=ttl returns the term as a SKOS concept in JSON-LD or Turtle;
//otherwise the term's database row is returned as JSON
$format = formatParameter();
$term = null;
if (isset($_GET["term"])) {
  //The term whose URI this is, exactly: the site's base URL (including any subdirectory), then the term's shortname,
  //or cv/cv_name#name for a term in a vocabulary, where name is its id if the term is opaque
  $found = is_string($_GET["term"]) ? Term::findByURI($_GET["term"]) : null;
  $term = ($found == null) ? null : getTermByID($found->id);
} else if (isset($_GET["shortname"])) {
   $term = getTerm($_GET["shortname"]);
}

if ($format !== null) {
  printRDF(($term == null) ? null : termJSONLD(Term::findByID($term["id"])), $format);
  exit;
}

header('Content-Type: application/json; charset=utf-8');
if ($term != null) {
  $term["url"] = term2URI($term);
  //Values are returned as strings, as they were before the database code used prepared statements
  foreach ($term as $key => $value) {
    if ($value !== null) {
      $term[$key] = (string)$value;
    }
  }
}

print(($term == null) ? "null" : toJSON($term));
exit;
