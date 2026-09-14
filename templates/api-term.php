<?php
header('Content-Type: application/json; charset=utf-8');
$term = null;
if (isset($_GET["term"])) {
  //Term URLs are https://host/shortname or https://host/cv/cv_name#shortname
  $parts = explode("/", $_GET["term"]);
  if (isset($parts[4]) && $parts[3] == "cv" && strpos($parts[4], "#") !== FALSE) {
    $term = getTerm(explode("#",$parts[4])[1]);
  } else if (isset($parts[3]) && $parts[3] != "" && $parts[3] != "cv") {
    $term = getTerm($parts[3]);
  }
} else if (isset($_GET["shortname"])) {
   $term = getTerm($_GET["shortname"]);
}

if ($term != null) {
  $term["url"] = term2URI($term);
}

print(($term == null) ? "null" : json_encode($term));
exit;
