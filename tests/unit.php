<?php
// Unit tests for code that doesn't need a database

$GLOBALS["ontomasticon"]["config"] = array(
  "default_lang" => "en",
  "base_url" => "glossary.example.org/",
  "version" => $version
);

section("Escaping");
checkSame("h() escapes HTML and both kinds of quote", "&lt;a href=&#039;x&#039;&gt;&quot;&amp;", h("<a href='x'>\"&"));
checkSame("h() treats NULL as an empty string", "", h(null));
$_SERVER["REQUEST_URI"] = "/admin/term/edit/x'><script>";
checkSame("form actions are escaped", "/admin/term/edit/x&#039;&gt;&lt;script&gt;", formAction());

section("Table names");
checkSame("tables have no prefix by default", "`terms`", table("terms"));
$table_prefix = "site_";
checkSame("with a table prefix, table names start with it", "`site_terms`", table("terms"));
checkSame("SQL files get the prefix on the tables they create and fill, and nowhere else",
  "DROP TABLE IF EXISTS `site_config`;\nCREATE TABLE `site_cv` (\n  `cv` varchar(50)\n);\nINSERT INTO `site_users` (email) VALUES ('terms');",
  prefixTables("DROP TABLE IF EXISTS `config`;\nCREATE TABLE `cv` (\n  `cv` varchar(50)\n);\nINSERT INTO users (email) VALUES ('terms');"));
check("a prefix may use letters, digits and underscores, or be empty", validTablePrefix("Site_2") && validTablePrefix(""));
check("but nothing that could change the SQL", !validTablePrefix("a-b") && !validTablePrefix("x`; DROP") && !validTablePrefix("site\n"));
$table_prefix = null;

section("IP address ranges");
check("IPv4 address inside a /16", ipInRanges("192.168.1.5", array("192.168.0.0/16")));
check("IPv4 address outside a /16", !ipInRanges("192.169.0.1", array("192.168.0.0/16")));
check("prefix ending part-way through a byte, address inside", ipInRanges("173.245.63.255", array("173.245.48.0/20")));
check("prefix ending part-way through a byte, address outside", !ipInRanges("173.245.64.0", array("173.245.48.0/20")));
check("single address without a prefix length", ipInRanges("10.0.0.1", array("10.0.0.1")));
check("a different single address", !ipInRanges("10.0.0.2", array("10.0.0.1")));
check("IPv6 address inside a /32", ipInRanges("2400:cb00::1", array("2400:cb00::/32")));
check("IPv6 address outside a /32", !ipInRanges("2400:cb01::1", array("2400:cb00::/32")));
check("IPv4 address never matches an IPv6 range", !ipInRanges("10.0.0.1", array("::/0")));
check("any range in the list can match", ipInRanges("10.0.0.1", array("192.168.0.0/16", "10.0.0.0/8")));
check("an invalid address never matches", !ipInRanges("not-an-ip", array("0.0.0.0/0")));

section("Visitor IP address and HTTPS behind proxies");
$GLOBALS["trusted_proxies"] = null;
$_SERVER["REMOTE_ADDR"] = "203.0.113.7";
$_SERVER["HTTP_X_FORWARDED_FOR"] = "198.51.100.1";
checkSame("ignores X-Forwarded-For when no proxies are trusted", "203.0.113.7", clientIP());
$GLOBALS["trusted_proxies"] = array("10.0.0.0/8");
checkSame("ignores X-Forwarded-For from an untrusted address", "203.0.113.7", clientIP());
$_SERVER["REMOTE_ADDR"] = "10.0.0.1";
checkSame("reads the visitor's address through a trusted proxy", "198.51.100.1", clientIP());
$_SERVER["HTTP_X_FORWARDED_FOR"] = "192.0.2.66, 198.51.100.1";
checkSame("ignores addresses the visitor put in the header themselves", "198.51.100.1", clientIP());
$_SERVER["HTTP_X_FORWARDED_FOR"] = "198.51.100.1, 10.0.0.2";
checkSame("skips past a chain of trusted proxies", "198.51.100.1", clientIP());
$_SERVER["HTTP_X_FORWARDED_FOR"] = "garbage";
checkSame("uses the proxy's address if the header is invalid", "10.0.0.1", clientIP());

