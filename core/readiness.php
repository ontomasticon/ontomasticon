<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// The readiness report: problems that stop the site's vocabularies meeting TDWG's requirements
// for controlled vocabulary terms, or giving clean RDF

//Problems in the site's own terms, vocabularies and settings (see readinessIssues())
function siteReadinessIssues() {
  $terms = Term::all();
  Term::loadRelations($terms);
  return(readinessIssues($terms, getCVs(), $GLOBALS["ontomasticon"]["config"]));
}

//Problems in a list of terms, vocabularies (rows of the cv table) and site settings. Each problem is an array with
//an "id", a "problem" describing it, and the "items" it affects, each with a "label" and a "link" to the page where
//it can be fixed. Problems are always given in the same order, and only those that affect something are returned.
function readinessIssues($terms, $vocabularies, $config) {
  $problems = array(
    "license" => t("The site has no license, so its vocabularies don't say how they can be reused"),
    "prefix" => t("Vocabularies without a namespace prefix"),
    "vocabulary-name" => t("Controlled vocabularies without a name, which TDWG requires"),
    "term-name" => t("Terms without a name, which TDWG requires as their label"),
    "definition" => t("Terms without a definition, which TDWG requires"),
    "language" => t("Terms whose language isn't a valid language tag, so their name and definition have no language in RDF"),
    "shortname" => t("Terms whose short name isn't safe in a URI, so their URI is invalid"),
    "uri-clash" => t("Terms whose URI clashes with another address on the site, so it doesn't reach the term"),
    "utf8" => t("Terms with text that isn't valid UTF-8, which is shown as a replacement character in RDF"),
    "synonym" => t("Synonyms without a parent term, so they don't say which term replaces them"),
    "type-hierarchy" => t("Terms whose broader term is a different type, such as a property under a concept")
  );
  $items = array_fill_keys(array_keys($problems), array());
  $configLink = "/admin/config";

  if (!isset($config["license"]) || $config["license"] == "") {
    $items["license"][] = array("label" => t("Site configuration"), "link" => $configLink);
  }
  $siteTerms = FALSE;
  foreach ($terms as $term) {
    if ($term->cv == null) {
      $siteTerms = TRUE;
    }
  }
  if ($siteTerms && (!isset($config["prefix"]) || $config["prefix"] == "")) {
    $items["prefix"][] = array("label" => t("Terms that aren't in a controlled vocabulary"), "link" => $configLink);
  }

  foreach ($vocabularies as $vocabulary) {
    $item = array("label" => $vocabulary["shortname"], "link" => "/admin/cv/edit/".$vocabulary["shortname"]);
    if (!isset($vocabulary["prefix"]) || $vocabulary["prefix"] == "") {
      $items["prefix"][] = $item;
    }
    if ($vocabulary["name"] == "") {
      $items["vocabulary-name"][] = $item;
    }
  }

  foreach ($terms as $term) {
    $item = array(
      "label" => (($term->cv == null) ? "" : $term->cv.": ").$term->shortname,
      "link" => "/admin/term/edit/".$term->shortname
    );
    if ($term->name == "") {
      $items["term-name"][] = $item;
    }
    if (plainText($term->description) == "") {
      $items["definition"][] = $item;
    }
    if (!validLanguageTag($term->language)) {
      $items["language"][] = $item;
    }
    //Short names saved before they were checked
    if (!validShortname((string)$term->shortname)) {
      $items["shortname"][] = $item;
    } elseif (termShortnameClash($term->shortname, $term->cv, $term->opaque) !== null) {
      $items["uri-clash"][] = $item;
    }
    foreach (array($term->shortname, $term->name, $term->description, $term->reference) as $text) {
      if (validUTF8($text) !== (string)$text) {
        $items["utf8"][] = $item;
        break;
      }
    }
    if ($term->isSynonym() && $term->parentID == null) {
      $items["synonym"][] = $item;
    }
    if ($term->broader() != null && $term->broader()->type != $term->type) {
      $items["type-hierarchy"][] = $item;
    }
  }

  $issues = array();
  foreach ($problems as $id => $problem) {
    if (count($items[$id]) > 0) {
      $issues[] = array("id" => $id, "problem" => $problem, "items" => $items[$id]);
    }
  }
  return($issues);
}
