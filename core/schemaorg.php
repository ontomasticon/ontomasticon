<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// schema.org data embedded in pages, so search engines and other crawlers can tell that a page defines terms.
// Terms are defined terms and vocabularies (or the site's terms that aren't in one) are defined term sets.
// The RDF formats, with SKOS and TDWG's properties, are in jsonld.php and rdf.php.

//A term on its own page, with the set it is in named, as an array ready for schemaOrgScript()
function schemaOrgTermJSONLD($term, $vocabulary) {
  $node = array("@context" => "https://schema.org") + schemaOrgTerm($term);
  $node["inDefinedTermSet"] = schemaOrgTermSetNode($vocabulary);
  return($node);
}

//A vocabulary, or the site's terms that aren't in one, as a defined term set with its terms, as an array ready for schemaOrgScript()
function schemaOrgTermSetJSONLD($vocabulary, $terms) {
  $language = configValue("default_lang");
  $set = array("@context" => "https://schema.org") + schemaOrgTermSetNode($vocabulary);
  $description = plainText($vocabulary->description);
  if ($description != "") {
    $set["description"] = jsonLDText($description, $language);
  }
  if (validLanguageTag($language)) {
    $set["inLanguage"] = $language;
  }
  foreach (array("creator" => $vocabulary->creator, "publisher" => $vocabulary->publisher) as $property => $value) {
    if ($value != "") {
      $set[$property] = (string)$value;
    }
  }
  if (preg_match('#^https?://\S+$#iD', (string)$vocabulary->license) === 1) {
    $set["license"] = (string)$vocabulary->license;
  }
  $reference = plainText($vocabulary->reference);
  if ($reference != "") {
    $set["citation"] = $reference;
  }
  if (count($terms) > 0) {
    $set["hasDefinedTerm"] = array_map("schemaOrgTerm", $terms);
  }
  return($set);
}

//A term as a defined term, without the context, for use inside a larger document
function schemaOrgTerm($term) {
  $node = array(
    "@type" => "DefinedTerm",
    "@id" => $term->uri()
  );
  if ($term->name != "") {
    $node["name"] = jsonLDText($term->name, $term->language);
  }
  //Synonyms' names are other names for the term, as they are alternative labels in SKOS
  $alternateNames = array();
  foreach ($term->children() as $child) {
    if ($child->isSynonym() && $child->name != "" && $child->name != $term->name) {
      $alternateNames[] = jsonLDText($child->name, $child->language);
    }
  }
  if (count($alternateNames) > 0) {
    $node["alternateName"] = $alternateNames;
  }
  $node["termCode"] = (string)$term->shortname;
  $definition = plainText($term->description);
  if ($definition != "") {
    $node["description"] = jsonLDText($definition, $term->language);
  }
  $node["url"] = $term->uri();
  $node["inDefinedTermSet"] = array("@id" => $term->vocabulary()->uri());
  return($node);
}

//A vocabulary as a defined term set, identified and named but without its terms
function schemaOrgTermSetNode($vocabulary) {
  $set = array(
    "@type" => "DefinedTermSet",
    "@id" => $vocabulary->uri()
  );
  if ($vocabulary->name != "") {
    $set["name"] = jsonLDText($vocabulary->name, configValue("default_lang"));
  }
  $set["url"] = $vocabulary->uri();
  return($set);
}

//Data as a JSON-LD script element for a page's head. < and > are escaped, so text in the data can't end the element.
//Empty if this PHP can't write JSON.
function schemaOrgScript($data) {
  if (!function_exists("json_encode")) {
    return("");
  }
  $json = toJSON($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
  return("<script type=\"application/ld+json\">\n".$json."\n</script>\n");
}
