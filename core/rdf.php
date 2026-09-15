<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// RDF output in the formats clients can ask for. Turtle is written from the same arrays as
// JSON-LD (see core/jsonld.php), so both formats always describe the same RDF.

//Send RDF made by termJSONLD() or vocabularyJSONLD() as $format ("jsonld" or "turtle"),
//or a "not found" response in that format if $data is NULL
function printRDF($data, $format) {
  $turtle = ($format == "turtle");
  header(($turtle) ? "Content-Type: text/turtle; charset=utf-8" : "Content-Type: application/ld+json; charset=utf-8");
  if ($data === null) {
    http_response_code(404);
    //An empty document is valid Turtle
    print(($turtle) ? "" : "null");
    return;
  }
  print(($turtle) ? turtleOutput($data) : jsonLDOutput($data));
}

//RDF made by termJSONLD() or vocabularyJSONLD() as Turtle
function turtleOutput($data) {
  $out = "";
  foreach ($data["@context"] as $prefix => $namespace) {
    $out .= "@prefix ".$prefix.": ".turtleIRI($namespace)." .\n";
  }
  $nodes = isset($data["@graph"]) ? $data["@graph"] : array($data);
  foreach ($nodes as $node) {
    $statements = array();
    foreach ($node as $property => $values) {
      if ($property == "@context" || $property == "@id") {
        continue;
      }
      //A property has a single value or a list of them
      if (!is_array($values) || isset($values["@id"]) || isset($values["@value"])) {
        $values = array($values);
      }
      if ($property == "@type") {
        //Types are prefixed names, such as skos:Concept
        $statements[] = "a ".implode(", ", $values);
      } else {
        $statements[] = $property." ".implode(", ", array_map("turtleValue", $values));
      }
    }
    $out .= "\n".turtleIRI($node["@id"])." ".implode(" ;\n    ", $statements)." .\n";
  }
  return($out);
}

//A JSON-LD value (a string, a boolean, or an array with @id, or with @value and either @type or @language) in Turtle
function turtleValue($value) {
  if (is_bool($value)) {
    return(($value) ? "true" : "false");
  }
  if (is_array($value) && isset($value["@id"])) {
    return(turtleIRI($value["@id"]));
  }
  if (is_array($value) && isset($value["@type"])) {
    //Datatypes are prefixed names, such as xsd:date
    return(turtleString($value["@value"])."^^".$value["@type"]);
  }
  if (is_array($value)) {
    return(turtleString($value["@value"]).(isset($value["@language"]) ? "@".$value["@language"] : ""));
  }
  return(turtleString($value));
}

//An IRI in Turtle, with the characters that aren't allowed in one escaped
function turtleIRI($iri) {
  $escaped = preg_replace_callback('/[\x00-\x20<>"{}|^`\\\\]/', function($matches) {
    return(sprintf("\\u%04X", ord($matches[0])));
  }, validUTF8($iri));
  return("<".$escaped.">");
}

//A string literal in Turtle
function turtleString($text) {
  $escapes = array("\\" => "\\\\", "\"" => "\\\"", "\n" => "\\n", "\r" => "\\r", "\t" => "\\t");
  return("\"".strtr(validUTF8($text), $escapes)."\"");
}
