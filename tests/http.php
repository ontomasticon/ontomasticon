<?php
// HTTP tests: runs the site in PHP's built-in web server and sends it real requests.
// Runs after the database tests, and needs settings/db.php to connect to the test database.

section("HTTP setup");

//The database settings/db.php connects to, so these tests never change a real site's data
function siteDatabaseName() {
  if (!file_exists("settings/db.php")) {
    return(null);
  }
  include("settings/db.php");
  if (!isset($db) || $db->connect_error) {
    return(null);
  }
  $result = $db->query("SELECT DATABASE();");
  return(($result) ? $result->fetch_row()[0] : null);
}

if ($db->connect_error || siteDatabaseName() !== getenv("TEST_DB_NAME")) {
  failTest("settings/db.php must connect to the test database (".getenv("TEST_DB_NAME").") to run the HTTP tests");
  return;
}

resetDatabase("inst/ontomasticon.sql");
//Skip the daily update check, which would contact GitHub
$db->query("UPDATE `config` SET `value` = UNIX_TIMESTAMP() WHERE `key` = 'update_check';");
$db->query("INSERT INTO `terms` (`shortname`, `name`, `language`, `opaque`) VALUES ('acoustic_allometry', 'Acoustic allometry', 'en', 0), ('opaque_term', 'Opaque term', 'en', 1);");

define("HTTP_PORT", getenv("TEST_HTTP_PORT") ? getenv("TEST_HTTP_PORT") : "8765");
$serverLog = sys_get_temp_dir()."/ontomasticon-http-tests.log";
$command = "exec ".escapeshellarg(PHP_BINARY)." -d display_errors=1 -d error_reporting=-1 -S 127.0.0.1:".HTTP_PORT." tests/router.php";
$server = proc_open($command, array(0 => array("file", "/dev/null", "r"), 1 => array("file", $serverLog, "w"), 2 => array("file", $serverLog, "a")), $pipes);
register_shutdown_function(function() use (&$server) {
  if (is_resource($server)) {
    proc_terminate($server);
  }
});

$ready = FALSE;
for ($i = 0; $i < 50 && !$ready; $i++) {
  $socket = @fsockopen("127.0.0.1", HTTP_PORT, $errno, $errstr, 0.2);
  if ($socket) {
    fclose($socket);
    $ready = TRUE;
  } else {
    usleep(100000);
  }
}
check("PHP's built-in web server starts", $ready);
if (!$ready) {
  print file_get_contents($serverLog);
  return;
}

//Send a request to the test server, with any extra headers, keeping the session cookie between requests.
//Returns array(status, response headers, body). PHP errors in the page count as failures.
function httpRequest($method, $path, $fields = null, $extraHeaders = array()) {
  $headers = $extraHeaders;
  if (isset($GLOBALS["http_cookie"])) {
    $headers[] = "Cookie: ".$GLOBALS["http_cookie"];
  }
  $options = array("method" => $method, "ignore_errors" => TRUE, "follow_location" => 0, "timeout" => 10);
  if ($fields !== null) {
    $headers[] = "Content-Type: application/x-www-form-urlencoded";
    $options["content"] = http_build_query($fields);
  }
  $options["header"] = implode("\r\n", $headers);

  $body = @file_get_contents("http://127.0.0.1:".HTTP_PORT.$path, FALSE, stream_context_create(array("http" => $options)));
  if ($body === FALSE) {
    failTest("No response to ".$method." ".$path);
    return(array(0, array(), ""));
  }
  $responseHeaders = function_exists("http_get_last_response_headers") ? http_get_last_response_headers() : $http_response_header;

  preg_match('#^HTTP/\S+ (\d+)#', $responseHeaders[0], $matches);
  foreach ($responseHeaders as $header) {
    if (preg_match('/^Set-Cookie: (PHPSESSID=[^;]+)/i', $header, $cookie)) {
      $GLOBALS["http_cookie"] = $cookie[1];
    }
  }
  if (preg_match('/(Warning|Notice|Fatal error|Parse error)(<\/b>)?: .*/', $body, $error)) {
    failTest("PHP error on ".$method." ".$path.": ".strip_tags($error[0]));
  }
  return(array((int)$matches[1], $responseHeaders, $body));
}

function hasHeader($headers, $pattern) {
  return(count(preg_grep($pattern, $headers)) > 0);
}

