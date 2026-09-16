<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// JSON-LD output. Terms are SKOS concepts, with the properties TDWG requires of
// controlled vocabulary terms (rdfs:label, rdfs:comment and rdf:value) as well.
// Vocabularies are SKOS concept schemes. On a glossary, the words for each term are
// also given as OntoLex lexical entries, described with LexInfo (see termLexicalEntries()).

//The namespaces of OntoLex, which models words and what they mean, and of LexInfo, which describes words
define("ONTOLEX_NAMESPACE", "http://www.w3.org/ns/lemon/ontolex#");
define("LEXINFO_NAMESPACE", "http://www.lexinfo.net/ontology/3.0/lexinfo#");

function jsonLDContext() {
  $context = array(
    "dcterms" => "http://purl.org/dc/terms/",
    "owl" => "http://www.w3.org/2002/07/owl#",
    "rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
    "rdfs" => "http://www.w3.org/2000/01/rdf-schema#",
    "skos" => "http://www.w3.org/2004/02/skos/core#",
    "vann" => "http://purl.org/vocab/vann/",
    "xsd" => "http://www.w3.org/2001/XMLSchema#"
  );
  //Only a glossary's lexical entries use OntoLex and LexInfo
  if (isGlossary()) {
    $context["lexinfo"] = LEXINFO_NAMESPACE;
    $context["ontolex"] = ONTOLEX_NAMESPACE;
    ksort($context);
  }
  return($context);
}

//JSON-LD as text: indented, with URIs and non-ASCII characters left unescaped
function jsonLDOutput($data) {
  return(toJSON($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

//A term as a SKOS concept, as an array ready for jsonLDOutput(). On a glossary, a term with words to give as lexical
//entries is a graph of the concept followed by its entries.
function termJSONLD($term) {
  $entries = termLexicalEntries($term);
  if (count($entries) == 0) {
    return(array("@context" => jsonLDContext()) + termNode($term));
  }
  return(array(
    "@context" => jsonLDContext(),
    "@graph" => array_merge(array(termNode($term)), $entries)
  ));
}

//A vocabulary, or the site's terms that aren't in one, as a SKOS concept scheme followed by its terms as concepts,
//and on a glossary by the lexical entries for their words, as an array ready for jsonLDOutput()
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
  $entries = array();
  foreach ($terms as $term) {
    $entries = array_merge($entries, termLexicalEntries($term));
  }
  return(array(
    "@context" => jsonLDContext(),
    "@graph" => array_merge(array($scheme), $concepts, $entries)
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
  //Where a property's values come from. A datatype is its range. There is no agreed way to say in RDF that values come
  //from a vocabulary, so that is a note people can read, in English whatever the visitor's language.
  $datatypes = termDatatypes();
  if ($term->type == "property" && isset($datatypes[(string)$term->datatype])) {
    $node["rdfs:range"] = array("@id" => $datatypes[$term->datatype]["iri"]);
  } elseif ($term->type == "property" && $term->rangeCV != null) {
    $CVs = isset($GLOBALS["ontomasticon"]["CVs"]) ? $GLOBALS["ontomasticon"]["CVs"] : array();
    $name = (isset($CVs[$term->rangeCV]) && $CVs[$term->rangeCV]["name"] != "") ? $CVs[$term->rangeCV]["name"] : $term->rangeCV;
    $node["skos:scopeNote"] = "Values come from the ".$name." controlled vocabulary: ".(new Vocabulary($term->rangeCV))->uri();
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

  //The term's acronym and its synonyms' names are alternative labels for it, and a synonym is replaced by its parent.
  //Other parent and child links are shown as "Related terms" on the site, with the term's related terms.
  $altLabels = array();
  if ($term->acronym != "") {
    $altLabels[] = jsonLDText($term->acronym, $term->language);
  }
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
  foreach ($term->related() as $relatedTerm) {
    $related[] = jsonLDLink($relatedTerm);
  }
  if (count($altLabels) > 0) {
    $node["skos:altLabel"] = $altLabels;
  }
  if (count($related) > 0) {
    //A related term can also be the term's parent or child, but is only linked once
    $node["skos:related"] = array_values(array_unique($related, SORT_REGULAR));
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

//On a glossary, the words for a term as OntoLex lexical entries, each denoting the concept the word means, without the
//context; elsewhere none. The term's name is an entry at Term::entryURI("entry"): the preferred term for its concept, or
//for a synonym an admitted term for the concept it is a synonym of. The term's acronym is an entry at
//Term::entryURI("acronym"), the acronym for the name's entry, which is then the full form.
function termLexicalEntries($term) {
  if (!isGlossary()) {
    return(array());
  }
  $parent = $term->parent();
  $concept = jsonLDLink(($term->isSynonym() && $parent != null) ? $parent : $term);
  $entries = array();
  if ($term->name != "") {
    $entry = lexicalEntryNode($term->entryURI("entry"), $term->name, $term->language, $concept);
    if ($term->acronym != "") {
      $entry["lexinfo:termType"] = array("@id" => LEXINFO_NAMESPACE."fullForm");
    }
    if ($term->isSynonym()) {
      $entry["lexinfo:normativeAuthorization"] = array("@id" => LEXINFO_NAMESPACE."admittedTerm");
    } elseif (!$term->isDeprecated()) {
      $entry["lexinfo:normativeAuthorization"] = array("@id" => LEXINFO_NAMESPACE."preferredTerm");
    }
    $entries[] = $entry;
  }
  if ($term->acronym != "") {
    $entry = lexicalEntryNode($term->entryURI("acronym"), $term->acronym, $term->language, $concept);
    $entry["lexinfo:termType"] = array("@id" => LEXINFO_NAMESPACE."acronym");
    if ($term->name != "") {
      $entry["lexinfo:acronymFor"] = array("@id" => $term->entryURI("entry"));
    }
    $entries[] = $entry;
  }
  return($entries);
}

//A lexical entry at $uri for a word written as $text in $language, which means the concept $concept links to
function lexicalEntryNode($uri, $text, $language, $concept) {
  return(array(
    "@id" => $uri,
    "@type" => "ontolex:LexicalEntry",
    "rdfs:label" => jsonLDText($text, $language),
    //The written form has no address of its own, so it is a blank node
    "ontolex:canonicalForm" => array("@type" => "ontolex:Form", "ontolex:writtenRep" => jsonLDText($text, $language)),
    "ontolex:denotes" => $concept
  ));
}

//A node with its references added (see referenceList()): web addresses as sources and anything else as citations,
//each given as a single value when there is only one
function jsonLDReference($node, $reference) {
  $sources = array();
  $citations = array();
  foreach (referenceList($reference) as $line) {
    $text = plainText($line);
    if (preg_match('#^https?://\S+$#iD', $text) === 1) {
      $sources[] = array("@id" => $text);
    } elseif ($text != "") {
      $citations[] = $text;
    }
  }
  if (count($sources) > 0) {
    $node["dcterms:source"] = (count($sources) == 1) ? $sources[0] : $sources;
  }
  if (count($citations) > 0) {
    $node["dcterms:bibliographicCitation"] = (count($citations) == 1) ? $citations[0] : $citations;
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
