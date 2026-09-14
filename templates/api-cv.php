<?php
//A vocabulary as a SKOS concept scheme with its terms, in JSON-LD or with format=ttl in Turtle.
//Without a shortname, the site's terms that aren't in a vocabulary are returned as the site's own scheme.
if (isset($_GET["shortname"]) && $_GET["shortname"] != "") {
  $vocabulary = Vocabulary::find($_GET["shortname"]);
} else {
  $vocabulary = Vocabulary::site();
}

$format = (formatParameter() !== null) ? formatParameter() : "jsonld";
printRDF(($vocabulary == null) ? null : vocabularyJSONLD($vocabulary, $vocabulary->terms()), $format);
exit;