section("HTTP: pages");
list($status, $headers, $body) = httpRequest("GET", "/");
checkSame("home page loads", 200, $status);
check("home page closes its head element", strpos($body, "</head>") !== FALSE);
check("session cookie is HttpOnly and SameSite=Lax",
  hasHeader($headers, '/^Set-Cookie: PHPSESSID=.*HttpOnly/i') && hasHeader($headers, '/^Set-Cookie: PHPSESSID=.*SameSite=Lax/i'));
list($status) = httpRequest("GET", "/css/default.css");
checkSame("serves static files directly", 200, $status);
list(, , $body) = httpRequest("GET", "/ping");
checkSame("ping", "pong", $body);
list(, , $body) = httpRequest("GET", "/cv");
check("/cv without a name lists the vocabularies", strpos($body, "There are no controlled vocabularies yet") !== FALSE);
list(, , $body) = httpRequest("GET", "/cv/nonexistent");
check("an unknown vocabulary says so", strpos($body, "No matching controlled vocabulary found for nonexistent") !== FALSE);
list(, , $body) = httpRequest("GET", "/?lang=".rawurlencode("'><script>alert(1)</script>"));
check("a script in the lang parameter is not echoed into the page", strpos($body, "<script>alert(1)") === FALSE);
list(, , $body) = httpRequest("GET", "/?lang=xx");
check("an unknown language still shows the interface text", strpos($body, "Powered by") !== FALSE);

section("HTTP: API");
list($status, $headers, $body) = httpRequest("GET", "/api/term/?shortname=acoustic_allometry");
$term = json_decode($body, TRUE);
check("returns JSON", hasHeader($headers, '#^Content-Type: application/json#i'));
checkSame("returns the term with values as strings", "1", is_array($term) ? $term["id"] : null);
checkSame("includes the term's URL", "https://glossary.example.org/acoustic_allometry", is_array($term) ? $term["url"] : null);
list(, , $body) = httpRequest("GET", "/api/term/?shortname=missing");
checkSame("returns null for a missing term", "null", $body);
list(, , $body) = httpRequest("GET", "/api/term/?term=".rawurlencode("https://glossary.example.org/acoustic_allometry"));
$term = json_decode($body, TRUE);
checkSame("finds a term from its URL", "acoustic_allometry", is_array($term) ? $term["shortname"] : null);
list(, , $body) = httpRequest("GET", "/api/term/?term=".rawurlencode("https://glossary.example.org/2"));
$term = json_decode($body, TRUE);
checkSame("finds an opaque term from the id in its URL", "opaque_term", is_array($term) ? $term["shortname"] : null);
list(, , $body) = httpRequest("GET", "/api/term/?term=".rawurlencode("https://glossary.example.org/1"));
checkSame("doesn't find a term that isn't opaque by its id", "null", $body);
$db->query("INSERT INTO `terms` (`shortname`, `name`, `description`, `language`, `opaque`) VALUES ('agreement_song', 'Agreement song', 'The female’s response', 'en', 0);");
list(, , $body) = httpRequest("GET", "/api/term/?shortname=agreement_song");
$term = json_decode($body, TRUE);
checkSame("returns curly quotes intact", "The female’s response", is_array($term) ? $term["description"] : null);

section("HTTP: JSON-LD");
list($status, $headers, $body) = httpRequest("GET", "/api/term/?shortname=acoustic_allometry&format=jsonld");
$concept = json_decode($body, TRUE);
check("returns JSON-LD", $status == 200 && hasHeader($headers, '#^Content-Type: application/ld\+json#i'));
checkSame("identifies the term by its URI", "https://glossary.example.org/acoustic_allometry", is_array($concept) ? $concept["@id"] : null);
checkSame("describes the term as a SKOS concept", "skos:Concept", is_array($concept) ? $concept["@type"] : null);
list(, , $body) = httpRequest("GET", "/api/term/?term=".rawurlencode("https://glossary.example.org/2")."&format=jsonld");
$concept = json_decode($body, TRUE);
checkSame("finds an opaque term from its URL", "https://glossary.example.org/2", is_array($concept) ? $concept["@id"] : null);
list($status, , $body) = httpRequest("GET", "/api/term/?shortname=missing&format=jsonld");
check("a missing term is not found", $status == 404 && $body == "null");