unset($_SERVER["HTTPS"]);
$_SERVER["HTTP_X_FORWARDED_PROTO"] = "https";
check("trusts X-Forwarded-Proto from a trusted proxy", requestIsHttps());
$_SERVER["REMOTE_ADDR"] = "203.0.113.7";
check("ignores X-Forwarded-Proto from an untrusted address", !requestIsHttps());
$_SERVER["HTTPS"] = "on";
check("a direct HTTPS connection", requestIsHttps());
$_SERVER["HTTPS"] = "off";
check("HTTPS set to off is not HTTPS", !requestIsHttps());
unset($_SERVER["HTTPS"], $_SERVER["HTTP_X_FORWARDED_FOR"], $_SERVER["HTTP_X_FORWARDED_PROTO"]);
$GLOBALS["trusted_proxies"] = null;

section("Site and term addresses");
checkSame("assumes https:// when base_url has no scheme", "https://glossary.example.org/", siteURL());
$GLOBALS["ontomasticon"]["config"]["base_url"] = "http://glossary.example.org/";
checkSame("keeps an http:// base_url", "http://glossary.example.org/", siteURL());
$GLOBALS["ontomasticon"]["config"]["base_url"] = "glossary.example.org/";
checkSame("term outside a vocabulary", "https://glossary.example.org/acoustic_allometry",
  term2URI(array("id" => 1, "shortname" => "acoustic_allometry", "cv" => null, "opaque" => 0)));
checkSame("term in a vocabulary", "https://glossary.example.org/cv/birds#song",
  term2URI(array("id" => 7, "shortname" => "song", "cv" => "birds", "opaque" => 0)));
checkSame("opaque term uses its id", "https://glossary.example.org/cv/birds#7",
  term2URI(array("id" => 7, "shortname" => "song", "cv" => "birds", "opaque" => 1)));
checkSame("a term's entry on the page is named by its shortname", "song",
  termAnchor(array("id" => 7, "shortname" => "song", "cv" => "birds", "opaque" => 0)));
checkSame("an opaque term's entry is named by its id, matching the fragment of its URI", "7",
  termAnchor(array("id" => 7, "shortname" => "song", "cv" => "birds", "opaque" => 1)));

section("Short names");
check("letters, digits, hyphens, underscores and full stops are allowed", validShortname("Bird_song-2.1"));
check("a space isn't allowed", !validShortname("odd term"));
check("nor characters that mean something in URIs",
  !validShortname("a/b") && !validShortname("a?b") && !validShortname("a#b") && !validShortname("a%20b"));
check("nor letters outside A to Z", !validShortname("café"));
check("nor a full stop at the start", !validShortname(".hidden") && !validShortname(".."));
check("nor a trailing newline", !validShortname("sound\n"));
check("nor an empty name", !validShortname(""));

section("Routing");
function routeFor($uri) {
  $_SERVER["REQUEST_URI"] = $uri;
  return(activePage());
}
checkSame("home page", array("page_type" => "home"), routeFor("/"));
checkSame("vocabulary page, ignoring the query string",
  array("page_type" => "cv", "active_page" => "birds"), routeFor("/cv/birds?lang=fr"));
checkSame("/cv with no vocabulary", array("page_type" => "cv", "active_page" => ""), routeFor("/cv"));
checkSame("admin page with sub-pages",
  array("page_type" => "admin", "active_page" => "term", "active_subpage" => "edit", "active_subsubpage" => "bird_song"),
  routeFor("/admin/term/edit/bird_song"));
checkSame("/admin with no sub-page",
  array("page_type" => "admin", "active_page" => "", "active_subpage" => null, "active_subsubpage" => null),
  routeFor("/admin"));
checkSame("/update opens the admin update page",
  array("page_type" => "admin", "active_page" => "update", "active_subpage" => null, "active_subsubpage" => null),
  routeFor("/update"));
checkSame("API endpoint", array("page_type" => "api", "active_page" => "term"), routeFor("/api/term/"));
checkSame("other files in settings/ go to the home page", array("page_type" => "home"), routeFor("/settings/db.php"));
checkSame("a term's own address, ignoring the query string",
  array("page_type" => "term", "active_page" => "acoustic_allometry"), routeFor("/acoustic_allometry?lang=fr"));
$routedElsewhere = array_values(array_filter(reservedRouteSegments(), function($segment) {
  return(routeFor("/".$segment."/")["page_type"] != "term");
}));
checkSame("reserved route segments don't go to a term's page", reservedRouteSegments(), $routedElsewhere);
$routing = new ReflectionFunction("activePage");
$routingSource = array_slice(file($routing->getFileName()), $routing->getStartLine() - 1, $routing->getEndLine() - $routing->getStartLine() + 1);
preg_match_all('/case "([^"]+)":/', implode("", $routingSource), $cases);
$routes = $cases[1];
sort($routes);
$segments = reservedRouteSegments();
sort($segments);
checkSame("and they are all of the routes in activePage()", $routes, $segments);

