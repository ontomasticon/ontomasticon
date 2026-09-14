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
