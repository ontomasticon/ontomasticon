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
checkSame("including the table of related terms", "CREATE TABLE `site_related_terms` (", prefixTables("CREATE TABLE `related_terms` ("));
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
checkSame("the MCP server", array("page_type" => "api", "active_page" => "mcp"), routeFor("/api/mcp"));
checkSame("other files in settings/ go to the home page", array("page_type" => "home"), routeFor("/settings/db.php"));
checkSame("a term's own address, ignoring the query string",
  array("page_type" => "term", "active_page" => "acoustic_allometry"), routeFor("/acoustic_allometry?lang=fr"));
checkSame("robots.txt, sitemap.xml and favicon.ico, which index.php makes", array("robots.txt", "sitemap.xml", "favicon.ico"),
  array(routeFor("/robots.txt")["page_type"], routeFor("/sitemap.xml")["page_type"], routeFor("/favicon.ico")["page_type"]));
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

section("Page titles, descriptions and canonical addresses");
checkSame("shortText() leaves short text alone", "A short definition.", shortText("A short definition.", 30));
checkSame("and cuts long text at the last space that fits, with an ellipsis", "The principle of acoustic…", shortText("The principle of acoustic allometry", 30));
checkSame("or at the length when there is no space", "abcdefghi…", shortText("abcdefghijklmnop", 10));
checkSame("counting characters rather than bytes", "Café…", shortText("Café crème brûlée", 6));
$GLOBALS["ontomasticon"]["config"]["site_name"] = "Bioacoustics Glossary";
$GLOBALS["ontomasticon"]["config"]["description"] = "Terms used in <i>bioacoustics</i>.";
$GLOBALS["ontomasticon"]["CVs"] = array("calls" => array("shortname" => "calls", "name" => "Calls", "description" => "<p>Types of call.</p>"));
function pageFor($pageInfo, $term = null) {
  $GLOBALS["ontomasticon"]["pageInfo"] = $pageInfo;
  $GLOBALS["ontomasticon"]["pageTerm"] = $term;
  return(array("title" => pageTitle(), "description" => pageDescription(), "canonical" => canonicalURL(), "notFound" => pageNotFound()));
}
$allometry = array("id" => 1, "shortname" => "acoustic_allometry", "name" => "acoustic allometry", "cv" => null, "opaque" => 0,
  "description" => "<p>The larger the animal, the lower its calls.</p>");
checkSame("a term's page is titled and described by the term, with its URI as the canonical address", array(
  "title" => "acoustic allometry – Bioacoustics Glossary", "description" => "The larger the animal, the lower its calls.",
  "canonical" => "https://glossary.example.org/acoustic_allometry", "notFound" => FALSE
), pageFor(array("page_type" => "term", "active_page" => "acoustic_allometry"), $allometry));
checkSame("an address with no term is not found, and has the site's title and description but no canonical address", array(
  "title" => "Bioacoustics Glossary", "description" => "Terms used in bioacoustics.", "canonical" => null, "notFound" => TRUE
), pageFor(array("page_type" => "term", "active_page" => "no_such_term")));
checkSame("a vocabulary's page is titled and described by the vocabulary", array(
  "title" => "Calls – Bioacoustics Glossary", "description" => "Types of call.", "canonical" => "https://glossary.example.org/cv/calls", "notFound" => FALSE
), pageFor(array("page_type" => "cv", "active_page" => "calls")));
checkSame("an address with no vocabulary is not found", TRUE, pageFor(array("page_type" => "cv", "active_page" => "medium"))["notFound"]);
checkSame("the list of vocabularies is found, but has no canonical address", array(FALSE, null),
  array(pageFor(array("page_type" => "cv", "active_page" => ""))["notFound"], pageFor(array("page_type" => "cv", "active_page" => ""))["canonical"]));
checkSame("the home page's canonical address is the site's", "https://glossary.example.org/", pageFor(array("page_type" => "home"))["canonical"]);
check("robots.txt, sitemap.xml and favicon.ico are reserved short names",
  reservedTermShortname("robots.txt") && reservedTermShortname("sitemap.xml") && reservedTermShortname("favicon.ico"));
unset($GLOBALS["ontomasticon"]["pageInfo"], $GLOBALS["ontomasticon"]["pageTerm"], $GLOBALS["ontomasticon"]["CVs"]);

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
section("Choosing a language");
$GLOBALS["ontomasticon"]["config"]["languages"] = "jibberish pt-BR";
checkSame("the site is offered in its default language and the other languages setting", array("en", "jibberish", "pt-BR"), siteLanguages());
function languageFor($acceptLanguage, $get = array(), $remembered = null) {
  if ($acceptLanguage === null) {
    unset($_SERVER["HTTP_ACCEPT_LANGUAGE"]);
  } else {
    $_SERVER["HTTP_ACCEPT_LANGUAGE"] = $acceptLanguage;
  }
  $_GET = $get;
  if ($remembered === null) {
    unset($_SESSION["lang"]);
  } else {
    $_SESSION["lang"] = $remembered;
  }
  return(detectLanguage());
}
checkSame("the default language when the browser doesn't say", "en", languageFor(null));
checkSame("the language the browser asks for", "jibberish", languageFor("jibberish"));
checkSame("skipping languages the site isn't offered in", "pt-BR", languageFor("fr-CH, fr;q=0.9, pt-BR;q=0.8, en;q=0.5"));
checkSame("most preferred first, whatever the order", "jibberish", languageFor("en;q=0.5, jibberish"));
checkSame("a language with a region matches the language", "en", languageFor("en-GB"));
checkSame("and a language matches the language with a region", "pt-BR", languageFor("pt"));
checkSame("in any case", "pt-BR", languageFor("PT-br"));
checkSame("not a language refused with q=0", "en", languageFor("jibberish;q=0"));
checkSame("the default language when the browser accepts any", "en", languageFor("*"));
checkSame("?lang= overrides the browser", "en", languageFor("jibberish", array("lang" => "en")));
checkSame("as does a language chosen earlier in the visit", "pt-BR", languageFor("jibberish", array(), "pt-BR"));
checkSame("unless the site is no longer offered in it", "en", languageFor(null, array(), "fr"));
languageFor(null, array("lang" => "jibberish"));
rememberLanguage();
checkSame("a language chosen with ?lang= is remembered", "jibberish", isset($_SESSION["lang"]) ? $_SESSION["lang"] : null);
languageFor(null, array("lang" => "xx"));
rememberLanguage();
check("unless the site isn't offered in it", !isset($_SESSION["lang"]));