section("Reserved short names");
check("route segments", reservedTermShortname("api") && reservedTermShortname("settings"));
check("files and directories at the top of the install",
  reservedTermShortname("index.php") && reservedTermShortname("README.md") && reservedTermShortname("css") && reservedTermShortname("inst"));
check("in any case", reservedTermShortname("CSS") && reservedTermShortname("Admin"));
check("names ending in .php, which nginx passes to PHP", reservedTermShortname("glossary.php"));
check("but not other names", !reservedTermShortname("sound") && !reservedTermShortname("apis") && !reservedTermShortname("php"));

section("Content negotiation");
function formatFor($accept, $get = array()) {
  if ($accept === null) {
    unset($_SERVER["HTTP_ACCEPT"]);
  } else {
    $_SERVER["HTTP_ACCEPT"] = $accept;
  }
  $_GET = $get;
  return(requestedFormat());
}
checkSame("HTML when there is no Accept header", "html", formatFor(null));
checkSame("HTML for a browser", "html", formatFor("text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"));
checkSame("JSON-LD when it is asked for", "jsonld", formatFor("application/ld+json"));
checkSame("JSON-LD when it is preferred to HTML", "jsonld", formatFor("text/html;q=0.5, application/ld+json"));
checkSame("HTML when it is preferred to JSON-LD", "html", formatFor("application/ld+json;q=0.9, text/html"));
checkSame("HTML when both are equally acceptable", "html", formatFor("application/ld+json, text/html"));
checkSame("HTML when JSON-LD is refused with q=0", "html", formatFor("application/ld+json;q=0"));
checkSame("JSON-LD when it is preferred to anything else", "jsonld", formatFor("application/ld+json, */*;q=0.1"));
checkSame("media types and parameters are case-insensitive", "jsonld", formatFor("Application/LD+JSON; Q=1.0"));
checkSame("?format=jsonld asks for JSON-LD without an Accept header", "jsonld", formatFor(null, array("format" => "jsonld")));
unset($_SERVER["HTTP_ACCEPT"]);
$_GET = array();

function linkedDataFor($pageInfo) {
  $GLOBALS["ontomasticon"]["pageInfo"] = $pageInfo;
  return(linkedDataURL());
}
checkSame("the home page links to the site's scheme", "/api/cv/", linkedDataFor(array("page_type" => "home")));
checkSame("a vocabulary page links to the vocabulary's scheme", "/api/cv/?shortname=birds",
  linkedDataFor(array("page_type" => "cv", "active_page" => "birds")));
checkSame("the list of vocabularies has no JSON-LD", null, linkedDataFor(array("page_type" => "cv", "active_page" => "")));
checkSame("a term's address links to the term", "/api/term/?term=https%3A%2F%2Fglossary.example.org%2Facoustic_allometry&format=jsonld",
  linkedDataFor(array("page_type" => "term", "active_page" => "acoustic_allometry")));
checkSame("other pages have no JSON-LD", null, linkedDataFor(array("page_type" => "admin", "active_page" => "config")));
unset($GLOBALS["ontomasticon"]["pageInfo"]);

section("Languages");
$_GET = array("lang" => "jibberish");
checkSame("accepts a plain language code", "jibberish", detectLanguage());
$_GET = array("lang" => "pt-BR");
checkSame("accepts a language code with a region", "pt-BR", detectLanguage());
$_GET = array("lang" => "../settings/db");
checkSame("rejects a language code that could reach other files", "en", detectLanguage());
$_GET = array("lang" => "xx");
checkSame("a language without a file keeps the original text", "Controlled Vocabularies", t("Controlled Vocabularies"));
$_GET = array("lang" => "jibberish");
checkSame("translates using the language file", "Weird lists of words", t("Controlled Vocabularies"));
checkSame("keeps text the language file doesn't cover", "Delete", t("Delete"));
checkSame("links keep the language and escape their text and address",
  "<a href='/cv/a&#039;b?lang=jibberish'>&lt;b&gt;</a>", l("<b>", "/cv/a'b"));
$_GET = array();
unset($GLOBALS["ontomasticon"]["language_data"]);

section("Installed in a subdirectory");
checkSame("at the top of a domain the base path is empty", "", basePath());
$GLOBALS["ontomasticon"]["config"]["base_url"] = "glossary.example.org/terms/";
checkSame("the base path is the path in base_url", "/terms", basePath());
checkSame("the home page, with a trailing slash", array("page_type" => "home"), routeFor("/terms/"));
checkSame("the home page, without one", array("page_type" => "home"), routeFor("/terms"));
checkSame("routes by the path within the site",
  array("page_type" => "cv", "active_page" => "birds"), routeFor("/terms/cv/birds?lang=fr"));
