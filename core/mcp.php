<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// The MCP server, which lets AI applications that support the Model Context Protocol search the site's terms and read
// them, when the MCP server setting is on. Clients post JSON-RPC messages to /api/mcp (MCP's Streamable HTTP transport).
// From protocol version 2026-07-28, each request gives its protocol version and the client's capabilities, so each is
// answered on its own. Clients of earlier versions start with an initialize request. Those versions let a server keep
// no session, so their requests are answered on their own too.

//The most bytes a request may have. Requests name a tool and give a few short arguments, so this is plenty.
define("MCP_BODY_LIMIT", 65536);

//The most terms search_terms gives, and how many list_terms gives at once
define("MCP_SEARCH_LIMIT", 50);
define("MCP_TERMS_PAGE", 50);

//The most characters of a definition given where a tool lists several terms
define("MCP_SUMMARY_LENGTH", 300);

//The protocol versions the server supports, newest first
function mcpVersions() {
  return(array("2026-07-28", "2025-11-25", "2025-06-18", "2025-03-26"));
}

//Whether a protocol version gives the version and the client's capabilities with each request, rather than starting with initialize
function mcpStatelessVersion($version) {
  return($version === "2026-07-28");
}

//Whether the site's MCP server is on. It needs PHP's JSON extension.
function mcpEnabled() {
  return(configValue("mcp_server") == "1" && function_exists("json_decode"));
}

//Whether a page (see activePage()) is the MCP server, which has no forms or logins, so needs no session
function mcpPage($pageInfo) {
  return($pageInfo["page_type"] == "api" && isset($pageInfo["active_page"]) && $pageInfo["active_page"] == "mcp");
}

//The MCP server's address
function mcpURL() {
  return(siteURL()."api/mcp");
}

//The request's headers, with their names in lower case, as mcpResponse() takes them
function mcpRequestHeaders() {
  $headers = array();
  foreach ($_SERVER as $key => $value) {
    if (strpos($key, "HTTP_") === 0 && is_string($value)) {
      $headers[strtolower(str_replace("_", "-", substr($key, 5)))] = $value;
    }
  }
  return($headers);
}

//The response to a request to the MCP server, as array("status" => the HTTP status, "headers" => header lines, "body" => text,
//or NULL for none). $method is the HTTP method, and $headers have their names in lower case (see mcpRequestHeaders()).
function mcpResponse($method, $headers, $body) {
  //Without a JSON-RPC error in the body, clients can tell there is no MCP server here
  if (!mcpEnabled()) {
    return(mcpEmptyResponse(404));
  }
  //Browsers ask before letting scripts on other websites post requests, which they may, as they may use the rest of the API
  if ($method == "OPTIONS") {
    return(mcpEmptyResponse(204, array(
      "Access-Control-Allow-Methods: POST",
      "Access-Control-Allow-Headers: Content-Type, Accept, MCP-Protocol-Version, Mcp-Method, Mcp-Name",
      "Access-Control-Max-Age: 86400"
    )));
  }
  //Earlier protocol versions also used GET, for messages from the server, and DELETE, to end a session. This server has neither.
  if ($method != "POST") {
    return(mcpEmptyResponse(405, array("Allow: POST, OPTIONS")));
  }
  if (strlen($body) > MCP_BODY_LIMIT) {
    return(mcpErrorResponse(413, null, -32600, "Invalid request: the request is too large."));
  }
  $message = json_decode($body, TRUE);
  if (json_last_error() !== JSON_ERROR_NONE) {
    return(mcpErrorResponse(400, null, -32700, "The request isn't valid JSON."));
  }
  //This also refuses several messages sent together in an array, which MCP no longer allows
  if (!is_array($message) || !isset($message["jsonrpc"]) || $message["jsonrpc"] !== "2.0") {
    return(mcpErrorResponse(400, null, -32600, "Invalid request: send a single JSON-RPC 2.0 message."));
  }
  if (!isset($message["method"]) || !is_string($message["method"])) {
    //A response to a request from the server, which clients of earlier versions could post. This server sends no requests.
    if (array_key_exists("id", $message) && (array_key_exists("result", $message) || array_key_exists("error", $message))) {
      return(mcpEmptyResponse(202));
    }
    return(mcpErrorResponse(400, null, -32600, "Invalid request: a request needs a method."));
  }
  //Notifications, such as notifications/initialized, need no reply
  if (!array_key_exists("id", $message)) {
    return(mcpEmptyResponse(202));
  }
  $id = $message["id"];
  if (!is_string($id) && !is_int($id)) {
    return(mcpErrorResponse(400, null, -32600, "Invalid request: the id must be a string or an integer."));
  }
  if (isset($message["params"]) && !is_array($message["params"])) {
    return(mcpErrorResponse(400, $id, -32602, "Invalid params: params must be an object."));
  }
  $params = isset($message["params"]) ? $message["params"] : array();
  $meta = (isset($params["_meta"]) && is_array($params["_meta"])) ? $params["_meta"] : array();
  $headerVersion = mcpHeader($headers, "mcp-protocol-version");
  if ($message["method"] != "initialize" && (isset($meta["io.modelcontextprotocol/protocolVersion"]) || mcpStatelessVersion($headerVersion))) {
    return(mcpStatelessResponse($id, $message["method"], $params, $meta, $headers));
  }
  //Clients of 2025-06-18 and later give the version they started with in a header. Earlier clients don't.
  if ($headerVersion !== null && !in_array($headerVersion, mcpVersions(), TRUE)) {
    return(mcpUnsupportedVersionResponse($id, $headerVersion));
  }
  return(mcpHandshakeResponse($id, $message["method"], $params));
}

