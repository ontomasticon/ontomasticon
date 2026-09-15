<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// RDF output in the formats clients can ask for. Turtle is written from the same arrays as
// JSON-LD (see core/jsonld.php), so both formats always describe the same RDF.

//Send RDF made by termJSONLD() or vocabularyJSONLD() as $format ("jsonld" or "turtle"),
//or a "not found" response with an empty body if $data is NULL
function printRDF($data, $format) {
  $turtle = ($format == "turtle");
  header(($turtle) ? "Content-Type: text/turtle; charset=utf-8" : "Content-Type: application/ld+json; charset=utf-8");
  if ($data === null) {
    //The status says there is nothing there. The body is empty in both formats, as null isn't a JSON-LD document.
    http_response_code(404);
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
    $out .= "\n".turtleIRI($node["@id"])." ".implode(" ;\n    ", turtleStatements($node))." .\n";
  }
  return($out);
}

//What a JSON-LD node says, as Turtle statements of each of its properties with its values, without the node itself
function turtleStatements($node) {
  $statements = array();
  foreach ($node as $property => $values) {
    if ($property == "@context" || $property == "@id") {
      continue;
    }
    //A property has a single value or a list of them, which starts at 0
    if (!is_array($values) || !array_key_exists(0, $values)) {
      $values = array($values);
    }
    if ($property == "@type") {
      //Types are prefixed names, such as skos:Concept
      $statements[] = "a ".implode(", ", $values);
    } else {
      $statements[] = $property." ".implode(", ", array_map("turtleValue", $values));
    }
  }
  return($statements);
}

//A JSON-LD value in Turtle: a string, a boolean, an array with @id, an array with @value and either @type or @language,
//or a node without an address, such as a lexical entry's written form, given in brackets as a blank node
function turtleValue($value) {
  if (is_bool($value)) {
    return(($value) ? "true" : "false");
  }
  if (is_array($value) && isset($value["@id"])) {
    return(turtleIRI($value["@id"]));
  }
  if (is_array($value) && isset($value["@value"]) && isset($value["@type"])) {
    //Datatypes are prefixed names, such as xsd:date
    return(turtleString($value["@value"])."^^".$value["@type"]);
  }
  if (is_array($value) && isset($value["@value"])) {
    return(turtleString($value["@value"]).(isset($value["@language"]) ? "@".$value["@language"] : ""));
  }
  if (is_array($value)) {
    return("[ ".implode(" ; ", turtleStatements($value))." ]");
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