checkSame("a term's own address", array("page_type" => "term", "active_page" => "song"), routeFor("/terms/song"));
checkSame("a path that only starts with the same letters isn't in the site",
  array("page_type" => "term", "active_page" => "termsong"), routeFor("/termsong"));
checkSame("links to the site's pages include the subdirectory", "<a href='/terms/cv/birds'>birds</a>", l("birds", "/cv/birds"));
checkSame("links to other sites don't", "<a href='https://example.org/'>x</a>", l("x", "https://example.org/"));
checkSame("linked data links include it", "/terms/api/cv/", linkedDataFor(array("page_type" => "home")));
checkSame("term URIs include it", "https://glossary.example.org/terms/cv/birds#song",
  term2URI(array("id" => 7, "shortname" => "song", "cv" => "birds", "opaque" => 0)));
unset($GLOBALS["ontomasticon"]["pageInfo"]);
$GLOBALS["ontomasticon"]["config"]["base_url"] = "glossary.example.org/";

section("CSRF tokens");
$_SESSION = array();
$token = csrfToken();
checkSame("token is 64 hex characters", 1, preg_match('/^[0-9a-f]{64}$/', $token));
checkSame("token stays the same within a session", $token, csrfToken());
check("the hidden form field carries the token", strpos(csrfField(), $token) !== FALSE);
$_POST = array("csrf_token" => $token);
check("accepts the session's token", csrfValid());
$_POST = array("csrf_token" => str_repeat("0", 64));
check("rejects a different token", !csrfValid());
$_POST = array("csrf_token" => array($token));
check("rejects a token sent as an array", !csrfValid());
$_POST = array();
check("rejects a missing token", !csrfValid());

section("Roles");
$roles = userRoles();
check("admin can do everything", in_array("*", $roles["administer"]["tasks"]));
check("editor can edit terms and vocabularies",
  in_array("edit-terms", $roles["editor"]["tasks"]) && in_array("edit-cvs", $roles["editor"]["tasks"]));
check("editor cannot create vocabularies", !in_array("create-cv", $roles["editor"]["tasks"]));
check("create-cv role can create vocabularies", in_array("create-cv", $roles["create-cv"]["tasks"]));
check("nobody but admin can delete vocabularies",
  !in_array("delete-cv", $roles["editor"]["tasks"]) && !in_array("delete-cv", $roles["create-cv"]["tasks"]));
checkSame("an unknown role has no name", "None", roleName("nonsense"));
check("the role dropdown marks the current role", strpos(roleSelect("editor"), "value='editor' selected") !== FALSE);

section("Plain text from HTML");
checkSame("removes tags and decodes entities", "Pulses & echemes", plainText("<b>Pulses</b> &amp; echemes"));
checkSame("separates paragraphs with a space", "First. Second.", plainText("<p>First.</p>\r\n\r\n<p>Second.</p>"));
checkSame("keeps escaped angle brackets as text", "a <b> tag", plainText("a &lt;b&gt; tag"));
checkSame("NULL gives an empty string", "", plainText(null));

section("JSON output");
if (!function_exists("json_encode")) {
  print "  JSON tests skipped: this PHP doesn't have the json extension.\n";
} else {
  checkSame("encodes values as JSON", '{"a":"b\/c"}', toJSON(array("a" => "b/c")));
  checkSame("replaces invalid UTF-8 instead of returning nothing", "female\xEF\xBF\xBDs", json_decode(toJSON("female\x92s")));
}

section("JSON-LD");
//A term with its relations set, so the database isn't needed
function testTerm($row, $related = array()) {
  $term = Term::fromRow($row + array("language" => "en", "opaque" => 0));
  foreach (array("broader" => null, "parent" => null, "narrower" => array(), "children" => array()) as $relation => $none) {
    $term->setRelated($relation, array_key_exists($relation, $related) ? $related[$relation] : $none);
  }
  return($term);
}
$premating = testTerm(array("id" => 2, "shortname" => "PrematingSong", "name" => "Premating Song", "cv" => "callType"));
$song = testTerm(array("id" => 1, "shortname" => "AgreementSong", "name" => "Agreement Song", "cv" => "callType", "broader" => 2,
  "description" => "<p>The female&rsquo;s response.</p>", "reference" => "Ragge and Reynolds 1998"), array("broader" => $premating));
$synonym = testTerm(array("id" => 3, "shortname" => "AttractionSong", "name" => "Attraction Song", "cv" => "callType",
  "parent" => 1, "invalid_reason" => "Synonym"), array("parent" => $song));
