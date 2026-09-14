<?php
//JSON-LD for the site's own addresses, for clients that ask for it (see requestedFormat()): the site's
//scheme at /, a vocabulary's scheme at /cv/shortname, and a term outside a vocabulary at its URI
header('Content-Type: application/ld+json; charset=utf-8');
$page = $GLOBALS["ontomasticon"]["pageInfo"];
$data = null;
if ($page["page_type"] == "home") {
  $vocabulary = Vocabulary::site();
  $data = vocabularyJSONLD($vocabulary, $vocabulary->terms());
} elseif ($page["page_type"] == "cv" && $page["active_page"] != "") {
  $vocabulary = Vocabulary::find($page["active_page"]);
  if ($vocabulary != null) {
    $data = vocabularyJSONLD($vocabulary, $vocabulary->terms());
  }
} elseif ($page["page_type"] == "term") {
  $term = Term::findByURI(siteURL().rawurldecode($page["active_page"]));
  if ($term != null) {
    $data = termJSONLD($term);
  }
}

if ($data === null) {
  http_response_code(404);
  print("null");
} else {
  print(jsonLDOutput($data));
}
