<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// JSON-LD output. Terms are SKOS concepts, with the properties TDWG requires of
// controlled vocabulary terms (rdfs:label, rdfs:comment and rdf:value) as well.

function jsonLDContext() {
  return(array(
    "dcterms" => "http://purl.org/dc/terms/",
    "owl" => "http://www.w3.org/2002/07/owl#",
    "rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
    "rdfs" => "http://www.w3.org/2000/01/rdf-schema#",
    "skos" => "http://www.w3.org/2004/02/skos/core#"
  ));
}

//A term as a SKOS concept, as an array ready for toJSON()
function termJSONLD($term) {
  $scheme = array("@id" => $term->vocabulary()->uri());
  $node = array(
    "@context" => jsonLDContext(),
    "@id" => $term->uri(),
    "@type" => "skos:Concept"
  );
  if ($term->name != "") {
    $node["rdfs:label"] = jsonLDText($term->name, $term->language);
    $node["skos:prefLabel"] = $node["rdfs:label"];
  }
  $node["rdf:value"] = (string)$term->shortname;
  $definition = plainText($term->description);
  if ($definition != "") {
    $node["rdfs:comment"] = jsonLDText($definition, $term->language);
    $node["skos:definition"] = $node["rdfs:comment"];
  }

  $node["skos:inScheme"] = $scheme;
  $broader = $term->broader();
  if ($broader != null) {
    $node["skos:broader"] = jsonLDLink($broader);
  } elseif (!$term->isDeprecated()) {
    $node["skos:topConceptOf"] = $scheme;
  }
  $narrower = array_map("jsonLDLink", $term->narrower());
  if (count($narrower) > 0) {
    $node["skos:narrower"] = $narrower;
  }

  //A synonym's name is an alternative label for its parent, and a synonym is replaced by its parent.
  //Other parent and child links are shown as "Related terms" on the site.
  $altLabels = array();
  $related = array();
  foreach ($term->children() as $child) {
    if (!$child->isSynonym()) {
      $related[] = jsonLDLink($child);
    } elseif ($child->name != "" && $child->name != $term->name) {
      $altLabels[] = jsonLDText($child->name, $child->language);
    }
  }
  $parent = $term->parent();
  if ($parent != null && $term->isSynonym()) {
    $node["dcterms:isReplacedBy"] = jsonLDLink($parent);
  } elseif ($parent != null) {
    $related[] = jsonLDLink($parent);
  }
  if (count($altLabels) > 0) {
    $node["skos:altLabel"] = $altLabels;
  }
  if (count($related) > 0) {
    $node["skos:related"] = $related;
  }
  if ($term->isDeprecated()) {
    $node["owl:deprecated"] = TRUE;
  }

  $reference = plainText($term->reference);
  if (preg_match('#^https?://\S+$#i', $reference)) {
    $node["dcterms:source"] = array("@id" => $reference);
  } elseif ($reference != "") {
    $node["dcterms:bibliographicCitation"] = $reference;
  }
  return($node);
}

//A text value, tagged with its language if it has one
function jsonLDText($text, $language) {
  if ($language == "") {
    return((string)$text);
  }
  return(array("@value" => (string)$text, "@language" => $language));
}

function jsonLDLink($term) {
  return(array("@id" => $term->uri()));
}