$_SERVER["REQUEST_URI"] = "/cv/birds?lang=en&x=1";
languageFor(null, array("lang" => "en", "x" => "1"));
$switcher = languageSwitcher();
check("the language switcher links to the same page in each other language",
  strpos($switcher, "<a href='/cv/birds?lang=jibberish&amp;x=1' hreflang='jibberish' lang='jibberish'>jibberish</a>") !== FALSE
  && strpos($switcher, "<a href='/cv/birds?lang=pt-BR&amp;x=1'") !== FALSE);
check("and marks the current language without linking it", strpos($switcher, "<strong lang='en'>en</strong>") !== FALSE && strpos($switcher, "lang=en") === FALSE);
$GLOBALS["ontomasticon"]["config"]["languages"] = "";
checkSame("there is no switcher when the site has one language", "", languageSwitcher());
languageFor(null);
unset($GLOBALS["ontomasticon"]["config"]["languages"]);

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

section("Sessions");
function sessionFor($pageInfo, $method = "GET", $get = array(), $cookies = array()) {
  $_SERVER["REQUEST_METHOD"] = $method;
  $_GET = $get;
  $_COOKIE = $cookies;
  return(sessionNeeded($pageInfo));
}
check("a visitor to a public page, or its linked data, doesn't need a session", !sessionFor(array("page_type" => "home"))
  && !sessionFor(array("page_type" => "term", "active_page" => "acoustic_allometry")) && !sessionFor(array("page_type" => "api", "active_page" => "cv")));
check("login, user and administration pages do, including the database update",
  sessionFor(array("page_type" => "user", "active_page" => "login")) && sessionFor(array("page_type" => "admin", "active_page" => "update")));
check("as does submitting a form", sessionFor(array("page_type" => "home"), "POST"));
check("and a visitor who already has a session, who may be logged in", sessionFor(array("page_type" => "home"), "GET", array(), array(session_name() => "abc")));
check("choosing one of the site's languages needs one, to remember it", sessionFor(array("page_type" => "home"), "GET", array("lang" => "en")));
check("but not a language the site isn't offered in", !sessionFor(array("page_type" => "home"), "GET", array("lang" => "xx")));
check("nor the MCP server, although requests to it are posted, even with a session cookie", !sessionFor(array("page_type" => "api", "active_page" => "mcp"), "POST")
  && !sessionFor(array("page_type" => "api", "active_page" => "mcp"), "POST", array(), array(session_name() => "abc")));
$_GET = array();
$_COOKIE = array();
unset($_SERVER["REQUEST_METHOD"]);

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

section("Term languages");
checkSame("termLanguageError() accepts language tags with a script or region", array(null, null, null),
  array(termLanguageError("en"), termLanguageError("zh-Hant"), termLanguageError("es-419")));
