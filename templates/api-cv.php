<?php
//A vocabulary as a SKOS concept scheme in JSON-LD, with its terms. Without a shortname,
//the site's terms that aren't in a vocabulary are returned as the site's own scheme.
header('Content-Type: application/ld+json; charset=utf-8');
if (isset($_GET["shortname"]) && $_GET["shortname"] != "") {
  $vocabulary = Vocabulary::find($_GET["shortname"]);
} else {
  $vocabulary = Vocabulary::site();
}

if ($vocabulary == null) {
  http_response_code(404);
  print("null");
} else {
  print(jsonLDOutput(vocabularyJSONLD($vocabulary, $vocabulary->terms())));
}
exit;
