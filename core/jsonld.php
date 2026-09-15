<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// JSON-LD output. Terms are SKOS concepts, with the properties TDWG requires of
// controlled vocabulary terms (rdfs:label, rdfs:comment and rdf:value) as well.
// Vocabularies are SKOS concept schemes.

function jsonLDContext() {
  return(array(
    "dcterms" => "http://purl.org/dc/terms/",
    "owl" => "http://www.w3.org/2002/07/owl#",
    "rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
    "rdfs" => "http://www.w3.org/2000/01/rdf-schema#",
    "skos" => "http://www.w3.org/2004/02/skos/core#",
    "vann" => "http://purl.org/vocab/vann/",
    "xsd" => "http://www.w3.org/2001/XMLSchema#"
  ));
}

//JSON-LD as text: indented, with URIs and non-ASCII characters left unescaped
function jsonLDOutput($data) {
  return(toJSON($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

//A term as a SKOS concept, as an array ready for jsonLDOutput()
function termJSONLD($term) {
  return(array("@context" => jsonLDContext()) + termNode($term));
}

//A vocabulary, or the site's terms that aren't in one, as a SKOS concept scheme followed by
//its terms as concepts, as an array ready for jsonLDOutput()
function vocabularyJSONLD($vocabulary, $terms) {
  $config = $GLOBALS["ontomasticon"]["config"];
  $language = isset($config["default_lang"]) ? $config["default_lang"] : "";
  $scheme = array(
    "@id" => $vocabulary->uri(),
    "@type" => "skos:ConceptScheme"
  );
  if ($vocabulary->name != "") {
    $scheme["dcterms:title"] = jsonLDText($vocabulary->name, $language);
    $scheme["rdfs:label"] = $scheme["dcterms:title"];
    $scheme["skos:prefLabel"] = $scheme["dcterms:title"];
  }
  $description = plainText($vocabulary->description);
  if ($description != "") {
    $scheme["dcterms:description"] = jsonLDText($description, $language);
  }
  if ($vocabulary->creator != "") {
    $scheme["dcterms:creator"] = (string)$vocabulary->creator;
  }
  if ($vocabulary->publisher != "") {
    $scheme["dcterms:publisher"] = (string)$vocabulary->publisher;
  }
  if (preg_match('#^https?://\S+$#iD', (string)$vocabulary->license) === 1) {
    $scheme["dcterms:license"] = array("@id" => $vocabulary->license);
  }
  if ($vocabulary->prefix != "") {
    $scheme["vann:preferredNamespacePrefix"] = (string)$vocabulary->prefix;
    $scheme["vann:preferredNamespaceUri"] = $vocabulary->namespaceURI();
  }
  $scheme = jsonLDReference($scheme, $vocabulary->reference);

  $concepts = array_map("termNode", $terms);
  $topConcepts = array();
  foreach ($concepts as $concept) {
    if (isset($concept["skos:topConceptOf"])) {
      $topConcepts[] = array("@id" => $concept["@id"]);
    }
  }
  if (count($topConcepts) > 0) {
    $scheme["skos:hasTopConcept"] = $topConcepts;
  }
  return(array(
    "@context" => jsonLDContext(),
    "@graph" => array_merge(array($scheme), $concepts)
  ));
}

//A term as a SKOS concept, without the context, for use inside a larger document
function termNode($term) {
  $scheme = array("@id" => $term->vocabulary()->uri());
  //Every term is a SKOS concept, so SKOS tools and Darwin Core measurement types can use any of them.
  //Properties and classes are also typed as what they are.
  $rdfTypes = array("property" => "rdf:Property", "class" => "rdfs:Class");
  $node = array(
    "@id" => $term->uri(),
    "@type" => isset($rdfTypes[$term->type]) ? array("skos:Concept", $rdfTypes[$term->type]) : "skos:Concept"
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
  if (isset($rdfTypes[$term->type])) {
    $node["rdfs:isDefinedBy"] = $scheme;
  }
  $broader = $term->broader();
  if ($broader != null) {
    $node["skos:broader"] = jsonLDLink($broader);
    //A property or class under another of the same type is also a sub-property or sub-class of it
    if (isset($rdfTypes[$term->type]) && $broader->type == $term->type) {
      $node[($term->type == "property") ? "rdfs:subPropertyOf" : "rdfs:subClassOf"] = jsonLDLink($broader);
    }
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

  //TDWG gives when terms were created and modified as dates
  foreach (array("dcterms:created" => $term->created, "dcterms:modified" => $term->modified) as $property => $datetime) {
    if (jsonLDDate($datetime) !== null) {
      $node[$property] = jsonLDDate($datetime);
    }
  }
  return(jsonLDReference($node, $term->reference));
}

//A node with its reference added: a web address as the source, anything else as a citation
function jsonLDReference($node, $reference) {
  $reference = plainText($reference);
  if (preg_match('#^https?://\S+$#i', $reference)) {
    $node["dcterms:source"] = array("@id" => $reference);
  } elseif ($reference != "") {
    $node["dcterms:bibliographicCitation"] = $reference;
  }
  return($node);
}

//A text value, tagged with its language if it has one. A language that isn't a valid
//language tag (such as en_GB) is left out, as it would make the RDF invalid.
function jsonLDText($text, $language) {
  if (!validLanguageTag($language)) {
    return((string)$text);
  }
  return(array("@value" => (string)$text, "@language" => $language));
}

//Whether a term's language can be used as a language tag in RDF, such as en or pt-BR
function validLanguageTag($language) {
  return(preg_match('/^[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*$/D', (string)$language) === 1);
}

//The date of a database DATETIME as an xsd:date value, or NULL if there is no date
function jsonLDDate($datetime) {
  if (preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2})/', (string)$datetime, $matches) !== 1 || $matches[1] == "0000-00-00") {
    return(null);
  }
  return(array("@value" => $matches[1], "@type" => "xsd:date"));
}

function jsonLDLink($term) {
  return(array("@id" => $term->uri()));
}