checkSame("and no language", null, termLanguageError(""));
check("but not en_GB, or a tag with a trailing newline", termLanguageError("en_GB") !== null && termLanguageError("en\n") !== null);
checkSame("accepts a tag of 35 characters", null, termLanguageError("en-abcdefgh-abcdefgh-abcdefgh-abcde"));
check("but not a longer one, which the database can't hold", termLanguageError("en-abcdefgh-abcdefgh-abcdefgh-abcdef") !== null);
checkSame("JSON-LD doesn't tag text with a language that has a trailing newline", "Canto", jsonLDText("Canto", "en\n"));

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
  foreach (array("broader" => null, "parent" => null, "narrower" => array(), "children" => array(), "related" => array()) as $relation => $none) {
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
$rivalry = testTerm(array("id" => 7, "shortname" => "RivalrySong", "name" => "Rivalry Song", "cv" => "callType"));
$courtship = testTerm(array("id" => 8, "shortname" => "courtship_song", "name" => "Courtship song"), array("related" => array($rivalry)));
checkSame("links a term's related terms", array(array("@id" => "https://glossary.example.org/cv/callType#RivalrySong")), termJSONLD($courtship)["skos:related"]);
$response->setRelated("related", array($song, $rivalry));
checkSame("after its other related terms, linking a related term that is also its parent only once", array(
  array("@id" => "https://glossary.example.org/cv/callType#AgreementSong"), array("@id" => "https://glossary.example.org/cv/callType#RivalrySong")
), termJSONLD($response)["skos:related"]);
$response->setRelated("related", array());
checkSame("related terms are named in a list separated by commas, spaces or new lines, each once", array("chirp", "trill", "echeme"),
  shortnameList(" chirp, trill  chirp,\r\necheme,, "));
checkSame("and a list that isn't text names none", array(), shortnameList(array("chirp")));

$ld = termJSONLD(testTerm(array("id" => 5, "shortname" => "acoustic_allometry", "name" => "acoustic allometry",
  "description" => "", "language" => "", "reference" => "https://doi.org/10.1000/example")));
checkSame("a term outside a vocabulary is in the site's scheme", array("@id" => "https://glossary.example.org/"), $ld["skos:inScheme"]);
checkSame("and is a top concept of it", $ld["skos:inScheme"], $ld["skos:topConceptOf"]);
checkSame("a term without a language has a plain label", "acoustic allometry", $ld["skos:prefLabel"]);
check("an empty description gives no definition", !isset($ld["skos:definition"]) && !isset($ld["rdfs:comment"]));
checkSame("gives a web address reference as a source", array("@id" => "https://doi.org/10.1000/example"), $ld["dcterms:source"]);
checkSame("references are one per line, or separated by <br>, without blank lines", array("A", "B", "C"), referenceList(" A \r\n\r\nB<br />C\n"));
checkSame("and an empty reference field has none", array(), referenceList(null));
$ld = termJSONLD(testTerm(array("id" => 6, "shortname" => "anthropophony", "name" => "anthropophony", "description" => "Cites [1] to [4].",
  "reference" => "Krause BL. Voices of the Wild. 2015.\nhttps://doi.org/10.1000/one\nPijanowski BC, et al. Soundscape ecology. 2011.<br>https://doi.org/10.1000/two")));
checkSame("gives several references as citations and sources, one for each", array(
  array("Krause BL. Voices of the Wild. 2015.", "Pijanowski BC, et al. Soundscape ecology. 2011."),
  array(array("@id" => "https://doi.org/10.1000/one"), array("@id" => "https://doi.org/10.1000/two"))
), array($ld["dcterms:bibliographicCitation"], $ld["dcterms:source"]));
check("which Turtle gives as values of one property",
  strpos(turtleOutput($ld), 'dcterms:bibliographicCitation "Krause BL. Voices of the Wild. 2015.", "Pijanowski BC, et al. Soundscape ecology. 2011."') !== FALSE
  && strpos(turtleOutput($ld), "dcterms:source <https://doi.org/10.1000/one>, <https://doi.org/10.1000/two>") !== FALSE);
$cited = new Vocabulary("cited");
$cited->reference = "Ragge and Reynolds 1998<br>Krause 2015";
checkSame("a vocabulary's references are citations of its set in schema.org", array("Ragge and Reynolds 1998", "Krause 2015"),
  schemaOrgTermSetJSONLD($cited, array())["citation"]);

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

section("schema.org");
$ld = schemaOrgTerm($song);
checkSame("a term is a defined term at its URI", array("DefinedTerm", "https://glossary.example.org/cv/callType#AgreementSong"), array($ld["@type"], $ld["@id"]));
checkSame("named in its language", array("@value" => "Agreement Song", "@language" => "en"), $ld["name"]);
checkSame("with its shortname as its code", "AgreementSong", $ld["termCode"]);
checkSame("and its definition as plain text", array("@value" => "The female’s response.", "@language" => "en"), $ld["description"]);
checkSame("its synonyms' names are other names for it", array(array("@value" => "Attraction Song", "@language" => "en")), $ld["alternateName"]);
checkSame("and it is in its vocabulary's set", array("@id" => "https://glossary.example.org/cv/callType"), $ld["inDefinedTermSet"]);
check("a term without synonyms has no other names", !isset(schemaOrgTerm($premating)["alternateName"]));

$ld = schemaOrgTermJSONLD($song, $callType);
checkSame("a term's page starts with the schema.org context", array("@context", "https://schema.org"), array(array_keys($ld)[0], $ld["@context"]));
checkSame("and names the set the term is in", array("@type" => "DefinedTermSet", "@id" => "https://glossary.example.org/cv/callType",
  "name" => array("@value" => "Type of Call", "@language" => "en"), "url" => "https://glossary.example.org/cv/callType"), $ld["inDefinedTermSet"]);

$published = clone $callType;
$published->creator = "Test Author";
$published->publisher = "Natural History Museum";
$published->license = "https://creativecommons.org/licenses/by/4.0/";
$ld = schemaOrgTermSetJSONLD($published, array($premating, $song));
checkSame("a vocabulary is a defined term set at its address", array("https://schema.org", "DefinedTermSet", "https://glossary.example.org/cv/callType", "https://glossary.example.org/cv/callType"),
  array($ld["@context"], $ld["@type"], $ld["@id"], $ld["url"]));
checkSame("described in plain text, in the site's language", array(array("@value" => "Calls with the same function.", "@language" => "en"), "en"),
  array($ld["description"], $ld["inLanguage"]));
checkSame("with its creator, publisher, license and reference", array("Test Author", "Natural History Museum", "https://creativecommons.org/licenses/by/4.0/", "Ragge and Reynolds 1998"),
  array($ld["creator"], $ld["publisher"], $ld["license"], $ld["citation"]));
checkSame("and its terms", array("https://glossary.example.org/cv/callType#PrematingSong", "https://glossary.example.org/cv/callType#AgreementSong"),
  array_column($ld["hasDefinedTerm"], "@id"));
check("which don't repeat the context", !isset($ld["hasDefinedTerm"][0]["@context"]));
$ld = schemaOrgTermSetJSONLD(Vocabulary::site(), array());
checkSame("the site's own set is at the site address", "https://glossary.example.org/", $ld["@id"]);
check("and a set without terms doesn't list any", !isset($ld["hasDefinedTerm"]));
$published->license = "CC BY 4.0";
check("a license that isn't a web address is left out", !isset(schemaOrgTermSetJSONLD($published, array())["license"]));

if (function_exists("json_encode")) {
  $data = array("description" => "Ends </script><script>alert(1)</script> & more");
  $script = schemaOrgScript($data);
  check("is embedded in a JSON-LD script element", strpos($script, '<script type="application/ld+json">') === 0);
  checkSame("that text in the data can't end", 1, substr_count(strtolower($script), "</script"));
  preg_match('#^<script[^>]*>(.*)</script>\s*$#s', $script, $matches);
  checkSame("and that still holds the same data", $data, json_decode(isset($matches[1]) ? $matches[1] : "", TRUE));
}

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
$notFound = array();
foreach (array("jsonld", "turtle") as $format) {
  ob_start();
  printRDF(null, $format);
  $notFound[] = ob_get_clean();
}
checkSame("something that isn't there has an empty body, in JSON-LD as in Turtle", array("", ""), $notFound);

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
//The problems the readiness report finds in a term with this definition and these references
function readinessFor($description, $reference) {
  $term = testTerm(array("id" => 28, "shortname" => "Cited", "name" => "Cited", "description" => $description, "reference" => $reference));
  return(array_column(readinessIssues(array($term), array(), array("license" => "https://creativecommons.org/licenses/by/4.0/", "prefix" => "gl")), "id"));
}
checkSame("and a term whose definition cites a reference it doesn't have", array("citations"), readinessFor("Cites [2].", "One"));
checkSame("but not one citing references it has", array(), readinessFor("Cites [1] and [2].", "One\nTwo"));
checkSame("a list of citations, such as [1,2], cites each reference in it", array(), readinessFor("Cites [1,2].", "One\nTwo"));
checkSame("so a list citing a reference the term doesn't have is listed, with or without spaces", array(array("citations"), array("citations")),
  array(readinessFor("Cites [1,4].", "One\nTwo"), readinessFor("Cites [1, 2, 3].", "One\nTwo")));
checkSame("and so is a range such as [2-4], with a hyphen or an en dash, unless the term has every reference in it",
  array(array("citations"), array("citations"), array()),
  array(readinessFor("Cites [2-4].", "One\nTwo\nThree"), readinessFor("Cites [2&ndash;4].", "One\nTwo\nThree"), readinessFor("Cites [1-3].", "One\nTwo\nThree")));
checkSame("brackets holding anything else aren't citations", array(), readinessFor("Brackets such as [sic] and [in 1977] aren't citations.", "One"));

section("Static files");
check("the stylesheet's address has the time it last changed", preg_match('#^/css/default\.css\?v=[0-9]+$#D', assetPath("/css/default.css")) === 1);
checkSame("a file that isn't there has its address alone", "/css/missing.css", assetPath("/css/missing.css"));

section("Term types");
checkSame("termType() accepts concept, property and class", array("concept", "property", "class"),
  array(termType("concept"), termType("property"), termType("class")));
checkSame("and treats anything else, including no type, as a concept", array("concept", "concept"), array(termType("widget"), termType(null)));
checkSame("a term saved before types existed is a concept", "concept", Term::fromRow(array("id" => 1, "shortname" => "old"))->type);
$duration = testTerm(array("id" => 30, "shortname" => "Duration", "name" => "Duration", "description" => "How long.", "type" => "property"));
$pulseDuration = testTerm(array("id" => 31, "shortname" => "PulseDuration", "name" => "Pulse Duration", "description" => "How long a pulse lasts.",
  "type" => "property", "datatype" => "decimal", "broader" => 30), array("broader" => $duration));
$callDuration = testTerm(array("id" => 32, "shortname" => "CallDuration", "name" => "Call Duration", "description" => "How long a call lasts.",
  "type" => "property", "datatype" => "decimal", "broader" => 2), array("broader" => $premating));
$component = testTerm(array("id" => 33, "shortname" => "Component", "name" => "Call component", "description" => "A part of a call.",
  "cv" => "components", "type" => "class"));
$syllable = testTerm(array("id" => 34, "shortname" => "Syllable", "name" => "Syllable", "description" => "A unit of a call.",
  "cv" => "components", "type" => "class", "broader" => 33), array("broader" => $component));
$ld = termJSONLD($pulseDuration);
checkSame("a property is also a SKOS concept, so it can still be used as a measurement type", array("skos:Concept", "rdf:Property"), $ld["@type"]);
checkSame("and is defined by its vocabulary", array("@id" => "https://glossary.example.org/"), $ld["rdfs:isDefinedBy"]);
checkSame("a property under another property is a sub-property of it", array("@id" => "https://glossary.example.org/Duration"), $ld["rdfs:subPropertyOf"]);
check("as well as narrower in SKOS", isset($ld["skos:broader"]));
$ld = termJSONLD($callDuration);
check("a property under a concept is narrower, but not a sub-property", isset($ld["skos:broader"]) && !isset($ld["rdfs:subPropertyOf"]));
$ld = termJSONLD($syllable);
checkSame("a class is also a SKOS concept", array("skos:Concept", "rdfs:Class"), $ld["@type"]);
checkSame("and a sub-class of a broader class", array("@id" => "https://glossary.example.org/cv/components#Component"), $ld["rdfs:subClassOf"]);
check("a concept only has its SKOS type", termJSONLD($premating)["@type"] === "skos:Concept" && !isset(termJSONLD($premating)["rdfs:isDefinedBy"]));
check("Turtle gives both types", strpos(turtleOutput($ld), " a skos:Concept, rdfs:Class ;") !== FALSE);
$typeIssues = array();
foreach (readinessIssues(array($pulseDuration, $callDuration, $syllable), array(), array("license" => "https://creativecommons.org/licenses/by/4.0/", "prefix" => "gl")) as $issue) {
  $typeIssues[$issue["id"]] = array_column($issue["items"], "label");
}
checkSame("the readiness report lists only a term under a broader term of a different type", array("type-hierarchy" => array("CallDuration")), $typeIssues);

section("Value ranges");
checkSame("properties can take numbers, whole numbers, text, yes or no, or dates",
  array("decimal", "integer", "string", "boolean", "date"), array_keys(termDatatypes()));
checkSame("the edit form shows values from a vocabulary", "cv:spm", termValuesChoice(array("range_cv" => "spm", "datatype" => null)));
checkSame("or a datatype", "datatype:boolean", termValuesChoice(array("range_cv" => null, "datatype" => "boolean")));
checkSame("or neither", "", termValuesChoice(array("id" => 1)));
$GLOBALS["ontomasticon"]["CVs"] = array("spm" => array("shortname" => "spm", "name" => "Sound Production Method", "prefix" => "spm"));
function valuesFor($type, $choice) {
  $_POST = array("values" => $choice);
  return(capture(function() use ($type) { return(termValues($type)); }));
}
checkSame("a property's values can come from one of the site's vocabularies", array("", array("range_cv" => "spm", "datatype" => null)), valuesFor("property", "cv:spm"));
checkSame("or be a datatype", array("", array("range_cv" => null, "datatype" => "boolean")), valuesFor("property", "datatype:boolean"));
checkSame("or not be stated", array("", array("range_cv" => null, "datatype" => null)), valuesFor("property", ""));
checkSame("other types of term have no values, whatever the form says", array("", array("range_cv" => null, "datatype" => null)), valuesFor("class", "cv:spm"));
list($out, $values) = valuesFor("property", "cv:medium");
check("values from a vocabulary the site doesn't have are refused", $values === null && strpos($out, "values must come from") !== FALSE);
list($out, $values) = valuesFor("property", "datatype:colour");
check("and so is a datatype it doesn't have", $values === null);
$_POST = array();

$stridulation = testTerm(array("id" => 40, "shortname" => "StridulationInFlight", "name" => "Stridulation In Flight",
  "description" => "Whether it stridulates in flight.", "type" => "property", "datatype" => "boolean"));
$method = testTerm(array("id" => 41, "shortname" => "SoundProductionMethod", "name" => "Sound Production Method",
  "description" => "How the sound is made.", "type" => "property", "range_cv" => "spm"));
$ld = termJSONLD($stridulation);
checkSame("a property with a datatype has it as its range", array("@id" => "http://www.w3.org/2001/XMLSchema#boolean"), $ld["rdfs:range"]);
check("and no note", !isset($ld["skos:scopeNote"]));
check("which Turtle writes as a full IRI", strpos(turtleOutput($ld), "    rdfs:range <http://www.w3.org/2001/XMLSchema#boolean> ;") !== FALSE);
$ld = termJSONLD($method);
checkSame("a property whose values come from a vocabulary says so in a note",
  "Values come from the Sound Production Method controlled vocabulary: https://glossary.example.org/cv/spm", $ld["skos:scopeNote"]);
check("rather than as a range", !isset($ld["rdfs:range"]));
$ld = termJSONLD(testTerm(array("id" => 42, "shortname" => "Oddity", "name" => "Oddity", "datatype" => "decimal", "range_cv" => "spm")));
check("a term that isn't a property has neither, even if the database gives it values", !isset($ld["rdfs:range"]) && !isset($ld["skos:scopeNote"]));

$withoutValues = testTerm(array("id" => 43, "shortname" => "PulseCount", "name" => "Pulse Count", "description" => "How many pulses.", "type" => "property"));
$missingVocabulary = testTerm(array("id" => 44, "shortname" => "SoundPropagationMedium", "name" => "Sound Propagation Medium",
  "description" => "What the sound travels through.", "type" => "property", "range_cv" => "medium"));
$conceptWithValues = testTerm(array("id" => 45, "shortname" => "Echeme", "name" => "Echeme", "description" => "A group of syllables.", "datatype" => "decimal"));
$valueIssues = array();
foreach (readinessIssues(array($stridulation, $method, $withoutValues, $missingVocabulary, $conceptWithValues), $GLOBALS["ontomasticon"]["CVs"],
  array("license" => "https://creativecommons.org/licenses/by/4.0/", "prefix" => "gl")) as $issue) {
  $valueIssues[$issue["id"]] = array_column($issue["items"], "label");
}
checkSame("the readiness report lists properties without values, values from a missing vocabulary, and values on a term that isn't a property",
  array("values" => array("PulseCount"), "values-vocabulary" => array("SoundPropagationMedium"), "values-not-property" => array("Echeme")), $valueIssues);
unset($GLOBALS["ontomasticon"]["CVs"]);

section("Glossaries");
check("a site isn't a glossary unless it says so", !isGlossary());
$GLOBALS["ontomasticon"]["config"]["glossary_display"] = "1";
check("and is when it does", isGlossary());
unset($GLOBALS["ontomasticon"]["config"]["glossary_display"]);
$groups = glossaryGroups(array(
  array("shortname" => "zebra_finch", "name" => "zebra finch"),
  array("shortname" => "Echo", "name" => "Echo"),
  array("shortname" => "alarm_call", "name" => "Alarm call"),
  array("shortname" => "tone_2khz", "name" => "2 kHz tone"),
  array("shortname" => "unnamed", "name" => ""),
  array("shortname" => "echeme", "name" => "echeme")
));
checkSame("groups terms by the first letter of their names, ignoring case, with other characters first",
  array("#", "A", "E", "U", "Z"), array_keys($groups));
checkSame("sorts the terms under a letter alphabetically, ignoring case", array("echeme", "Echo"), array_column($groups["E"], "shortname"));
checkSame("files a term without a name under its short name", array("unnamed"), array_column($groups["U"], "shortname"));
$index = glossaryIndex($groups);
check("links to the letters that have terms", strpos($index, '<a href="#glossary:A">A</a>') !== FALSE && strpos($index, '<a href="#glossary:other">#</a>') !== FALSE);
check("and shows the others without a link", strpos($index, '<span class="glossary-index-empty">B</span>') !== FALSE && strpos($index, 'href="#glossary:B"') === FALSE);
checkSame("lists every letter from A to Z", 27, preg_match_all('/>[A-Z#]</', $index));
check("leaves out # when no term is filed under it", strpos(glossaryIndex(array("A" => array())), "#</") === FALSE);
$groups = glossaryGroups(glossaryEntries(array(
  array("id" => 50, "shortname" => "passive_acoustic_monitoring", "name" => "Passive acoustic monitoring", "acronym" => "PAM"),
  array("id" => 51, "shortname" => "echo", "name" => "Echo", "acronym" => "ECHO"),
  array("id" => 52, "shortname" => "sonar", "name" => "Sonar", "acronym" => null)
)));
checkSame("lists a term's acronym under its own letter as well, pointing to the term", array("PAM", "passive_acoustic_monitoring"),
  array($groups["P"][0]["name"], $groups["P"][0]["see"]["shortname"]));
checkSame("but not an acronym that is just the term's name", array("echo"), array_column($groups["E"], "shortname"));
checkSame("a term without an acronym is listed once", 1, count($groups["S"]));
checkSame("an acronym is too long for the database above 50 characters", array(null, TRUE),
  array(termAcronymError(str_repeat("A", 50)), termAcronymError(str_repeat("A", 51)) !== null));

$monitoring = testTerm(array("id" => 50, "shortname" => "passive_acoustic_monitoring", "name" => "Passive acoustic monitoring", "acronym" => "PAM"));
$ld = termJSONLD($monitoring);
checkSame("a term's acronym is an alternative label for it", array(array("@value" => "PAM", "@language" => "en")), $ld["skos:altLabel"]);
checkSame("and another name for it in schema.org", array(array("@value" => "PAM", "@language" => "en")), schemaOrgTerm($monitoring)["alternateName"]);
check("a site that isn't a glossary gives the concept alone, without OntoLex",
  $ld["@id"] === "https://glossary.example.org/passive_acoustic_monitoring" && !isset($ld["@graph"]) && !isset($ld["@context"]["ontolex"]));
checkSame("a word's address is the term's URI with the word as its fragment", "https://glossary.example.org/passive_acoustic_monitoring#acronym",
  $monitoring->entryURI("acronym"));
checkSame("or added after a colon to the fragment of a term in a vocabulary", "https://glossary.example.org/cv/callType#AgreementSong:entry",
  $song->entryURI("entry"));

$GLOBALS["ontomasticon"]["config"]["glossary_display"] = "1";
$ld = termJSONLD($monitoring);
checkSame("on a glossary, a term is a graph of its concept followed by lexical entries for its name and acronym", array(
  "https://glossary.example.org/passive_acoustic_monitoring", "https://glossary.example.org/passive_acoustic_monitoring#entry",
  "https://glossary.example.org/passive_acoustic_monitoring#acronym"
), isset($ld["@graph"]) ? array_column($ld["@graph"], "@id") : null);
checkSame("using OntoLex and LexInfo", array("http://www.w3.org/ns/lemon/ontolex#", "http://www.lexinfo.net/ontology/3.0/lexinfo#"),
  array($ld["@context"]["ontolex"], $ld["@context"]["lexinfo"]));
$entry = $ld["@graph"][1];
checkSame("the name's entry is written as the name, and denotes the concept", array("ontolex:LexicalEntry",
  array("@type" => "ontolex:Form", "ontolex:writtenRep" => array("@value" => "Passive acoustic monitoring", "@language" => "en")),
  array("@id" => "https://glossary.example.org/passive_acoustic_monitoring")
), array($entry["@type"], $entry["ontolex:canonicalForm"], $entry["ontolex:denotes"]));
checkSame("as the preferred term, and the full form of the acronym",
  array(array("@id" => "http://www.lexinfo.net/ontology/3.0/lexinfo#preferredTerm"), array("@id" => "http://www.lexinfo.net/ontology/3.0/lexinfo#fullForm")),
  array($entry["lexinfo:normativeAuthorization"], $entry["lexinfo:termType"]));
$entry = $ld["@graph"][2];
checkSame("the acronym's entry is an acronym for the name's entry, and denotes the same concept", array(
  array("@id" => "http://www.lexinfo.net/ontology/3.0/lexinfo#acronym"), array("@id" => "https://glossary.example.org/passive_acoustic_monitoring#entry"),
  array("@id" => "https://glossary.example.org/passive_acoustic_monitoring")
), array($entry["lexinfo:termType"], $entry["lexinfo:acronymFor"], $entry["ontolex:denotes"]));
$entry = termJSONLD($synonym)["@graph"][1];
checkSame("a synonym's name is an admitted term for the concept it is a synonym of", array("https://glossary.example.org/cv/callType#AttractionSong:entry",
  array("@id" => "http://www.lexinfo.net/ontology/3.0/lexinfo#admittedTerm"), array("@id" => "https://glossary.example.org/cv/callType#AgreementSong")
), array($entry["@id"], $entry["lexinfo:normativeAuthorization"], $entry["ontolex:denotes"]));
check("with no term type, as it has no acronym", !isset($entry["lexinfo:termType"]));
$turtle = turtleOutput(termJSONLD($monitoring));
check("Turtle gives a word's written form as a blank node", strpos($turtle, "\n".'    ontolex:canonicalForm [ a ontolex:Form ; ontolex:writtenRep "PAM"@en ] ;'."\n") !== FALSE);
check("and declares the prefixes", strpos($turtle, "@prefix lexinfo: <http://www.lexinfo.net/ontology/3.0/lexinfo#> .\n") !== FALSE
  && strpos($turtle, "@prefix ontolex: <http://www.w3.org/ns/lemon/ontolex#> .\n") !== FALSE);
$ld = vocabularyJSONLD($callType, array($premating, $song, $synonym, $response));
checkSame("a glossary's vocabulary gives its terms, then an entry for each of their names", array(9, "https://glossary.example.org/cv/callType#PrematingSong:entry"),
  array(count($ld["@graph"]), $ld["@graph"][5]["@id"]));
checkSame("with the same top concepts", 2, count($ld["@graph"][0]["skos:hasTopConcept"]));
unset($GLOBALS["ontomasticon"]["config"]["glossary_display"]);

section("Search");
checkSame("a search matches text containing it", "%echo%", likePattern("echo"));
checkSame("or starting with it", "echo%", likePattern("echo", TRUE));
checkSame("LIKE's wildcards and escape character in a search only match themselves", "%100|% |_||%", likePattern("100% _|"));
$_GET["q"] = "  echo  ";
checkSame("the search is read from ?q=, without the spaces around it", "echo", searchQuery());
$_GET["q"] = str_repeat("é", 150);
checkSame("and cut to 100 characters, rather than bytes", str_repeat("é", 100), searchQuery());
$_GET["q"] = array("echo");
checkSame("a search that isn't text is ignored", "", searchQuery());
checkSame("searches from elsewhere, such as the MCP server, are trimmed and cut the same way", array("echo", str_repeat("é", 100)),
  array(searchText("  echo "), searchText(str_repeat("é", 150))));
$pageInfo = isset($GLOBALS["ontomasticon"]["pageInfo"]) ? $GLOBALS["ontomasticon"]["pageInfo"] : null;
$_GET["q"] = "echo";
$GLOBALS["ontomasticon"]["pageInfo"] = array("page_type" => "home");
check("the home page with a search is the search page", searchPage());
$GLOBALS["ontomasticon"]["pageInfo"] = array("page_type" => "term", "active_page" => "echo");
check("other pages aren't, whatever their address has", !searchPage());
$GLOBALS["ontomasticon"]["pageInfo"] = $pageInfo;
unset($_GET["q"]);

section("MCP server");
//The value in nested arrays at a list of keys, or NULL if it isn't there
function valueAt($value, $keys) {
  foreach ($keys as $key) {
    if (!is_array($value) || !array_key_exists($key, $value)) {
      return(null);
    }
    $value = $value[$key];
  }
  return($value);
}

//The ways a value decoded from JSON doesn't match a JSON Schema, or none if it does. Only the keywords the MCP server's
//schemas use are checked: type, enum, required, properties and items. An empty array matches both an object and a list.
function schemaProblems($value, $schema, $path = "") {
  if (isset($schema["type"])) {
    $isList = is_array($value) && array_values($value) === $value;
    $matches = FALSE;
    foreach ((array)$schema["type"] as $type) {
      $matches = $matches || ($type == "object" && is_array($value) && (!$isList || count($value) == 0)) || ($type == "array" && $isList)
        || ($type == "string" && is_string($value)) || ($type == "integer" && is_int($value)) || ($type == "boolean" && is_bool($value))
        || ($type == "null" && $value === null);
    }
    if (!$matches) {
      return(array($path." isn't ".implode(" or ", (array)$schema["type"])));
    }
  }
  $problems = array();
  if (isset($schema["enum"]) && !in_array($value, $schema["enum"], TRUE)) {
    $problems[] = $path." isn't one of its values";
  }
  foreach ((is_array($value) && isset($schema["required"])) ? $schema["required"] : array() as $key) {
    if (!array_key_exists($key, $value)) {
      $problems[] = $path."/".$key." is missing";
    }
  }
  foreach ((is_array($value) && isset($schema["properties"])) ? $schema["properties"] : array() as $key => $property) {
    if (array_key_exists($key, $value)) {
      $problems = array_merge($problems, schemaProblems($value[$key], $property, $path."/".$key));
    }
  }
  foreach ((is_array($value) && isset($schema["items"])) ? $value : array() as $index => $item) {
    $problems = array_merge($problems, schemaProblems($item, $schema["items"], $path."/".$index));
  }
  return($problems);
}

//The MCP server's response to a request, as array(status, headers, the body decoded, the body). An array is sent as JSON.
function testMCPResponse($message, $headers = array(), $method = "POST") {
  $response = mcpResponse($method, $headers, is_string($message) ? $message : toJSON($message));
  return(array($response["status"], $response["headers"], ($response["body"] === null) ? null : json_decode($response["body"], TRUE), $response["body"]));
}

//A request of protocol version 2026-07-28, which gives the version and the client's capabilities in its _meta
function testMCPMessage($method, $params = array(), $id = 1) {
  $params["_meta"] = array("io.modelcontextprotocol/protocolVersion" => "2026-07-28", "io.modelcontextprotocol/clientCapabilities" => new stdClass());
  return(array("jsonrpc" => "2.0", "id" => $id, "method" => $method, "params" => $params));
}

//The headers a request of protocol version 2026-07-28 repeats its version, method and tool name in
function testMCPHeaders($method, $name = null) {
  $headers = array("mcp-protocol-version" => "2026-07-28", "mcp-method" => $method);
  if ($name !== null) {
    $headers["mcp-name"] = $name;
  }
  return($headers);
}

if (!function_exists("json_encode")) {
  print "  MCP tests skipped: this PHP doesn't have the json extension.\n";
} else {
  list($status, , $response) = testMCPResponse(testMCPMessage("server/discover"), testMCPHeaders("server/discover"));
  check("the MCP server isn't there unless the site turns it on", $status == 404 && $response === null);
  $GLOBALS["ontomasticon"]["config"]["mcp_server"] = "1";

  list($status, $headers, $response, $body) = testMCPResponse(testMCPMessage("server/discover"), testMCPHeaders("server/discover"));
  check("server/discover answers with JSON", $status == 200 && in_array("Content-Type: application/json; charset=utf-8", $headers));
  checkSame("giving the protocol versions the server supports, newest first", array("2026-07-28", "2025-11-25", "2025-06-18", "2025-03-26"),
    valueAt($response, array("result", "supportedVersions")));
  check("and that it has tools, as an empty object", strpos($body, '"capabilities":{"tools":{}}') !== FALSE);
  checkSame("in a complete result, which clients may keep for as long as public pages, from the server titled with the site's name",
    array("complete", 300000, "public", array("name" => "ontomasticon", "title" => "Bioacoustics Glossary", "version" => $version)),
    array(valueAt($response, array("result", "resultType")), valueAt($response, array("result", "ttlMs")), valueAt($response, array("result", "cacheScope")),
      valueAt($response, array("result", "_meta", "io.modelcontextprotocol/serverInfo"))));
  check("with instructions naming and describing the site, as plain text, and saying how to use the tools",
    strpos((string)valueAt($response, array("result", "instructions")), "Bioacoustics Glossary: Terms used in bioacoustics.\n\nUse search_terms") === 0);
  $GLOBALS["ontomasticon"]["config"]["license"] = "https://creativecommons.org/licenses/by/4.0/";
  check("and the license, when the site has one", strpos(mcpInstructions(), "published under the license at https://creativecommons.org/licenses/by/4.0/.") !== FALSE);
  unset($GLOBALS["ontomasticon"]["config"]["license"]);
  list(, , $response) = testMCPResponse(testMCPMessage("server/discover", array(), "discover-1"), testMCPHeaders("server/discover"));
  checkSame("a response has its request's id", "discover-1", valueAt($response, array("id")));

  list($status, , $response, $body) = testMCPResponse(testMCPMessage("tools/list"), testMCPHeaders("tools/list"));
  $tools = valueAt($response, array("result", "tools"));
  checkSame("tools/list gives the tools, always in the same order", array("search_terms", "get_term", "list_vocabularies", "list_terms"),
    is_array($tools) ? array_column($tools, "name") : null);
  check("which clients may keep too", valueAt($response, array("result", "ttlMs")) === 300000 && valueAt($response, array("result", "cacheScope")) === "public");
  $wellFormed = is_array($tools);
  foreach (is_array($tools) ? $tools : array() as $tool) {
    $wellFormed = $wellFormed && preg_match('/^[A-Za-z0-9_.-]{1,128}$/D', $tool["name"]) === 1 && $tool["description"] != ""
      && valueAt($tool, array("inputSchema", "type")) === "object" && valueAt($tool, array("outputSchema", "type")) === "object"
      && valueAt($tool, array("annotations", "readOnlyHint")) === TRUE;
  }
  check("each named with the characters MCP asks for, described, taking and giving objects, and only reading", $wellFormed);
  check("a tool without arguments takes an object with no properties", strpos($body, '"inputSchema":{"type":"object","additionalProperties":false}') !== FALSE);
  $searchSchema = mcpTools()[0]["outputSchema"];
  checkSame("the tests' schema check finds results that don't match a tool's output schema", array(array(), array("/more is missing", "/terms isn't array")),
    array(schemaProblems(array("terms" => array(), "more" => FALSE), $searchSchema), schemaProblems(array("terms" => "none"), $searchSchema)));

  $discover = testMCPMessage("server/discover");
  list($status, , $response) = testMCPResponse($discover, array("mcp-protocol-version" => "2026-07-28"));
  checkSame("a request that doesn't repeat its method in a header is refused", array(400, -32020), array($status, valueAt($response, array("error", "code"))));
  list($status, , $response) = testMCPResponse($discover, testMCPHeaders("tools/list"));
  checkSame("as is one whose header gives another method", array(400, -32020), array($status, valueAt($response, array("error", "code"))));
  list($status, , $response) = testMCPResponse($discover, array("mcp-protocol-version" => "2025-11-25", "mcp-method" => "server/discover"));
  checkSame("or another protocol version", array(400, -32020), array($status, valueAt($response, array("error", "code"))));
  $future = $discover;
  $future["params"]["_meta"]["io.modelcontextprotocol/protocolVersion"] = "2099-01-01";
  list($status, , $response) = testMCPResponse($future, array("mcp-protocol-version" => "2099-01-01", "mcp-method" => "server/discover"));
  checkSame("a protocol version the server doesn't support is refused, listing those it does", array(400, -32022, mcpVersions(), "2099-01-01"),
    array($status, valueAt($response, array("error", "code")), valueAt($response, array("error", "data", "supported")), valueAt($response, array("error", "data", "requested"))));
  $withoutCapabilities = $discover;
  unset($withoutCapabilities["params"]["_meta"]["io.modelcontextprotocol/clientCapabilities"]);
  list($status, , $response) = testMCPResponse($withoutCapabilities, testMCPHeaders("server/discover"));
  checkSame("as is a request that doesn't give the client's capabilities", array(400, -32602), array($status, valueAt($response, array("error", "code"))));
  list($status, , $response) = testMCPResponse(testMCPMessage("ping"), testMCPHeaders("ping"));
  checkSame("methods the protocol no longer has, such as ping, aren't found", array(404, -32601), array($status, valueAt($response, array("error", "code"))));

  $emptySearch = testMCPMessage("tools/call", array("name" => "search_terms", "arguments" => array("query" => " ")));
  list($status, , $response) = testMCPResponse($emptySearch, testMCPHeaders("tools/call", "get_term"));
  checkSame("a tool call whose header names another tool is refused", array(400, -32020), array($status, valueAt($response, array("error", "code"))));
  list($status, , $response) = testMCPResponse($emptySearch, testMCPHeaders("tools/call"));
  checkSame("as is one that doesn't name its tool in a header", array(400, -32020), array($status, valueAt($response, array("error", "code"))));
  list($status, , $response) = testMCPResponse(testMCPMessage("tools/call", array("name" => "sïng")), testMCPHeaders("tools/call", "=?base64?".base64_encode("sïng")."?="));
  checkSame("a tool name encoded in the header is decoded, and a tool the server doesn't have is an error", array(200, -32602),
    array($status, valueAt($response, array("error", "code"))));
  list($status, , $response) = testMCPResponse($emptySearch, testMCPHeaders("tools/call", "search_terms"));
  check("arguments a tool can't use give a result that is an error, saying what to change", $status == 200 && valueAt($response, array("result", "isError")) === TRUE
    && strpos((string)valueAt($response, array("result", "content", 0, "text")), "query") !== FALSE && valueAt($response, array("result", "resultType")) === "complete");
  $errors = array(
    mcpCallTool("search_terms", array("query" => "echo", "limit" => 0)),
    mcpCallTool("search_terms", array("query" => "echo", "limit" => "ten")),
    mcpCallTool("get_term", array()),
    mcpCallTool("list_terms", array("vocabulary" => "nowhere")),
    mcpCallTool("list_terms", array("offset" => -1))
  );
  checkSame("as do a limit out of range, a term that isn't named, an unknown vocabulary and a negative offset", array(TRUE, TRUE, TRUE, TRUE, TRUE),
    array_column($errors, "isError"));
  checkSame("a whole number can be given as 10 or 10.0, but not 10.5", array(10, 10, null), array(mcpWholeNumber(10), mcpWholeNumber(10.0), mcpWholeNumber(10.5)));
  checkSame("calling a tool the server doesn't have gives nothing", null, mcpCallTool("delete_everything", array()));
  checkSame("header values encoded as Base64 are decoded", array("sïng", "plain", null),
    array(mcpHeaderValue("=?base64?".base64_encode("sïng")."?="), mcpHeaderValue("plain"), mcpHeaderValue("=?base64?!!?=")));

  $initialize = array("jsonrpc" => "2.0", "id" => 1, "method" => "initialize",
    "params" => array("protocolVersion" => "2025-06-18", "capabilities" => new stdClass(), "clientInfo" => array("name" => "Test", "version" => "1")));
  list($status, $headers, $response) = testMCPResponse($initialize);
  checkSame("clients of earlier versions start with initialize, and get the version they ask for", array(200, "2025-06-18"),
    array($status, valueAt($response, array("result", "protocolVersion"))));
  check("with the server's name, its tools and instructions", valueAt($response, array("result", "serverInfo", "name")) === "ontomasticon"
    && valueAt($response, array("result", "capabilities", "tools")) === array() && is_string(valueAt($response, array("result", "instructions"))));
  check("but no session", count(preg_grep('/^Mcp-Session-Id:/i', $headers)) == 0);
  $initialize["params"]["protocolVersion"] = "2024-11-05";
  list(, , $response) = testMCPResponse($initialize);
  checkSame("a version the server doesn't support gets the newest that starts with initialize", "2025-11-25", valueAt($response, array("result", "protocolVersion")));
  list($status, , , $body) = testMCPResponse(array("jsonrpc" => "2.0", "method" => "notifications/initialized"), array("mcp-protocol-version" => "2025-06-18"));
  check("notifications are accepted, with no reply", $status == 202 && $body === null);
  list(, , , $body) = testMCPResponse(array("jsonrpc" => "2.0", "id" => 2, "method" => "ping"), array("mcp-protocol-version" => "2025-06-18"));
  check("these clients can ping, and get an empty object", strpos((string)$body, '"result":{}') !== FALSE);
  list($status, , $response) = testMCPResponse(array("jsonrpc" => "2.0", "id" => 3, "method" => "tools/list"), array("mcp-protocol-version" => "2025-11-25"));
  checkSame("and list the tools without giving _meta", array(200, 4), array($status, count((array)valueAt($response, array("result", "tools")))));
  list($status, , $response) = testMCPResponse(array("jsonrpc" => "2.0", "id" => 4, "method" => "tools/list"));
  checkSame("as can clients of 2025-03-26, which don't give their version in a header", array(200, 4), array($status, count((array)valueAt($response, array("result", "tools")))));
  list($status, , $response) = testMCPResponse(array("jsonrpc" => "2.0", "id" => 5, "method" => "resources/list"), array("mcp-protocol-version" => "2025-11-25"));
  checkSame("a method they ask for that the server doesn't have is only an error in the response, as they may take an HTTP error as a failed connection",
    array(200, -32601), array($status, valueAt($response, array("error", "code"))));
  list($status, , $response) = testMCPResponse(array("jsonrpc" => "2.0", "id" => 6, "method" => "tools/list"), array("mcp-protocol-version" => "2099-01-01"));
  checkSame("a version in their header that the server doesn't support is refused", array(400, -32022), array($status, valueAt($response, array("error", "code"))));

  list($status, $headers) = testMCPResponse("", array(), "GET");
  check("GET isn't allowed, as the server sends no messages of its own", $status == 405 && in_array("Allow: POST, OPTIONS", $headers));
  list($status) = testMCPResponse("", array(), "DELETE");
  checkSame("nor is DELETE, as there are no sessions to end", 405, $status);
  list($status, $headers) = testMCPResponse("", array(), "OPTIONS");
  check("browsers may let scripts on other websites post requests with the headers MCP uses",
    $status == 204 && count(preg_grep('/^Access-Control-Allow-Headers: .*MCP-Protocol-Version, Mcp-Method, Mcp-Name/', $headers)) == 1);
  list($status, , $response) = testMCPResponse("{not json");
  check("a request that isn't JSON is a parse error, without an id", $status == 400 && valueAt($response, array("error", "code")) === -32700
    && !array_key_exists("id", (array)$response));
  list($status, , $response) = testMCPResponse(array(testMCPMessage("tools/list"), testMCPMessage("server/discover", array(), 2)), testMCPHeaders("tools/list"));
  checkSame("several messages sent together are refused", array(400, -32600), array($status, valueAt($response, array("error", "code"))));
  $nullID = testMCPMessage("tools/list");
  $nullID["id"] = null;
  list($status, , $response) = testMCPResponse($nullID, testMCPHeaders("tools/list"));
  checkSame("as is a request whose id is null", array(400, -32600), array($status, valueAt($response, array("error", "code"))));
  list($status) = testMCPResponse(str_repeat(" ", MCP_BODY_LIMIT + 1));
  checkSame("or one that is too large", 413, $status);
  list($status) = testMCPResponse(array("jsonrpc" => "2.0", "id" => 7, "result" => new stdClass()));
  checkSame("a response to a request is accepted and ignored, as the server sends no requests", 202, $status);
  unset($GLOBALS["ontomasticon"]["config"]["mcp_server"]);
}