//The response to a request of protocol version 2026-07-28, which gives the version and the client's capabilities in its _meta. Its
//headers repeat the version, the method and, for tools/call, the tool's name, so gateways can route it without reading it. They must
//match the request, so a gateway can't act on one request while this server answers another.
function mcpStatelessResponse($id, $method, $params, $meta, $headers) {
  $version = isset($meta["io.modelcontextprotocol/protocolVersion"]) ? $meta["io.modelcontextprotocol/protocolVersion"] : null;
  if (!is_string($version)) {
    return(mcpErrorResponse(400, $id, -32602, "Invalid params: _meta must give io.modelcontextprotocol/protocolVersion."));
  }
  if (mcpHeader($headers, "mcp-protocol-version") !== $version) {
    return(mcpErrorResponse(400, $id, -32020, "Header mismatch: the MCP-Protocol-Version header must give the protocol version in _meta."));
  }
  if (!mcpStatelessVersion($version)) {
    return(mcpUnsupportedVersionResponse($id, $version));
  }
  if (!isset($meta["io.modelcontextprotocol/clientCapabilities"]) || !is_array($meta["io.modelcontextprotocol/clientCapabilities"])) {
    return(mcpErrorResponse(400, $id, -32602, "Invalid params: _meta must give io.modelcontextprotocol/clientCapabilities."));
  }
  if (mcpHeader($headers, "mcp-method") !== $method) {
    return(mcpErrorResponse(400, $id, -32020, "Header mismatch: the Mcp-Method header must give the request's method."));
  }
  switch ($method) {
    case "server/discover":
      $result = array("supportedVersions" => mcpVersions(), "capabilities" => mcpCapabilities(), "instructions" => mcpInstructions()) + mcpCacheHints();
      break;
    case "tools/list":
      $result = array("tools" => mcpTools()) + mcpCacheHints();
      break;
    case "tools/call":
      if (!isset($params["name"]) || !is_string($params["name"])) {
        return(mcpErrorResponse(400, $id, -32602, "Invalid params: tools/call needs the name of a tool."));
      }
      if (mcpHeaderValue(mcpHeader($headers, "mcp-name")) !== $params["name"]) {
        return(mcpErrorResponse(400, $id, -32020, "Header mismatch: the Mcp-Name header must give the tool's name."));
      }
      $call = mcpToolsCall($params);
      if (isset($call["error"])) {
        return(mcpErrorResponse(200, $id, $call["error"]["code"], $call["error"]["message"]));
      }
      $result = $call["result"];
      break;
    default:
      return(mcpErrorResponse(404, $id, -32601, "Method not found: ".$method));
  }
  $result = array("resultType" => "complete") + $result;
  $result["_meta"] = array("io.modelcontextprotocol/serverInfo" => mcpServerInfo());
  return(mcpResultResponse($id, $result));
}

