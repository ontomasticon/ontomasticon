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