$response = testTerm(array("id" => 4, "shortname" => "ResponseCall", "name" => "Response Call", "cv" => "callType", "parent" => 1),
  array("parent" => $song));
$song->setRelated("children", array($synonym, $response));

$ld = termJSONLD($song);
checkSame("identifies a term in a vocabulary by its URI", "https://glossary.example.org/cv/callType#AgreementSong", $ld["@id"]);
checkSame("describes it as a SKOS concept", "skos:Concept", $ld["@type"]);
checkSame("declares the prefixes it uses", "http://www.w3.org/2004/02/skos/core#", $ld["@context"]["skos"]);
checkSame("labels it in its language", array("@value" => "Agreement Song", "@language" => "en"), $ld["skos:prefLabel"]);
checkSame("repeats the label as rdfs:label, which TDWG requires", $ld["skos:prefLabel"], $ld["rdfs:label"]);
checkSame("uses the shortname as the controlled value", "AgreementSong", $ld["rdf:value"]);
checkSame("gives the definition as plain text", array("@value" => "The female’s response.", "@language" => "en"), $ld["skos:definition"]);
checkSame("repeats the definition as rdfs:comment, which TDWG requires", $ld["skos:definition"], $ld["rdfs:comment"]);
checkSame("puts it in its vocabulary's scheme", array("@id" => "https://glossary.example.org/cv/callType"), $ld["skos:inScheme"]);
checkSame("links its broader term", array("@id" => "https://glossary.example.org/cv/callType#PrematingSong"), $ld["skos:broader"]);
check("a term with a broader term isn't a top concept", !isset($ld["skos:topConceptOf"]));
checkSame("gives synonyms as alternative labels", array(array("@value" => "Attraction Song", "@language" => "en")), $ld["skos:altLabel"]);
checkSame("links other child terms as related", array(array("@id" => "https://glossary.example.org/cv/callType#ResponseCall")), $ld["skos:related"]);
check("a valid term isn't deprecated", !isset($ld["owl:deprecated"]));
checkSame("gives a text reference as a citation", "Ragge and Reynolds 1998", $ld["dcterms:bibliographicCitation"]);

$ld = termJSONLD($synonym);
checkSame("a synonym is deprecated", TRUE, $ld["owl:deprecated"]);
checkSame("and replaced by the term it is a synonym of", array("@id" => "https://glossary.example.org/cv/callType#AgreementSong"), $ld["dcterms:isReplacedBy"]);
check("but not related to that term", !isset($ld["skos:related"]));
check("and isn't a top concept", !isset($ld["skos:topConceptOf"]));
checkSame("a child term that isn't a synonym is related to its parent", array(array("@id" => "https://glossary.example.org/cv/callType#AgreementSong")), termJSONLD($response)["skos:related"]);

$ld = termJSONLD(testTerm(array("id" => 5, "shortname" => "acoustic_allometry", "name" => "acoustic allometry",
  "description" => "", "language" => "", "reference" => "https://doi.org/10.1000/example")));
checkSame("a term outside a vocabulary is in the site's scheme", array("@id" => "https://glossary.example.org/"), $ld["skos:inScheme"]);
checkSame("and is a top concept of it", $ld["skos:inScheme"], $ld["skos:topConceptOf"]);
checkSame("a term without a language has a plain label", "acoustic allometry", $ld["skos:prefLabel"]);
check("an empty description gives no definition", !isset($ld["skos:definition"]) && !isset($ld["rdfs:comment"]));
checkSame("gives a web address reference as a source", array("@id" => "https://doi.org/10.1000/example"), $ld["dcterms:source"]);

section("JSON-LD vocabularies");
$callType = new Vocabulary("callType");
$callType->name = "Type of Call";
$callType->description = "<p>Calls with the same function.</p>";
$callType->reference = "Ragge and Reynolds 1998";
$ld = vocabularyJSONLD($callType, array($premating, $song, $synonym, $response));
$scheme = $ld["@graph"][0];
checkSame("declares the prefixes once, for the whole graph", "http://www.w3.org/2004/02/skos/core#", $ld["@context"]["skos"]);
checkSame("starts with the vocabulary as a concept scheme at its address",
  array("https://glossary.example.org/cv/callType", "skos:ConceptScheme"), array($scheme["@id"], $scheme["@type"]));