//The response to a request of a protocol version before 2026-07-28, whose clients start with initialize and may ping
function mcpHandshakeResponse($id, $method, $params) {
  switch ($method) {
    case "initialize":
      //The version the client asks for, if the server supports it, or otherwise the newest version that starts with initialize
      $versions = array_values(array_filter(mcpVersions(), function($version) { return(!mcpStatelessVersion($version)); }));
      $requested = isset($params["protocolVersion"]) ? $params["protocolVersion"] : null;
      $result = array(
        "protocolVersion" => in_array($requested, $versions, TRUE) ? $requested : $versions[0],
        "capabilities" => mcpCapabilities(),
        "serverInfo" => mcpServerInfo(),
        "instructions" => mcpInstructions()
      );
      break;
    case "ping":
      $result = new stdClass();
      break;
    case "tools/list":
      $result = array("tools" => mcpTools());
      break;
    case "tools/call":
      $call = mcpToolsCall($params);
      if (isset($call["error"])) {
        return(mcpErrorResponse(200, $id, $call["error"]["code"], $call["error"]["message"]));
      }
      $result = $call["result"];
      break;
    default:
      //These clients may take an HTTP error as a failed connection, so the error is only given in the JSON-RPC response
      return(mcpErrorResponse(200, $id, -32601, "Method not found: ".$method));
  }
  return(mcpResultResponse($id, $result));
}

//A request header, or NULL if it isn't given
function mcpHeader($headers, $name) {
  return((isset($headers[$name]) && is_string($headers[$name])) ? $headers[$name] : null);
}

//A header value, decoded if the client encoded it as =?base64?...?=, as clients do with values that aren't plain ASCII.
//NULL if the value isn't given or can't be decoded.
function mcpHeaderValue($value) {
  if ($value === null || preg_match('/^=\?base64\?(.*)\?=$/sD', $value, $matches) !== 1) {
    return($value);
  }
  $decoded = base64_decode($matches[1], TRUE);
  return(($decoded === FALSE) ? null : $decoded);
}

function mcpEmptyResponse($status, $headers = array()) {
  return(array("status" => $status, "headers" => $headers, "body" => null));
}

function mcpResultResponse($id, $result) {
  return(mcpJSONResponse(200, array("jsonrpc" => "2.0", "id" => $id, "result" => $result)));
}

//A JSON-RPC error. The id is left out when it is NULL, because the request's id couldn't be read.
function mcpErrorResponse($status, $id, $code, $message, $data = null) {
  $response = array("jsonrpc" => "2.0");
  if ($id !== null) {
    $response["id"] = $id;
  }
  $response["error"] = array("code" => $code, "message" => $message);
  if ($data !== null) {
    $response["error"]["data"] = $data;
  }
  return(mcpJSONResponse($status, $response));
}