section("HTTP: vocabularies");
$db->query("INSERT INTO `cv` (`shortname`, `name`, `description`, `reference`) VALUES ('calls', 'Calls', '<p>Types of call.</p>', '');");
$db->query("INSERT INTO `terms` (`shortname`, `name`, `language`, `opaque`, `cv`) VALUES ('calling_song', 'Calling song', 'en', 0, 'calls');");
$db->query("INSERT INTO `terms` (`shortname`, `name`, `language`, `opaque`, `cv`, `broader`) SELECT 'rivalry_call', 'Rivalry call', 'en', 0, 'calls', `id` FROM `terms` WHERE `shortname` = 'calling_song';");
list($status, $headers, $body) = httpRequest("GET", "/api/cv/?shortname=calls");
$ld = json_decode($body, TRUE);
$graph = (is_array($ld) && isset($ld["@graph"])) ? $ld["@graph"] : array(array("@id" => null, "@type" => null));
check("returns a vocabulary as JSON-LD", $status == 200 && hasHeader($headers, '#^Content-Type: application/ld\+json#i'));
checkSame("as a concept scheme at the vocabulary's address",
  array("https://glossary.example.org/cv/calls", "skos:ConceptScheme"), array($graph[0]["@id"], $graph[0]["@type"]));
checkSame("with its terms", array("https://glossary.example.org/cv/calls#calling_song", "https://glossary.example.org/cv/calls#rivalry_call"),
  array_column(array_slice($graph, 1), "@id"));
checkSame("and its top concepts", array(array("@id" => "https://glossary.example.org/cv/calls#calling_song")),
  isset($graph[0]["skos:hasTopConcept"]) ? $graph[0]["skos:hasTopConcept"] : null);
list($status, , $body) = httpRequest("GET", "/api/cv/");
$ld = json_decode($body, TRUE);
$graph = (is_array($ld) && isset($ld["@graph"])) ? $ld["@graph"] : array(array("@id" => null));
checkSame("without a short name, returns the site's own scheme", "https://glossary.example.org/", $graph[0]["@id"]);
check("with the terms that aren't in a vocabulary", in_array("https://glossary.example.org/acoustic_allometry", array_column($graph, "@id"))
  && !in_array("https://glossary.example.org/cv/calls#calling_song", array_column($graph, "@id")));
list($status, , $body) = httpRequest("GET", "/api/cv/?shortname=missing");
check("a missing vocabulary is not found", $status == 404 && $body == "null");
$db->query("INSERT INTO `terms` (`shortname`, `name`, `language`, `opaque`, `cv`) VALUES ('opaque_call', 'Opaque call', 'en', 1, 'calls');");
$opaqueCall = getTerm("opaque_call");
list(, , $body) = httpRequest("GET", "/cv/calls");
check("the vocabulary page has an entry for each term, at the fragment of its URI",
  strpos($body, 'id="calling_song"') !== FALSE && strpos(term2URI(getTerm("calling_song")), "#calling_song") !== FALSE);
check("an opaque term's entry is at its id, as its URI is",
  strpos($body, 'id="'.$opaqueCall["id"].'"') !== FALSE && strpos($body, 'id="opaque_call"') === FALSE
  && term2URI($opaqueCall) === "https://glossary.example.org/cv/calls#".$opaqueCall["id"]);

section("HTTP: content negotiation");
$asJSONLD = array("Accept: application/ld+json");
list($status, $headers, $body) = httpRequest("GET", "/acoustic_allometry", null, $asJSONLD);
$concept = json_decode($body, TRUE);
check("a term's address returns JSON-LD to a client that asks for it", $status == 200 && hasHeader($headers, '#^Content-Type: application/ld\+json#i'));
checkSame("describing the term", "https://glossary.example.org/acoustic_allometry", is_array($concept) ? $concept["@id"] : null);
check("and says the response depends on the Accept header", hasHeader($headers, '/^Vary: .*Accept/i'));
list($status, $headers, $body) = httpRequest("GET", "/acoustic_allometry");
check("browsers still get the HTML page there", $status == 200 && hasHeader($headers, '#^Content-Type: text/html#i') && strpos($body, "</head>") !== FALSE);
check("which also varies by Accept header", hasHeader($headers, '/^Vary: .*Accept/i'));
check("and links to the term's JSON-LD",
  strpos($body, '<link rel="alternate" type="application/ld+json" href="/api/term/?term=https%3A%2F%2Fglossary.example.org%2Facoustic_allometry&amp;format=jsonld"') !== FALSE);
