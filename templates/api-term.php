<?php
header('Content-Type: application/json; charset=utf-8');
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
    if ($term == null && ctype_digit($name)) {
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

if ($term != null) {
  $term["url"] = term2URI($term);
  //Values are returned as strings, as they were before the database code used prepared statements
  foreach ($term as $key => $value) {
    if ($value !== null) {
      $term[$key] = (string)$value;
    }
  }
}

print(($term == null) ? "null" : json_encode($term));
exit;