function mcpJSONResponse($status, $response) {
  return(array(
    "status" => $status,
    "headers" => array("Content-Type: application/json; charset=utf-8"),
    "body" => toJSON($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
  ));
}

//The error for a protocol version the server doesn't support, listing those it does, so the client can choose one
function mcpUnsupportedVersionResponse($id, $version) {
  return(mcpErrorResponse(400, $id, -32022, "Unsupported protocol version", array("supported" => mcpVersions(), "requested" => $version)));
}

//The server only has tools. PHP arrays with no items are written as JSON arrays, so an empty object is an stdClass.
function mcpCapabilities() {
  return(array("tools" => new stdClass()));
}

//How long clients, and caches shared between them, may keep results that are the same for everyone, such as the list of tools:
//as long as the site's public pages may be kept
function mcpCacheHints() {
  return(array("ttlMs" => PUBLIC_CACHE_SECONDS * 1000, "cacheScope" => "public"));
}

//The server's name and version, titled with the site's name
function mcpServerInfo() {
  $info = array("name" => "ontomasticon");
  if (plainText(configValue("site_name")) != "") {
    $info["title"] = plainText(configValue("site_name"));
  }
  $info["version"] = configValue("version");
  return($info);
}

//What the site is and how to use its tools, which AI applications give their models
function mcpInstructions() {
  $about = plainText(configValue("site_name"));
  $description = plainText(configValue("description"));
  if ($description != "") {
    $about .= (($about == "") ? "" : ": ").$description;
  }
  $text  = ($about == "") ? "" : $about."\n\n";
  $text .= "Use search_terms to find terms by name, acronym or synonym, or by words in their definitions, and get_term for a term's ";
  $text .= "full definition, references and related terms. list_vocabularies and list_terms list the terms. Each term is identified ";
  $text .= "by its URI. When you use a definition, give the term's URI and the references the term gives: [1] in a definition cites ";
  $text .= "the first reference.";
  $publisher = plainText(configValue("publisher"));
  $license = configValue("license");
  if ($publisher != "" || $license != "") {
    $text .= " The terms are published".(($publisher == "") ? "" : " by ".$publisher).(($license == "") ? "" : " under the license at ".$license).".";
  }
  return($text);
}

//The tools the server has, always in this order. None of them change anything.
function mcpTools() {
  $readOnly = array("readOnlyHint" => TRUE, "idempotentHint" => TRUE, "openWorldHint" => FALSE);
  $terms = array("type" => "array", "items" => mcpTermSummarySchema());
  $links = array("type" => "array", "items" => mcpTermLinkSchema());
  return(array(
    array(
      "name" => "search_terms",
      "title" => "Search terms",
      "description" => "Search the site's terms by name, short name, acronym or synonym, or by words in their definitions. Terms whose name "
        ."starts with the search come first. Definitions are shortened here: use get_term for a term's full definition and its references.",
      "inputSchema" => array(
        "type" => "object",
        "properties" => array(
          "query" => array("type" => "string", "description" => "Text to search for, of up to ".SEARCH_QUERY_LENGTH." characters. "
            ."It is matched as it is given, not word by word, so search for one word or phrase at a time."),
          "limit" => array("type" => "integer", "minimum" => 1, "maximum" => MCP_SEARCH_LIMIT, "default" => 10,
            "description" => "The most terms to give, from 1 to ".MCP_SEARCH_LIMIT.". The default is 10.")
        ),
        "required" => array("query")
      ),
      "outputSchema" => mcpObjectSchema(array(
        "terms" => $terms,
        "more" => array("type" => "boolean", "description" => "Whether more terms match than were given")
      )),
      "annotations" => $readOnly
    ),
    array(
      "name" => "get_term",
      "title" => "Get a term",
      "description" => "Get a term by its URI or short name: its full definition as plain text, its references (numbered, so [1] in the "
        ."definition cites the first), its vocabulary, its synonyms, and its broader, narrower and related terms. A synonym is deprecated, "
        ."and gives the term it is a synonym of.",
      "inputSchema" => array(
        "type" => "object",
        "properties" => array(
          "uri" => array("type" => "string", "description" => "The term's URI, as the other tools give it"),
          "shortname" => array("type" => "string", "description" => "The term's short name, if you don't have its URI")
        )
      ),
      "outputSchema" => mcpObjectSchema(array(
        "uri" => array("type" => "string", "description" => "The URI that identifies the term"),
        "shortname" => array("type" => "string"),
        "name" => array("type" => "string"),
        "acronym" => array("type" => array("string", "null")),
        "type" => mcpTermTypeSchema(),
        "language" => array("type" => array("string", "null"), "description" => "The language of the name and definition, such as en"),
        "definition" => array("type" => "string", "description" => "The definition as plain text"),
        "references" => array("type" => "array", "items" => array("type" => "string"),
          "description" => "The references, in order: [1] in the definition cites the first"),
        "vocabulary" => mcpVocabularySchema(),
        "deprecated" => array("type" => "boolean"),
        "synonym_of" => mcpNullable(mcpTermLinkSchema()),
        "synonyms" => $links,
        "broader" => mcpNullable(mcpTermLinkSchema()),
        "narrower" => $links,
        "related" => $links,
        "values" => mcpNullable(mcpObjectSchema(array(
          "datatype" => array("type" => array("string", "null"), "enum" => array_merge(array_keys(termDatatypes()), array(null))),
          "vocabulary" => mcpNullable(mcpVocabularySchema())
        ))),
        "created" => array("type" => array("string", "null"), "description" => "The date the term was added, as YYYY-MM-DD"),
        "modified" => array("type" => array("string", "null"), "description" => "The date the term last changed, as YYYY-MM-DD")
      )),
      "annotations" => $readOnly
    ),
    array(
      "name" => "list_vocabularies",
      "title" => "List vocabularies",
      "description" => "List the site's controlled vocabularies, with how many terms each has. The first, whose short name is null, "
        ."is the site's own terms, which aren't in a controlled vocabulary.",
      "inputSchema" => array("type" => "object", "additionalProperties" => FALSE),
      "outputSchema" => mcpObjectSchema(array(
        "vocabularies" => array("type" => "array", "items" => mcpObjectSchema(mcpVocabularySchema()["properties"] + array(
          "description" => array("type" => "string"),
          "terms" => array("type" => "integer", "description" => "How many terms it has, not counting synonyms")
        )))
      )),
      "annotations" => $readOnly
    ),
    array(
      "name" => "list_terms",
      "title" => "List terms",
      "description" => "List the terms in a controlled vocabulary, or the site's own terms, which aren't in one, ".MCP_TERMS_PAGE." at a "
        ."time in order of name. Synonyms are given with the terms they are synonyms of. Definitions are shortened here: use get_term "
        ."for a term's full definition.",
      "inputSchema" => array(
        "type" => "object",
        "properties" => array(
          "vocabulary" => array("type" => "string", "description" => "The vocabulary's short name, as list_vocabularies gives it. "
            ."Leave it out for the site's own terms."),
          "offset" => array("type" => "integer", "minimum" => 0, "default" => 0,
            "description" => "How many terms to skip: 0 for the first terms, then next_offset from the terms before")
        )
      ),
      "outputSchema" => mcpObjectSchema(array(
        "vocabulary" => mcpVocabularySchema(),
        "terms" => $terms,
        "total" => array("type" => "integer", "description" => "How many terms there are in all"),
        "offset" => array("type" => "integer"),
        "next_offset" => array("type" => array("integer", "null"), "description" => "The offset of the next terms, or null if there are no more")
      )),
      "annotations" => $readOnly
    )
  ));
}

//A JSON Schema for an object that has all of these properties
function mcpObjectSchema($properties) {
  return(array("type" => "object", "properties" => $properties, "required" => array_keys($properties)));
}

//A JSON Schema that also allows null
function mcpNullable($schema) {
  $schema["type"] = array($schema["type"], "null");
  return($schema);
}

function mcpTermTypeSchema() {
  return(array("type" => "string", "enum" => termTypes(),
    "description" => "concept, or property (a characteristic that is measured or recorded) or class (a kind of thing)"));
}

//A term another term links to (see mcpTermLink())
function mcpTermLinkSchema() {
  return(mcpObjectSchema(array("name" => array("type" => "string"), "uri" => array("type" => "string"))));
}

//A vocabulary (see mcpVocabulary())
function mcpVocabularySchema() {
  return(mcpObjectSchema(array(
    "shortname" => array("type" => array("string", "null"), "description" => "null for the site's own terms, which aren't in a controlled vocabulary"),
    "name" => array("type" => "string"),
    "uri" => array("type" => "string")
  )));
}

//A term where several are listed (see mcpTermSummary())
function mcpTermSummarySchema() {
  return(mcpObjectSchema(array(
    "name" => array("type" => "string"),
    "shortname" => array("type" => "string"),
    "uri" => array("type" => "string", "description" => "The URI that identifies the term"),
    "acronym" => array("type" => array("string", "null")),
    "type" => mcpTermTypeSchema(),
    "vocabulary" => array("type" => array("string", "null"), "description" => "The name of the term's controlled vocabulary, or null for the site's own terms"),
    "definition" => array("type" => "string", "description" => "The definition as plain text, shortened to at most ".MCP_SUMMARY_LENGTH." characters"),
    "synonyms" => array("type" => "array", "items" => array("type" => "string"), "description" => "The names of the term's synonyms")
  )));
}

//The result of a tools/call request as array("result" => the result), or array("error" => array("code" => ..., "message" => ...))
//if the request doesn't call a tool the server has
function mcpToolsCall($params) {
  if (!isset($params["name"]) || !is_string($params["name"])) {
    return(array("error" => array("code" => -32602, "message" => "Invalid params: tools/call needs the name of a tool.")));
  }
  if (isset($params["arguments"]) && !is_array($params["arguments"])) {
    return(array("error" => array("code" => -32602, "message" => "Invalid params: the arguments must be an object.")));
  }
  $result = mcpCallTool($params["name"], isset($params["arguments"]) ? $params["arguments"] : array());
  if ($result === null) {
    return(array("error" => array("code" => -32602, "message" => "Unknown tool: ".$params["name"])));
  }
  return(array("result" => $result));
}

//The result of calling a tool with its arguments, or NULL if there is no tool with that name. Arguments that can't be used, and
//terms or vocabularies that aren't found, give a result that is an error, which says what to change.
function mcpCallTool($name, $arguments) {
  switch ($name) {
    case "search_terms":
      return(mcpSearchTerms($arguments));
    case "get_term":
      return(mcpGetTerm($arguments));
    case "list_vocabularies":
      return(mcpListVocabularies());
    case "list_terms":
      return(mcpListTerms($arguments));
  }
  return(null);
}

//A tool's result: data, which matches the tool's output schema, given as JSON text too for clients that only read text
function mcpToolResult($data) {
  return(array(
    "content" => array(array("type" => "text", "text" => toJSON($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))),
    "structuredContent" => $data,
    "isError" => FALSE
  ));
}

function mcpToolError($text) {
  return(array("content" => array(array("type" => "text", "text" => $text)), "isError" => TRUE));
}

//A whole number given as an argument, or NULL if the argument isn't one. JSON numbers such as 10.0 are whole numbers too.
function mcpWholeNumber($value) {
  if (is_int($value)) {
    return($value);
  }
  if (is_float($value) && floor($value) == $value && abs($value) < PHP_INT_MAX) {
    return((int)$value);
  }
  return(null);
}

function mcpSearchTerms($arguments) {
  $query = (isset($arguments["query"]) && is_string($arguments["query"])) ? searchText($arguments["query"]) : "";
  if ($query === "") {
    return(mcpToolError("Give the text to search for as the query."));
  }
  $limit = isset($arguments["limit"]) ? mcpWholeNumber($arguments["limit"]) : 10;
  if ($limit === null || $limit < 1 || $limit > MCP_SEARCH_LIMIT) {
    return(mcpToolError("The limit must be a whole number from 1 to ".MCP_SEARCH_LIMIT."."));
  }
  //One more term than the limit shows whether there are more
  $rows = searchTerms($query, $limit + 1);
  return(mcpToolResult(array(
    "terms" => array_map("mcpTermSummary", array_slice($rows, 0, $limit)),
    "more" => count($rows) > $limit
  )));
}

function mcpGetTerm($arguments) {
  if (isset($arguments["uri"]) && is_string($arguments["uri"]) && $arguments["uri"] !== "") {
    $term = Term::findByURI($arguments["uri"]);
    $missing = "No term has the URI ".$arguments["uri"].". Give a term's URI exactly as the other tools give it, or its shortname.";
  } elseif (isset($arguments["shortname"]) && is_string($arguments["shortname"]) && $arguments["shortname"] !== "") {
    $term = Term::find($arguments["shortname"]);
    $missing = "No term has the short name ".$arguments["shortname"].".";
  } else {
    return(mcpToolError("Give the term's uri, or its shortname."));
  }
  if ($term === null) {
    return(mcpToolError($missing." Use search_terms to find terms by name."));
  }
  Term::loadRelations(array($term));

  //A synonym's parent is the term it is a synonym of. Other parent and child terms are related terms, as the site shows them.
  $parent = $term->parent();
  $synonyms = array();
  $related = array();
  foreach ($term->children() as $child) {
    if ($child->isSynonym()) {
      $synonyms[] = mcpTermLink($child);
    } else {
      $related[] = mcpTermLink($child);
    }
  }
  if ($parent !== null && !$term->isSynonym()) {
    $related[] = mcpTermLink($parent);
  }
  foreach ($term->related() as $relatedTerm) {
    $related[] = mcpTermLink($relatedTerm);
  }

  //Where a property's values come from
  $values = null;
  $datatypes = termDatatypes();
  if ($term->type == "property" && isset($datatypes[(string)$term->datatype])) {
    $values = array("datatype" => $term->datatype, "vocabulary" => null);
  } elseif ($term->type == "property" && $term->rangeCV != null) {
    $values = array("datatype" => null, "vocabulary" => mcpVocabulary($term->rangeCV));
  }
  $dates = array();
  foreach (array("created" => $term->created, "modified" => $term->modified) as $key => $datetime) {
    $date = jsonLDDate($datetime);
    $dates[$key] = ($date === null) ? null : $date["@value"];
  }
  $broader = $term->broader();

  return(mcpToolResult(array(
    "uri" => $term->uri(),
    "shortname" => (string)$term->shortname,
    "name" => mcpTermName($term),
    "acronym" => ($term->acronym != "") ? (string)$term->acronym : null,
    "type" => $term->type,
    "language" => ($term->language != "") ? (string)$term->language : null,
    "definition" => plainText($term->description),
    "references" => array_map("plainText", referenceList($term->reference)),
    "vocabulary" => mcpVocabulary($term->cv),
    "deprecated" => $term->isDeprecated(),
    "synonym_of" => ($term->isSynonym() && $parent !== null) ? mcpTermLink($parent) : null,
    "synonyms" => $synonyms,
    "broader" => ($broader === null) ? null : mcpTermLink($broader),
    "narrower" => array_map("mcpTermLink", $term->narrower()),
    //A related term can also be the term's parent or child, but is only given once
    "related" => array_values(array_unique($related, SORT_REGULAR)),
    "values" => $values,
    "created" => $dates["created"],
    "modified" => $dates["modified"]
  )));
}

function mcpListVocabularies() {
  //Terms outside a vocabulary have no vocabulary, or an empty one, which are counted together
  $counts = array();
  $result = dbQuery("SELECT `cv`, COUNT(*) AS `count` FROM ".table("terms")." WHERE `invalid_reason` IS NULL GROUP BY `cv`;");
  foreach (($result) ? $result->fetch_all(MYSQLI_ASSOC) : array() as $row) {
    $key = (string)$row["cv"];
    $counts[$key] = (isset($counts[$key]) ? $counts[$key] : 0) + (int)$row["count"];
  }
  $vocabularies = array(mcpVocabulary(null) + array(
    "description" => plainText(configValue("description")),
    "terms" => isset($counts[""]) ? $counts[""] : 0
  ));
  $CVs = isset($GLOBALS["ontomasticon"]["CVs"]) ? $GLOBALS["ontomasticon"]["CVs"] : array();
  ksort($CVs, SORT_STRING);
  foreach ($CVs as $shortname => $CV) {
    $vocabularies[] = mcpVocabulary((string)$shortname) + array(
      "description" => plainText($CV["description"]),
      "terms" => isset($counts[(string)$shortname]) ? $counts[(string)$shortname] : 0
    );
  }
  return(mcpToolResult(array("vocabularies" => $vocabularies)));
}

function mcpListTerms($arguments) {
  if (isset($arguments["vocabulary"]) && !is_string($arguments["vocabulary"])) {
    return(mcpToolError("The vocabulary must be a vocabulary's short name."));
  }
  $shortname = (isset($arguments["vocabulary"]) && $arguments["vocabulary"] !== "") ? $arguments["vocabulary"] : null;
  if ($shortname !== null && !isset($GLOBALS["ontomasticon"]["CVs"][$shortname])) {
    return(mcpToolError("There is no controlled vocabulary with the short name ".$shortname.". Use list_vocabularies to list them."));
  }
  $offset = isset($arguments["offset"]) ? mcpWholeNumber($arguments["offset"]) : 0;
  if ($offset === null || $offset < 0) {
    return(mcpToolError("The offset must be a whole number: 0 for the first terms, or next_offset from the terms before."));
  }
  //Synonyms are given with the terms they are synonyms of, as they are on the site's pages
  $where = (($shortname === null) ? "(`cv` IS NULL OR `cv` = '')" : "`cv` = ?")." AND `invalid_reason` IS NULL";
  $params = ($shortname === null) ? array() : array($shortname);
  $result = dbQuery("SELECT COUNT(*) AS `count` FROM ".table("terms")." WHERE ".$where.";", $params);
  $total = ($result) ? (int)$result->fetch_assoc()["count"] : 0;
  $result = dbQuery("SELECT * FROM ".table("terms")." WHERE ".$where." ORDER BY `name`, `shortname` LIMIT ".MCP_TERMS_PAGE." OFFSET ".$offset.";", $params);
  $rows = ($result) ? $result->fetch_all(MYSQLI_ASSOC) : array();
  return(mcpToolResult(array(
    "vocabulary" => mcpVocabulary($shortname),
    "terms" => array_map("mcpTermSummary", withTermRelations($rows)),
    "total" => $total,
    "offset" => $offset,
    "next_offset" => ($offset + MCP_TERMS_PAGE < $total) ? $offset + MCP_TERMS_PAGE : null
  )));
}

//A term's name, or its short name if it has none
function mcpTermName($term) {
  return((trim((string)$term->name) == "") ? (string)$term->shortname : (string)$term->name);
}

//A term another term links to, as its name and URI
function mcpTermLink($term) {
  return(array("name" => mcpTermName($term), "uri" => $term->uri()));
}

//A vocabulary, as its short name, name and URI. A NULL short name gives the site's own terms, which aren't in a vocabulary.
function mcpVocabulary($shortname) {
  if ($shortname === null || $shortname === "") {
    return(array("shortname" => null, "name" => plainText(configValue("site_name")), "uri" => siteURL()));
  }
  $CVs = isset($GLOBALS["ontomasticon"]["CVs"]) ? $GLOBALS["ontomasticon"]["CVs"] : array();
  $name = (isset($CVs[$shortname]) && $CVs[$shortname]["name"] != "") ? (string)$CVs[$shortname]["name"] : (string)$shortname;
  return(array("shortname" => (string)$shortname, "name" => $name, "uri" => (new Vocabulary($shortname))->uri()));
}

//A row of the terms table with its related terms (see withTermRelations()), where tools list several terms
function mcpTermSummary($row) {
  $synonyms = array();
  foreach ($row["children"] as $child) {
    if ($child["invalid_reason"] == "Synonym") {
      $synonyms[] = glossaryLabel($child);
    }
  }
  return(array(
    "name" => glossaryLabel($row),
    "shortname" => (string)$row["shortname"],
    "uri" => term2URI($row),
    "acronym" => (isset($row["acronym"]) && $row["acronym"] != "") ? (string)$row["acronym"] : null,
    "type" => termType(isset($row["type"]) ? $row["type"] : null),
    "vocabulary" => ($row["cv"] != "") ? mcpVocabulary($row["cv"])["name"] : null,
    "definition" => shortText(plainText($row["description"]), MCP_SUMMARY_LENGTH),
    "synonyms" => $synonyms
  ));
}