checkSame("titles the scheme in the site's language", array("@value" => "Type of Call", "@language" => "en"), $scheme["dcterms:title"]);
check("and labels it with the same text", $scheme["rdfs:label"] === $scheme["dcterms:title"] && $scheme["skos:prefLabel"] === $scheme["dcterms:title"]);
checkSame("describes the scheme in plain text", array("@value" => "Calls with the same function.", "@language" => "en"), $scheme["dcterms:description"]);
checkSame("gives the vocabulary's reference", "Ragge and Reynolds 1998", $scheme["dcterms:bibliographicCitation"]);
checkSame("follows it with every term, including deprecated ones", array(
  "https://glossary.example.org/cv/callType#PrematingSong", "https://glossary.example.org/cv/callType#AgreementSong",
  "https://glossary.example.org/cv/callType#AttractionSong", "https://glossary.example.org/cv/callType#ResponseCall"
), array_column(array_slice($ld["@graph"], 1), "@id"));
check("the terms don't repeat the prefixes", !isset($ld["@graph"][1]["@context"]));
checkSame("lists the valid terms without a broader term as top concepts", array(
  array("@id" => "https://glossary.example.org/cv/callType#PrematingSong"), array("@id" => "https://glossary.example.org/cv/callType#ResponseCall")
), $scheme["skos:hasTopConcept"]);

$GLOBALS["ontomasticon"]["config"]["site_name"] = "Bioacoustics Glossary";
$GLOBALS["ontomasticon"]["config"]["description"] = "Terms used in <i>bioacoustics</i>.";
$GLOBALS["ontomasticon"]["config"]["author"] = "Test Author";
$scheme = vocabularyJSONLD(Vocabulary::site(), array())["@graph"][0];
checkSame("the site's own scheme is at the site address", "https://glossary.example.org/", $scheme["@id"]);
checkSame("and is titled with the site name", array("@value" => "Bioacoustics Glossary", "@language" => "en"), $scheme["dcterms:title"]);
checkSame("and described with the site description", array("@value" => "Terms used in bioacoustics.", "@language" => "en"), $scheme["dcterms:description"]);
checkSame("credits the site's author as its creator", "Test Author", $scheme["dcterms:creator"]);
check("a scheme without terms has no top concepts", !isset($scheme["skos:hasTopConcept"]));
checkSame("a language that isn't a valid language tag is left out", "Calling Song", jsonLDText("Calling Song", "en_GB"));
checkSame("a language tag with a region is kept", array("@value" => "Canto", "@language" => "pt-BR"), jsonLDText("Canto", "pt-BR"));

section("Turtle");
checkSame("Turtle when it is asked for", "turtle", formatFor("text/turtle"));
checkSame("Turtle when it is preferred to JSON-LD", "turtle", formatFor("application/ld+json;q=0.5, text/turtle"));
checkSame("JSON-LD when it is as acceptable as Turtle", "jsonld", formatFor("text/turtle, application/ld+json"));
checkSame("HTML when it is as acceptable as Turtle", "html", formatFor("text/turtle, text/html"));
checkSame("?format=ttl asks for Turtle", "turtle", formatFor(null, array("format" => "ttl")));
checkSame("an unknown ?format is ignored", "html", formatFor(null, array("format" => "xml")));
unset($_SERVER["HTTP_ACCEPT"]);
$_GET = array();

$GLOBALS["ontomasticon"]["pageInfo"] = array("page_type" => "home");
checkSame("the home page links to the site's scheme in Turtle", "/api/cv/?format=ttl", linkedDataURL("turtle"));
$GLOBALS["ontomasticon"]["pageInfo"] = array("page_type" => "cv", "active_page" => "birds");
checkSame("a vocabulary page links to its scheme in Turtle", "/api/cv/?shortname=birds&format=ttl", linkedDataURL("turtle"));
$GLOBALS["ontomasticon"]["pageInfo"] = array("page_type" => "term", "active_page" => "acoustic_allometry");
checkSame("a term's address links to the term in Turtle",
  "/api/term/?term=https%3A%2F%2Fglossary.example.org%2Facoustic_allometry&format=ttl", linkedDataURL("turtle"));
unset($GLOBALS["ontomasticon"]["pageInfo"]);

checkSame("validUTF8() replaces invalid bytes", "female\xEF\xBF\xBDs", validUTF8("female\x92s"));
checkSame("and leaves valid text, including HTML, alone", "a &amp; <b>’", validUTF8("a &amp; <b>’"));