list(, , $body) = httpRequest("GET", "/2", null, $asJSONLD);
$concept = json_decode($body, TRUE);
checkSame("an opaque term's address uses its id", "https://glossary.example.org/2", is_array($concept) ? $concept["@id"] : null);
list($status, , $body) = httpRequest("GET", "/1", null, $asJSONLD);
check("a term that isn't opaque isn't found at its id", $status == 404 && $body == "null");
list($status) = httpRequest("GET", "/calling_song", null, $asJSONLD);
checkSame("a term in a vocabulary isn't found outside it", 404, $status);
list($status, , $body) = httpRequest("GET", "/no_such_term", null, $asJSONLD);
check("an unknown term is not found", $status == 404 && $body == "null");
list(, , $body) = httpRequest("GET", "/cv/calls", null, $asJSONLD);
$ld = json_decode($body, TRUE);
checkSame("a vocabulary's address returns its scheme", "https://glossary.example.org/cv/calls",
  (is_array($ld) && isset($ld["@graph"])) ? $ld["@graph"][0]["@id"] : null);
list(, , $body) = httpRequest("GET", "/", null, $asJSONLD);
$ld = json_decode($body, TRUE);
checkSame("the site's address returns the site's own scheme", "https://glossary.example.org/",
  (is_array($ld) && isset($ld["@graph"])) ? $ld["@graph"][0]["@id"] : null);
list($status, $headers) = httpRequest("GET", "/cv/calls?format=jsonld");
check("?format=jsonld works at the same addresses", $status == 200 && hasHeader($headers, '#^Content-Type: application/ld\+json#i'));
list(, $headers, $body) = httpRequest("GET", "/cv/calls", null, array("Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"));
check("a browser's Accept header gets the vocabulary page", hasHeader($headers, '#^Content-Type: text/html#i') && strpos($body, "Controlled Vocabulary: Calls") !== FALSE);
list(, , $body) = httpRequest("GET", "/ping", null, $asJSONLD);
checkSame("other addresses aren't affected", "pong", $body);

section("HTTP: Turtle");
$asTurtle = array("Accept: text/turtle");
list($status, $headers, $body) = httpRequest("GET", "/acoustic_allometry", null, $asTurtle);
check("a term's address returns Turtle to a client that asks for it",
  $status == 200 && hasHeader($headers, '#^Content-Type: text/turtle#i') && hasHeader($headers, '/^Vary: .*Accept/i'));
check("describing the term", strpos($body, "<https://glossary.example.org/acoustic_allometry> a skos:Concept ;") !== FALSE);
check("with the prefixes it uses", strpos($body, "@prefix skos: <http://www.w3.org/2004/02/skos/core#> .") !== FALSE);
list(, $headers, $body) = httpRequest("GET", "/cv/calls", null, array("Accept: text/turtle;q=0.9, application/ld+json;q=0.5"));
check("a vocabulary's address returns Turtle when it is preferred to JSON-LD",
  hasHeader($headers, '#^Content-Type: text/turtle#i') && strpos($body, "<https://glossary.example.org/cv/calls> a skos:ConceptScheme ;") !== FALSE);
list(, $headers) = httpRequest("GET", "/cv/calls", null, array("Accept: text/turtle, application/ld+json"));
check("and JSON-LD when both are equally acceptable", hasHeader($headers, '#^Content-Type: application/ld\+json#i'));
list($status, $headers, $body) = httpRequest("GET", "/no_such_term", null, $asTurtle);
check("an unknown term is not found, with an empty Turtle document",
  $status == 404 && hasHeader($headers, '#^Content-Type: text/turtle#i') && $body === "");
list($status, $headers, $body) = httpRequest("GET", "/api/term/?shortname=agreement_song&format=ttl");
check("the term API returns Turtle with format=ttl", $status == 200 && hasHeader($headers, '#^Content-Type: text/turtle#i')
  && strpos($body, 'rdfs:comment "The female’s response"@en') !== FALSE);
list($status, , $body) = httpRequest("GET", "/api/term/?shortname=missing&format=ttl");
check("a missing term is not found in Turtle", $status == 404 && $body === "");
list($status, $headers, $body) = httpRequest("GET", "/api/cv/?format=ttl");
check("the vocabulary API returns Turtle with format=ttl", $status == 200 && hasHeader($headers, '#^Content-Type: text/turtle#i')
  && strpos($body, "<https://glossary.example.org/> a skos:ConceptScheme ;") !== FALSE);
