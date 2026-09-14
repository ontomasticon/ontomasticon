<?php
//format=jsonld or format=ttl returns the term as a SKOS concept in JSON-LD or Turtle;
//otherwise the term's database row is returned as JSON
$format = formatParameter();
$term = null;
if (isset($_GET["term"])) {
  //Term URLs are https://host/name or https://host/cv/cv_name#name, where
  //name is the term's shortname, or its id if the term is opaque
  $parts = explode("/", $_GET["term"]);
  $name = null;
  if (isset($parts[4]) && $parts[3] == "cv" && strpos($parts[4], "#") !== FALSE) {
    $name = explode("#",$parts[4])[1];
  } else if (isset($parts[3]) && $parts[3] != "" && $parts[3] != "cv") {
    $name = $parts[3];
  }
  if ($name !== null) {
    $term = getTerm($name);
    if ($term == null && preg_match('/^[0-9]+$/D', $name) === 1) {
      $term = getTermByID($name);
      //Only opaque terms are identified by their id
      if ($term != null && $term["opaque"] != 1) {
        $term = null;
      }
    }
  }
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