$expected = implode("\n", array(
  '@prefix skos: <http://www.w3.org/2004/02/skos/core#> .',
  '@prefix owl: <http://www.w3.org/2002/07/owl#> .',
  '',
  '<https://glossary.example.org/a> a skos:Concept ;',
  '    skos:prefLabel "A \"quoted\"\nlabel\\\\"@en ;',
  '    skos:altLabel "plain", "B"@en-GB ;',
  //A space in an IRI is written as a backslash, u and its code point, 0020
  '    skos:broader <https://glossary.example.org/b' . "\\" . 'u0020c> ;',
  '    owl:deprecated true .',
  ''
));
checkSame("writes the prefixes, then each subject with its properties, escaping strings and IRIs", $expected, turtleOutput(array(
  "@context" => array("skos" => "http://www.w3.org/2004/02/skos/core#", "owl" => "http://www.w3.org/2002/07/owl#"),
  "@id" => "https://glossary.example.org/a",
  "@type" => "skos:Concept",
  "skos:prefLabel" => array("@value" => "A \"quoted\"\nlabel\\", "@language" => "en"),
  "skos:altLabel" => array("plain", array("@value" => "B", "@language" => "en-GB")),
  "skos:broader" => array("@id" => "https://glossary.example.org/b c"),
  "owl:deprecated" => TRUE
)));
checkSame("replaces invalid UTF-8 in strings", '"female' . "\xEF\xBF\xBD" . 's"', turtleString("female\x92s"));
$turtle = turtleOutput(vocabularyJSONLD($callType, array($premating, $song, $synonym, $response)));
checkSame("writes a vocabulary as its scheme followed by each of its terms", 5, preg_match_all('/^</m', $turtle));
check("starting with the scheme", strpos($turtle, "\n<https://glossary.example.org/cv/callType> a skos:ConceptScheme ;\n") !== FALSE);
check("with the same values as the JSON-LD", strpos($turtle, '    skos:definition "The female’s response."@en ;') !== FALSE);

section("Dates and publishing settings");
checkSame("a database date and time gives an xsd:date", array("@value" => "2026-09-14", "@type" => "xsd:date"), jsonLDDate("2026-09-14 16:05:00"));
checkSame("no date gives nothing", null, jsonLDDate(null));
checkSame("nor does MariaDB's zero date", null, jsonLDDate("0000-00-00 00:00:00"));
$ld = termJSONLD(testTerm(array("id" => 6, "shortname" => "dated", "name" => "Dated",
  "created" => "2020-01-01 09:00:00", "modified" => "2026-09-14 16:05:00")));
checkSame("gives when a term was created and last modified",
  array(array("@value" => "2020-01-01", "@type" => "xsd:date"), array("@value" => "2026-09-14", "@type" => "xsd:date")),
  array($ld["dcterms:created"], $ld["dcterms:modified"]));
check("a term without dates has none", !isset(termJSONLD($premating)["dcterms:created"]) && !isset(termJSONLD($premating)["dcterms:modified"]));
$turtle = turtleOutput($ld);
check("Turtle writes the dates as typed values", preg_match('/^    dcterms:modified "2026-09-14"\^\^xsd:date [;.]$/m', $turtle) === 1);
check("with the xsd prefix declared", strpos($turtle, "@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .\n") !== FALSE);

$GLOBALS["ontomasticon"]["config"]["publisher"] = "Natural History Museum";
$GLOBALS["ontomasticon"]["config"]["license"] = "https://creativecommons.org/licenses/by/4.0/";
$GLOBALS["ontomasticon"]["config"]["prefix"] = "gl";
$callType = new Vocabulary("callType");
$callType->prefix = "calltype";
$scheme = vocabularyJSONLD($callType, array())["@graph"][0];
checkSame("a vocabulary's scheme gives the site's publisher", "Natural History Museum", $scheme["dcterms:publisher"]);
checkSame("and license", array("@id" => "https://creativecommons.org/licenses/by/4.0/"), $scheme["dcterms:license"]);
checkSame("and the vocabulary's namespace prefix and URI", array("calltype", "https://glossary.example.org/cv/callType#"),
  array($scheme["vann:preferredNamespacePrefix"], $scheme["vann:preferredNamespaceUri"]));
$scheme = vocabularyJSONLD(Vocabulary::site(), array())["@graph"][0];
checkSame("the site's own scheme uses the site's prefix, with the site address as its namespace", array("gl", "https://glossary.example.org/"),
  array($scheme["vann:preferredNamespacePrefix"], $scheme["vann:preferredNamespaceUri"]));