list(, $headers, $body) = httpRequest("GET", "/api/term/?shortname=acoustic_allometry");
check("the term API still returns JSON by default", hasHeader($headers, '#^Content-Type: application/json#i') && is_array(json_decode($body, TRUE)));
list(, , $body) = httpRequest("GET", "/");
check("pages link to their Turtle too", strpos($body, '<link rel="alternate" type="text/turtle" href="/api/cv/?format=ttl"') !== FALSE);

section("HTTP: logging in and forms");
list(, , $body) = httpRequest("GET", "/user/login");
preg_match("/name='csrf_token' value='([0-9a-f]{64})'/", $body, $matches);
$token = isset($matches[1]) ? $matches[1] : "";
check("the login form carries a CSRF token", $token != "");
check("the login form posts back to the requested address", strpos($body, '<form action="/user/login"') !== FALSE);

list(, , $body) = httpRequest("POST", "/user/login", array("email" => "admin", "password" => "password", "submit" => ""));
check("rejects a form without the CSRF token", strpos($body, "The form could not be verified") !== FALSE && strpos($body, "Logged in as") === FALSE);
list(, , $body) = httpRequest("POST", "/user/login", array("csrf_token" => $token, "email" => "admin", "password" => "wrong", "submit" => ""));
check("rejects a wrong password", strpos($body, "Incorrect email address or password") !== FALSE);

list($status, $headers) = httpRequest("POST", "/user/login", array("csrf_token" => $token, "email" => "admin", "password" => "password", "submit" => ""));
check("logging in with the default password redirects to the settings page",
  $status == 302 && hasHeader($headers, '#^Location: /user/settings$#i'));
list($status) = httpRequest("GET", "/admin/config");
checkSame("other pages redirect until the password is changed", 302, $status);
list($status) = httpRequest("GET", "/api/term/?shortname=acoustic_allometry");
checkSame("the API still works meanwhile", 200, $status);
list(, , $body) = httpRequest("GET", "/user/settings");
check("the settings page asks for a new password", strpos($body, "You are using the default password") !== FALSE);
list(, , $body) = httpRequest("POST", "/user/settings", array(
  "csrf_token" => $token, "first_name" => "Site", "last_name" => "Admin",
  "old_password" => "password", "new_password1" => "n3w-secret", "new_password2" => "n3w-secret", "submit" => ""
));
check("changes the password", strpos($body, "Saved.") !== FALSE);

list($status, , $body) = httpRequest("GET", "/admin/config");
checkSame("admin pages open once the password is changed", 200, $status);
check("admin forms post back to the requested address", strpos($body, '<form action="/admin/config"') !== FALSE);
check("the configuration form has the publishing settings",
  strpos($body, 'name="publisher"') !== FALSE && strpos($body, 'name="license"') !== FALSE && strpos($body, 'name="prefix"') !== FALSE);
list(, , $body) = httpRequest("POST", "/admin/config", array(
  "csrf_token" => $token, "site_name" => "Test glossary", "author" => "Tester", "publisher" => "Test publisher",
  "default_lang" => "en", "base_url" => "glossary.example.org/", "description" => "Testing",
  "license" => "https://creativecommons.org/licenses/by/4.0/", "prefix" => "test", "submit" => ""
));
check("saves the site configuration", strpos($body, "Saved.") !== FALSE && strpos($body, "Test glossary") !== FALSE);
list(, , $body) = httpRequest("GET", "/api/cv/");
$ld = json_decode($body, TRUE);
checkSame("the site's scheme then gives the license", array("@id" => "https://creativecommons.org/licenses/by/4.0/"),
  (is_array($ld) && isset($ld["@graph"][0]["dcterms:license"])) ? $ld["@graph"][0]["dcterms:license"] : null);

list(, , $body) = httpRequest("POST", "/user/login", array("csrf_token" => $token, "logout" => ""));
check("logs out", strpos($body, "Logged out.") !== FALSE);
list(, , $body) = httpRequest("GET", "/admin/config");
check("admin pages are refused after logging out", strpos($body, "You do not have permission") !== FALSE);

proc_terminate($server);
proc_close($server);