$GLOBALS["ontomasticon"]["config"]["license"] = "CC BY 4.0";
$scheme = vocabularyJSONLD(new Vocabulary("callType"), array())["@graph"][0];
check("a license that isn't a web address is left out", !isset($scheme["dcterms:license"]));
check("and a vocabulary without a prefix has no namespace", !isset($scheme["vann:preferredNamespacePrefix"]) && !isset($scheme["vann:preferredNamespaceUri"]));
unset($GLOBALS["ontomasticon"]["config"]["publisher"], $GLOBALS["ontomasticon"]["config"]["license"], $GLOBALS["ontomasticon"]["config"]["prefix"]);
checkSame("configValue() gives an empty string for a setting that isn't there", "", configValue("publisher"));
checkSame("prefixError() accepts a prefix that starts with a letter", null, prefixError("call-type_2"));
checkSame("and an empty prefix, meaning none", null, prefixError(""));
check("but refuses others", prefixError("2calls") !== null && prefixError("call type") !== null && prefixError(str_repeat("a", 21)) !== null);

section("Readiness report");
check("validLanguageTag() accepts en and pt-BR", validLanguageTag("en") && validLanguageTag("pt-BR"));
check("but not en_GB, an empty language or one with a trailing newline", !validLanguageTag("en_GB") && !validLanguageTag("") && !validLanguageTag("en\n"));
$readinessTerms = array(
  testTerm(array("id" => 20, "shortname" => "CallingSong", "name" => "Calling Song", "description" => "Produced by a male.", "cv" => "callType")),
  testTerm(array("id" => 21, "shortname" => "PrematingSong", "name" => "Premating Song", "description" => "<p></p>", "cv" => "callType")),
  testTerm(array("id" => 22, "shortname" => "Canto", "name" => "Canto", "description" => "A song.", "language" => "pt_BR", "cv" => "callType")),
  testTerm(array("id" => 23, "shortname" => "odd term", "name" => "Odd term", "description" => "Saved before short names were checked.")),
  testTerm(array("id" => 24, "shortname" => "api", "name" => "API", "description" => "Uses the address of the API.")),
  testTerm(array("id" => 25, "shortname" => "AgreementSong", "name" => "Agreement Song", "description" => "The female\x92s response.", "cv" => "callType")),
  testTerm(array("id" => 26, "shortname" => "AttractionSong", "name" => "Attraction Song", "description" => "A synonym.", "cv" => "callType", "invalid_reason" => "Synonym")),
  testTerm(array("id" => 27, "shortname" => "unnamed", "description" => "No name.", "cv" => "callType"))
);
$readinessVocabularies = array(
  "callType" => array("shortname" => "callType", "name" => "Type of Call", "prefix" => null),
  "unnamedcv" => array("shortname" => "unnamedcv", "name" => "", "prefix" => "unnamed")
);
$readiness = readinessIssues($readinessTerms, $readinessVocabularies, array("license" => "", "prefix" => ""));
$issues = array();
foreach ($readiness as $issue) {
  $issues[$issue["id"]] = array_column($issue["items"], "label");
}
checkSame("lists each kind of problem once, always in the same order",
  array("license", "prefix", "vocabulary-name", "term-name", "definition", "language", "shortname", "uri-clash", "utf8", "synonym"), array_keys($issues));
checkSame("reports a missing license", array("Site configuration"), $issues["license"]);
checkSame("the site's own terms and vocabularies without a namespace prefix", array("Terms that aren't in a controlled vocabulary", "callType"), $issues["prefix"]);
checkSame("a vocabulary without a name", array("unnamedcv"), $issues["vocabulary-name"]);
checkSame("a term without a name", array("callType: unnamed"), $issues["term-name"]);
checkSame("a term whose definition is empty once its HTML is removed", array("callType: PrematingSong"), $issues["definition"]);
checkSame("a language that isn't a valid language tag", array("callType: Canto"), $issues["language"]);
checkSame("a short name saved before short names were checked", array("odd term"), $issues["shortname"]);
checkSame("a URI that clashes with the site's own addresses", array("api"), $issues["uri-clash"]);
checkSame("text that isn't valid UTF-8", array("callType: AgreementSong"), $issues["utf8"]);
checkSame("and a synonym without a parent term", array("callType: AttractionSong"), $issues["synonym"]);
check("a term with no problems isn't listed", !in_array("callType: CallingSong", call_user_func_array("array_merge", array_values($issues))));
checkSame("links each term to its edit page", array("label" => "callType: PrematingSong", "link" => "/admin/term/edit/PrematingSong"), $readiness[4]["items"][0]);
checkSame("and each vocabulary to its edit page", array("label" => "callType", "link" => "/admin/cv/edit/callType"), $readiness[1]["items"][1]);
checkSame("a site with nothing to fix has no problems", array(), readinessIssues(array($readinessTerms[0]),
  array("callType" => array("shortname" => "callType", "name" => "Type of Call", "prefix" => "calltype")),
  array("license" => "https://creativecommons.org/licenses/by/4.0/", "prefix" => "")));
