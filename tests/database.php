<?php
// Database tests. WARNING: these drop every table in the TEST_DB_NAME database.

mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli(getenv("TEST_DB_HOST"), getenv("TEST_DB_USER"), getenv("TEST_DB_PASSWORD"), getenv("TEST_DB_NAME"));
if ($db->connect_error) {
  section("Database");
  failTest("Could not connect to the test database: ".$db->connect_error);
  return;
}
$db->set_charset("utf8mb4");
$_SERVER["REMOTE_ADDR"] = "198.51.100.20";
//Set TEST_TABLE_PREFIX to run the tests with prefixed table names, as $table_prefix in settings/db.php does
$table_prefix = (string)getenv("TEST_TABLE_PREFIX");

//Drop every table, then set up from an SQL file
function resetDatabase($sqlFile) {
  global $db;
  $result = $db->query("SHOW TABLES;");
  foreach ($result->fetch_all() as $table) {
    $db->query("DROP TABLE `".$table[0]."`;");
  }
  runSQLFile($sqlFile);
}

//Run an SQL file the way the installer does, with the table prefix
function runSQLFile($sqlFile) {
  global $db;
  $statement = "";
  foreach (file($sqlFile) as $line) {
    if (substr($line, 0, 2) == '--' || trim($line) == '') { continue; }
    $statement .= $line;
    if (substr(trim($line), -1, 1) == ';') {
      if (!$db->query(prefixTables($statement))) {
        failTest("Setting up from ".$sqlFile." failed: ".$db->error);
      }
      $statement = "";
    }
  }
  $db->query("INSERT INTO ".table("config")." VALUES('base_url', 'glossary.example.org/');");
  $GLOBALS["ontomasticon"]["config"] = getConfig();
  $GLOBALS["ontomasticon"]["CVs"] = getCVs();
}

//Fill in $_POST the way the add and edit term forms do
function termForm($fields) {
  $_POST = array_merge(array(
    "shortname" => "", "name" => "", "description" => "", "language" => "en",
    "cv" => "none", "invalid" => "none", "parent" => "", "broader" => "", "reference" => ""
  ), $fields);
}

function termRow($shortname) {
  $result = dbQuery("SELECT * FROM ".table("terms")." WHERE `shortname` = ?;", array($shortname));
  return(($result) ? $result->fetch_assoc() : null);
}

function passwordHashFor($email) {
  $result = dbQuery("SELECT `password` FROM ".table("users")." WHERE `email` = ?;", array($email));
  return($result->fetch_assoc()["password"]);
}

function tryLogin($email, $password) {
  $_POST = array("email" => $email, "password" => $password);
  unset($_SESSION["user"], $_SESSION["must_change_password"]);
  return(login());
}

function runUpdate() {
  $GLOBALS["ontomasticon"]["config"] = getConfig();
  ob_start();
  template("update.php");
  return(ob_get_clean());
}

section("Fresh install");
resetDatabase("inst/ontomasticon.sql");
checkSame("database version matches the code", (string)$version, (string)$GLOBALS["ontomasticon"]["config"]["version_db"]);
check("terms have a reference column", $db->query("SELECT `reference` FROM ".table("terms")." LIMIT 1;") !== FALSE);
check("the login_attempts table exists", $db->query("SELECT 1 FROM ".table("login_attempts")." LIMIT 1;") !== FALSE);
check("terms have created and modified columns", $db->query("SELECT `created`, `modified` FROM ".table("terms")." LIMIT 1;") !== FALSE);
check("vocabularies have a prefix column", $db->query("SELECT `prefix` FROM ".table("cv")." LIMIT 1;") !== FALSE);
check("the linked data settings exist", count(array_intersect(array("publisher", "license", "prefix"), array_keys(getConfig()))) == 3);
check("term languages can be 35 characters long", !termLanguageColumnTooNarrow());

section("Adding terms");
termForm(array("shortname" => "sound", "name" => "Sound"));
list($out, $ok) = capture(function() { return(addTerm()); });
check("adds a term", $ok && termRow("sound") != null);
check("records when the term was added", termRow("sound")["created"] !== null && termRow("sound")["modified"] === termRow("sound")["created"]);
termForm(array("shortname" => "sound", "name" => "Duplicate"));
list($out, $ok) = capture(function() { return(addTerm()); });
check("refuses a short name that is already used", !$ok && strpos($out, "already a term") !== FALSE);
checkSame("keeps the original term", "Sound", termRow("sound")["name"]);
termForm(array("shortname" => ""));
list($out, $ok) = capture(function() { return(addTerm()); });
check("refuses an empty short name", !$ok && strpos($out, "short name is required") !== FALSE);
termForm(array("shortname" => "odd term", "name" => "Odd term"));
list($out, $ok) = capture(function() { return(addTerm()); });
check("refuses a short name that isn't safe in a URI", !$ok && strpos($out, "can only use the letters") !== FALSE && termRow("odd term") == null);
termForm(array("shortname" => "a/b#c", "name" => "Slash"));
list($out, $ok) = capture(function() { return(addTerm()); });
check("including one with characters that mean something in URIs", !$ok && termRow("a/b#c") == null);
termForm(array("shortname" => "song", "parent" => "missing"));
list($out, $ok) = capture(function() { return(addTerm()); });
check("refuses a parent term that doesn't exist", !$ok && strpos($out, "no term with the short name") !== FALSE && termRow("song") == null);
termForm(array("shortname" => "api", "name" => "API"));
list($out, $ok) = capture(function() { return(addTerm()); });
check("refuses a short name outside a vocabulary that the site uses for its own pages", !$ok && strpos($out, "own pages or files") !== FALSE && termRow("api") == null);
foreach (array("css", "README.md", "Admin", "glossary.php") as $reserved) {
  termForm(array("shortname" => $reserved, "name" => $reserved));
  list($out, $ok) = capture(function() { return(addTerm()); });
  check("or for its files and directories, in any case, or names ending in .php: ".$reserved, !$ok && termRow($reserved) == null);
}
termForm(array("shortname" => "user", "name" => "User", "opaque" => "opaque"));
list($out, $ok) = capture(function() { return(addTerm()); });
check("but allows one for an opaque term, whose URI uses its id", $ok && termRow("user") != null);
dbQuery("DELETE FROM ".table("terms")." WHERE `shortname` = 'user';");
termForm(array("shortname" => "42", "name" => "Forty-two"));
list($out, $ok) = capture(function() { return(addTerm()); });
check("refuses a short name made only of digits, which could share a URI with an opaque term's id", !$ok && strpos($out, "only of digits") !== FALSE && termRow("42") == null);
termForm(array("shortname" => "bird_song", "name" => "Bird song", "parent" => "sound", "reference" => "Smith 2020"));
capture(function() { return(addTerm()); });
termForm(array("shortname" => "animal_sound", "name" => "Animal sound", "broader" => "sound"));
capture(function() { return(addTerm()); });
checkSame("saves the parent as the parent's id", termRow("sound")["id"], termRow("bird_song")["parent"]);
checkSame("saves the reference", "Smith 2020", termRow("bird_song")["reference"]);
termForm(array("shortname" => "echo", "name" => "Echo", "broader" => "echo"));
list($out, $ok) = capture(function() { return(addTerm()); });
check("refuses a term as its own broader term", !$ok && strpos($out, "its own parent or broader term") !== FALSE && termRow("echo") == null);
termForm(array("shortname" => "echo", "name" => "Echo", "language" => "en_GB"));
list($out, $ok) = capture(function() { return(addTerm()); });
check("refuses a language that isn't a language tag", !$ok && strpos($out, "must be a language tag") !== FALSE && termRow("echo") == null);
termForm(array("shortname" => "echo", "name" => "Echo", "language" => "zh-Hant"));
list($out, $ok) = capture(function() { return(addTerm()); });
checkSame("saves a language tag longer than five characters", "zh-Hant", $ok ? termRow("echo")["language"] : null);
termForm(array("shortname" => "reverb", "name" => "Reverb", "language" => "en-abcdefgh-abcdefgh-abcdefgh-abcde"));
list($out, $ok) = capture(function() { return(addTerm()); });
checkSame("and one of 35 characters", "en-abcdefgh-abcdefgh-abcdefgh-abcde", $ok ? termRow("reverb")["language"] : null);
termForm(array("shortname" => "silence", "name" => "Silence", "language" => ""));
list($out, $ok) = capture(function() { return(addTerm()); });
check("a term can have no language", $ok && termRow("silence") != null);
dbQuery("DELETE FROM ".table("terms")." WHERE `shortname` IN ('echo', 'reverb', 'silence');");
checkSame("quotes in values can't change a query", null, getTerm("x' OR '1'='1"));

section("Listing terms");
$terms = array();
foreach (getTerms() as $term) {
  $terms[$term["shortname"]] = $term;
}
$names = array_keys($terms);
sort($names);
checkSame("lists the terms outside vocabularies", array("animal_sound", "bird_song", "sound"), $names);
checkSame("includes child terms", array("bird_song"), array_column($terms["sound"]["children"], "shortname"));
checkSame("includes narrower terms", array("animal_sound"), array_column($terms["sound"]["narrower"], "shortname"));
checkSame("includes the broader term", array("sound"), array_column($terms["animal_sound"]["broader"], "shortname"));
checkSame("terms without children have none", array(), $terms["bird_song"]["children"]);
$term = getTerm("animal_sound");
checkSame("getTerm() gives the broader term's short name", "sound", $term["broader"]);
checkSame("getTermByID() finds the same term", "animal_sound", getTermByID($term["id"])["shortname"]);

section("Term objects");
$shortnames = function($terms) {
  return(array_map(function($term) { return($term->shortname); }, $terms));
};
$sound = Term::find("sound");
checkSame("Term::find() loads a term", "Sound", ($sound != null) ? $sound->name : null);
checkSame("Term::find() gives NULL for a missing term", null, Term::find("missing"));
checkSame("Term::findByID() finds the same term", "sound", Term::findByID($sound->id)->shortname);
checkSame("loads the narrower terms", array("animal_sound"), $shortnames($sound->narrower()));
checkSame("loads the child terms", array("bird_song"), $shortnames($sound->children()));
checkSame("loads the broader term", "sound", Term::find("animal_sound")->broader()->shortname);
checkSame("loads the parent term", "sound", Term::find("bird_song")->parent()->shortname);
checkSame("a term without a broader term has none", null, $sound->broader());
checkSame("Term::findByURI() finds a term from its URI", "sound",
  (Term::findByURI("https://glossary.example.org/sound") != null) ? Term::findByURI("https://glossary.example.org/sound")->shortname : null);
checkSame("but not from another address ending in its name", null, Term::findByURI("https://glossary.example.org/cv/birds#sound"));
checkSame("nor from its id when it isn't opaque", null, Term::findByURI("https://glossary.example.org/".$sound->id));
checkSame("and gives NULL for a missing term", null, Term::findByURI("https://glossary.example.org/missing"));
$ld = termJSONLD($sound);
checkSame("JSON-LD links the narrower terms", array(array("@id" => "https://glossary.example.org/animal_sound")), $ld["skos:narrower"]);
checkSame("and the child terms as related", array(array("@id" => "https://glossary.example.org/bird_song")), $ld["skos:related"]);

section("Vocabulary objects");
checkSame("Vocabulary::find() gives NULL for a missing vocabulary", null, Vocabulary::find("missing"));
$siteTerms = array();
foreach (Vocabulary::site()->terms() as $term) {
  $siteTerms[$term->shortname] = $term;
}
checkSame("the site's own scheme has the terms that aren't in a vocabulary", array("animal_sound", "bird_song", "sound"), array_keys($siteTerms));
checkSame("loads their broader terms together", "sound", $siteTerms["animal_sound"]->broader()->shortname);
checkSame("their narrower terms", array("animal_sound"), $shortnames($siteTerms["sound"]->narrower()));
checkSame("their child terms", array("bird_song"), $shortnames($siteTerms["sound"]->children()));
checkSame("and their parent terms", "sound", $siteTerms["bird_song"]->parent()->shortname);
checkSame("a term without a broader term has none", null, $siteTerms["sound"]->broader());

section("Editing terms");
$GLOBALS["ontomasticon"]["pageInfo"] = array("page_type" => "admin", "active_page" => "term", "active_subpage" => "edit", "active_subsubpage" => "bird_song");
dbQuery("UPDATE ".table("terms")." SET `created` = '2020-01-01 09:00:00', `modified` = '2020-01-01 09:00:00' WHERE `shortname` = 'bird_song';");
termForm(array("name" => "Birdsong", "parent" => "sound", "reference" => "Jones 2021"));
list($out, $ok) = capture(function() { return(editTerm()); });
check("saves changes", $ok && termRow("bird_song")["name"] == "Birdsong" && termRow("bird_song")["reference"] == "Jones 2021");
check("records when the term was changed, but not when it was added",
  termRow("bird_song")["created"] == "2020-01-01 09:00:00" && termRow("bird_song")["modified"] > "2020-01-01 09:00:00");
checkSame("gives when the term was added as a date in JSON-LD", array("@value" => "2020-01-01", "@type" => "xsd:date"),
  termJSONLD(Term::find("bird_song"))["dcterms:created"]);
termForm(array("name" => "Changed", "parent" => "missing"));
list($out, $ok) = capture(function() { return(editTerm()); });
check("refuses a parent term that doesn't exist", !$ok && termRow("bird_song")["name"] == "Birdsong");
termForm(array("name" => "Changed", "parent" => "bird_song"));
list($out, $ok) = capture(function() { return(editTerm()); });
check("refuses the term as its own parent", !$ok && strpos($out, "its own parent or broader term") !== FALSE && termRow("bird_song")["parent"] == termRow("sound")["id"]);
termForm(array("name" => "Changed", "parent" => "sound", "language" => "en_GB"));
list($out, $ok) = capture(function() { return(editTerm()); });
check("refuses a language that isn't a language tag", !$ok && strpos($out, "must be a language tag") !== FALSE && termRow("bird_song")["language"] == "en");
$GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"] = "sound";
termForm(array("name" => "Sound", "broader" => "animal_sound"));
list($out, $ok) = capture(function() { return(editTerm()); });
check("refuses a broader term whose broader term is this term, which would make a loop",
  !$ok && strpos($out, "make a loop") !== FALSE && termRow("sound")["broader"] === null);
dbQuery("UPDATE ".table("terms")." SET `broader` = ? WHERE `shortname` = 'bird_song';", array(termRow("animal_sound")["id"]));
termForm(array("name" => "Sound", "broader" => "bird_song"));
list($out, $ok) = capture(function() { return(editTerm()); });
check("or a loop further up", !$ok && strpos($out, "make a loop") !== FALSE && termRow("sound")["broader"] === null);
dbQuery("UPDATE ".table("terms")." SET `broader` = NULL WHERE `shortname` = 'bird_song';");
termForm(array("name" => "Sound", "parent" => "bird_song"));
list($out, $ok) = capture(function() { return(editTerm()); });
check("refuses a parent term whose parent is this term", !$ok && strpos($out, "make a loop") !== FALSE && termRow("sound")["parent"] === null);
termForm(array("name" => "Sound", "parent" => "animal_sound"));
list($out, $ok) = capture(function() { return(editTerm()); });
check("but allows a parent term whose broader term is this term, as they are different kinds of link",
  $ok && termRow("sound")["parent"] == termRow("animal_sound")["id"]);
dbQuery("UPDATE ".table("terms")." SET `broader` = ? WHERE `shortname` = 'animal_sound';", array(termRow("bird_song")["id"]));
dbQuery("UPDATE ".table("terms")." SET `broader` = ? WHERE `shortname` = 'bird_song';", array(termRow("animal_sound")["id"]));
termForm(array("name" => "Sound", "broader" => "animal_sound"));
list($out, $ok) = capture(function() { return(editTerm()); });
check("stops following links at a loop saved before loops were refused", $ok && termRow("sound")["broader"] == termRow("animal_sound")["id"]);
dbQuery("UPDATE ".table("terms")." SET `broader` = NULL WHERE `shortname` IN ('sound', 'bird_song');");
dbQuery("UPDATE ".table("terms")." SET `broader` = ? WHERE `shortname` = 'animal_sound';", array(termRow("sound")["id"]));

section("Deleting terms");
$GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"] = "sound";
termForm(array("shortname" => "noise", "name" => "Noise", "invalid" => "Synonym", "parent" => "sound"));
capture(function() { return(addTerm()); });
list($out, $ok) = capture(function() { return(deleteTerm()); });
check("refuses to delete a term that has synonyms, naming them",
  !$ok && strpos($out, "synonyms of this term") !== FALSE && strpos($out, "noise") !== FALSE && termRow("sound") != null);
list($page) = capture(function() { template("admin-term-delete.php"); });
check("and the delete page says why instead of offering to delete", strpos($page, "noise") !== FALSE && strpos($page, "delete_term") === FALSE);
dbQuery("DELETE FROM ".table("terms")." WHERE `shortname` = 'noise';");
list($out, $ok) = capture(function() { return(deleteTerm()); });
check("deletes the term", $ok && termRow("sound") == null);
checkSame("clears parent links to it from terms that aren't synonyms", null, termRow("bird_song")["parent"]);
checkSame("clears broader links to it", null, termRow("animal_sound")["broader"]);

section("Controlled vocabularies");
$_POST = array("shortname" => "birds", "name" => "Birds", "description" => "", "reference" => "", "prefix" => "birds");
list($out, $ok) = capture(function() { return(addCV()); });
check("adds a vocabulary", $ok && array_key_exists("birds", getCVs()));
checkSame("with its namespace prefix", "birds", getCVs()["birds"]["prefix"]);
list($out, $ok) = capture(function() { return(addCV()); });
check("refuses a short name that is already used", !$ok && strpos($out, "already a controlled vocabulary") !== FALSE);
$_POST["shortname"] = "bird calls";
list($out, $ok) = capture(function() { return(addCV()); });
check("refuses a short name that isn't safe in a URI", !$ok && strpos($out, "can only use the letters") !== FALSE && !array_key_exists("bird calls", getCVs()));
$_POST = array("shortname" => "insects", "name" => "Insects", "description" => "", "reference" => "", "prefix" => "1insects");
list($out, $ok) = capture(function() { return(addCV()); });
check("refuses a namespace prefix that doesn't start with a letter", !$ok && strpos($out, "namespace prefix must") !== FALSE && !array_key_exists("insects", getCVs()));
termForm(array("shortname" => "robin", "name" => "Robin", "cv" => "birds"));
capture(function() { return(addTerm()); });
termForm(array("shortname" => "wren_song", "name" => "Wren song", "parent" => "robin"));
capture(function() { return(addTerm()); });
checkSame("lists the vocabulary's terms", array("robin"), array_column(getTerms("birds"), "shortname"));
$birds = Vocabulary::find("birds");
checkSame("Vocabulary::find() loads a vocabulary", "Birds", ($birds != null) ? $birds->name : null);
$birdTerms = ($birds != null) ? $birds->terms() : array();
checkSame("with its terms", array("robin"), $shortnames($birdTerms));
checkSame("and their child terms, even outside the vocabulary", array("wren_song"), (count($birdTerms) > 0) ? $shortnames($birdTerms[0]->children()) : null);
checkSame("its JSON-LD scheme is at the vocabulary's address", "https://glossary.example.org/cv/birds",
  ($birds != null) ? vocabularyJSONLD($birds, $birdTerms)["@graph"][0]["@id"] : null);
$birdScheme = ($birds != null) ? vocabularyJSONLD($birds, $birdTerms)["@graph"][0] : array();
checkSame("and gives its namespace prefix and URI", array("birds", "https://glossary.example.org/cv/birds#"),
  array(isset($birdScheme["vann:preferredNamespacePrefix"]) ? $birdScheme["vann:preferredNamespacePrefix"] : null,
    isset($birdScheme["vann:preferredNamespaceUri"]) ? $birdScheme["vann:preferredNamespaceUri"] : null));
termForm(array("shortname" => "api", "name" => "API", "cv" => "birds"));
list($out, $ok) = capture(function() { return(addTerm()); });
check("allows a term in a vocabulary to use a short name the site uses for its own pages", $ok && termRow("api") != null);
termForm(array("shortname" => "1990", "name" => "1990", "cv" => "birds", "opaque" => "opaque"));
list($out, $ok) = capture(function() { return(addTerm()); });
check("and an opaque term to use a short name made only of digits", $ok && termRow("1990") != null);
termForm(array("shortname" => "1991", "name" => "1991", "cv" => "birds"));
list($out, $ok) = capture(function() { return(addTerm()); });
check("but not a term in a vocabulary that isn't opaque, as its fragment could match an id", !$ok && termRow("1991") == null);
$GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"] = "api";
termForm(array("name" => "Moved", "cv" => "none"));
list($out, $ok) = capture(function() { return(editTerm()); });
check("editing refuses to move a term out of its vocabulary when the site uses its short name",
  !$ok && strpos($out, "own pages or files") !== FALSE && termRow("api")["cv"] == "birds" && termRow("api")["name"] == "API");
termForm(array("name" => "Application programming interface", "cv" => "birds"));
list($out, $ok) = capture(function() { return(editTerm()); });
check("but saves other changes to it", $ok && termRow("api")["name"] == "Application programming interface");
dbQuery("UPDATE ".table("terms")." SET `cv` = NULL WHERE `shortname` = 'api';");
termForm(array("name" => "API", "cv" => "none"));
list($out, $ok) = capture(function() { return(editTerm()); });
check("still edits a term saved outside a vocabulary before short names were checked", $ok && termRow("api")["name"] == "API");
termForm(array("name" => "API", "cv" => "birds"));
list($out, $ok) = capture(function() { return(editTerm()); });
check("and can move it into a vocabulary", $ok && termRow("api")["cv"] == "birds");
$GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"] = "1990";
termForm(array("name" => "1990", "cv" => "birds"));
list($out, $ok) = capture(function() { return(editTerm()); });
check("editing refuses to make a term whose short name is made only of digits not opaque",
  !$ok && strpos($out, "only of digits") !== FALSE && termRow("1990")["opaque"] == 1);
$GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"] = "birds";
$_POST = array("name" => "Birds", "description" => "", "reference" => "", "prefix" => "aves");
list($out, $ok) = capture(function() { return(editCV()); });
check("editing a vocabulary changes its namespace prefix", $ok && getCVs()["birds"]["prefix"] == "aves");
$_POST["prefix"] = "has space";
list($out, $ok) = capture(function() { return(editCV()); });
check("but refuses one that can't be used", !$ok && getCVs()["birds"]["prefix"] == "aves");
termForm(array("shortname" => "redbreast", "name" => "Redbreast", "invalid" => "Synonym", "parent" => "robin"));
capture(function() { return(addTerm()); });
termForm(array("shortname" => "erithacus", "name" => "Erithacus", "cv" => "birds", "invalid" => "Synonym", "parent" => "robin"));
capture(function() { return(addTerm()); });
list($out, $ok) = capture(function() { return(deleteCV()); });
check("refuses to delete a vocabulary whose terms have synonyms outside it, naming only those",
  !$ok && strpos($out, "redbreast") !== FALSE && strpos($out, "erithacus") === FALSE && array_key_exists("birds", getCVs()));
dbQuery("DELETE FROM ".table("terms")." WHERE `shortname` = 'redbreast';");
list($out, $ok) = capture(function() { return(deleteCV()); });
check("deletes the vocabulary and its terms, including its synonyms", $ok && !array_key_exists("birds", getCVs()) && termRow("robin") == null && termRow("erithacus") == null);
checkSame("clears links from other terms to the deleted terms", null, termRow("wren_song")["parent"]);

section("Site configuration");
$_POST = array("site_name" => "Glossary", "author" => "A. Author", "publisher" => "Natural History Museum", "default_lang" => "en",
  "base_url" => "glossary.example.org/", "description" => "Terms.", "license" => "https://creativecommons.org/licenses/by/4.0/", "prefix" => "gl");
list($out, $ok) = capture(function() { return(saveConfig()); });
check("saves the settings, including the linked data ones", $ok && getConfig()["publisher"] == "Natural History Museum"
  && getConfig()["license"] == "https://creativecommons.org/licenses/by/4.0/" && getConfig()["prefix"] == "gl");
$scheme = vocabularyJSONLD(Vocabulary::site(), array())["@graph"][0];
checkSame("the site's scheme gives the license", array("@id" => "https://creativecommons.org/licenses/by/4.0/"),
  isset($scheme["dcterms:license"]) ? $scheme["dcterms:license"] : null);
checkSame("and the publisher", "Natural History Museum", isset($scheme["dcterms:publisher"]) ? $scheme["dcterms:publisher"] : null);
checkSame("and the namespace prefix and URI", array("gl", "https://glossary.example.org/"),
  array(isset($scheme["vann:preferredNamespacePrefix"]) ? $scheme["vann:preferredNamespacePrefix"] : null,
    isset($scheme["vann:preferredNamespaceUri"]) ? $scheme["vann:preferredNamespaceUri"] : null));
$_POST["license"] = "CC BY 4.0";
list($out, $ok) = capture(function() { return(saveConfig()); });
check("refuses a license that isn't a web address, saving nothing",
  !$ok && strpos($out, "license must be a web address") !== FALSE && getConfig()["license"] == "https://creativecommons.org/licenses/by/4.0/");
$_POST["license"] = "";
$_POST["prefix"] = "g l";
list($out, $ok) = capture(function() { return(saveConfig()); });
check("refuses a namespace prefix that can't be used", !$ok && strpos($out, "namespace prefix must") !== FALSE && getConfig()["prefix"] == "gl");
dbQuery("DELETE FROM ".table("config")." WHERE `key` = 'publisher';");
$_POST["prefix"] = "gl";
$_POST["publisher"] = "Restored publisher";
list($out, $ok) = capture(function() { return(saveConfig()); });
check("saves a setting whose row is missing, as before the update that adds it", $ok && getConfig()["publisher"] == "Restored publisher");
$_POST["languages"] = " fr,  pt-BR ";
list($out, $ok) = capture(function() { return(saveConfig()); });
check("saves the other languages, separated by single spaces", $ok && getConfig()["languages"] == "fr pt-BR");
$_POST["languages"] = "fr ../lang/x";
list($out, $ok) = capture(function() { return(saveConfig()); });
check("refuses other languages that aren't language codes", !$ok && strpos($out, "Other languages must be language codes") !== FALSE && getConfig()["languages"] == "fr pt-BR");
$_POST["languages"] = "";
capture(function() { return(saveConfig()); });

section("Readiness report");
$issues = array();
foreach (siteReadinessIssues() as $issue) {
  $issues[$issue["id"]] = array_column($issue["items"], "label");
}
checkSame("reports that the site has no license", array("Site configuration"), isset($issues["license"]) ? $issues["license"] : null);
checkSame("and the terms without a definition", array("animal_sound", "bird_song", "wren_song"), isset($issues["definition"]) ? $issues["definition"] : null);
check("but not the site's namespace prefix, which is set", !isset($issues["prefix"]));

section("Users and logging in");
$_POST = array("first_name" => "Ada", "surname" => "Editor", "email" => " ada@example.org ", "password" => " correct horse ", "role" => "editor");
capture(function() { return(createUser()); });
$ada = loadUser("ada@example.org");
check("creates a user, trimming the email address", $ada != null && $ada["role"] == "editor");
list($out) = capture(function() { return(createUser()); });
check("refuses an email address that is already used", strpos($out, "already exists") !== FALSE);

checkSame("logs in with the right password, ignoring surrounding spaces", null, tryLogin("ada@example.org", "correct horse "));
checkSame("remembers who is logged in", "ada@example.org", $_SESSION["user"]);
$wrongPassword = tryLogin("ada@example.org", "wrong");
check("a failed login leaves the user logged out", !isset($_SESSION["user"]));
checkSame("a wrong password and an unknown email give the same message", $wrongPassword, tryLogin("nobody@example.org", "wrong"));
for ($i = 0; $i < LOGIN_MAX_PER_EMAIL; $i++) {
  tryLogin("ada@example.org", "wrong");
}
check("locks the account after too many failures", strpos((string)tryLogin("ada@example.org", "correct horse"), "Too many failed logins") !== FALSE);
$db->query("DELETE FROM ".table("login_attempts").";");
checkSame("logs in again once the failures have expired", null, tryLogin("ada@example.org", "correct horse"));

checkSame("the admin account can log in with the installer's password", null, tryLogin("admin", "password"));
check("but must then change it", !empty($_SESSION["must_change_password"]));
check("its cost 4 password hash is upgraded", !password_needs_rehash(passwordHashFor("admin"), PASSWORD_DEFAULT));

$legacyHash = password_hash($db->real_escape_string("it's"), PASSWORD_BCRYPT, array("cost" => 4));
dbQuery("INSERT INTO ".table("users")." (`email`, `password`) VALUES (?, ?);", array("legacy@example.org", $legacyHash));
checkSame("accepts a password hashed the old way, after SQL escaping", null, tryLogin("legacy@example.org", "it's"));
check("and re-hashes it the new way", password_verify("it's", passwordHashFor("legacy@example.org")));

section("Permissions");
$_SESSION["user"] = "ada@example.org";
check("an editor can edit terms", userAllow("edit-terms"));
check("an editor cannot create vocabularies", !userAllow("create-cv"));
check("an editor cannot administer the site", !userAllow("administer"));
$_SESSION["user"] = "legacy@example.org";
check("a user without a role can do nothing", !userAllow("edit-terms"));
$_SESSION["user"] = "admin";
check("user 1 can do everything", userAllow("delete-cv") && userAllow("manage-users"));
unset($_SESSION["user"]);
check("a logged out visitor can do nothing", !userAllow("edit-terms"));

section("Managing users");
$_SESSION["user"] = "admin";
$_POST = array("role" => "create-cv", "user_id" => $ada["id"]);
checkSame("changes another user's role", "Role updated", setUserRole());
checkSame("saves the new role", "create-cv", loadUser("ada@example.org")["role"]);
$_POST = array("role" => "", "user_id" => 1);
checkSame("admins cannot change their own role", "You cannot change your own role", setUserRole());
$_POST = array("first_name" => "Ada", "last_name" => "Lovelace", "email" => "legacy@example.org");
list($out, $ok) = capture(function() use ($ada) { return(updateUserDetails($ada)); });
check("refuses to give a user another user's email address", !$ok && strpos($out, "already exists") !== FALSE);
$_POST["email"] = "ada.lovelace@example.org";
list($out, $ok) = capture(function() use ($ada) { return(updateUserDetails($ada)); });
check("changes a user's name and email address", $ok && loadUser("ada.lovelace@example.org")["last_name"] == "Lovelace");
list($out, $ok) = capture(function() { return(deleteUser(1)); });
check("refuses to delete user 1", !$ok && getUser(1) != null);
$legacy = loadUser("legacy@example.org");
list($out, $ok) = capture(function() use ($legacy) { return(deleteUser($legacy["id"])); });
check("deletes a user", $ok && getUser($legacy["id"]) == null);

section("Changing your own settings");
$_SESSION["user"] = "admin";
$_SESSION["must_change_password"] = TRUE;
$_POST = array("first_name" => "Site", "last_name" => "Admin", "old_password" => "wrong", "new_password1" => "n3w-secret", "new_password2" => "n3w-secret");
list($out) = capture(function() { return(editUser()); });
check("refuses a wrong current password", strpos($out, "Current password is incorrect") !== FALSE);
$_POST["old_password"] = "password";
$_POST["new_password1"] = $_POST["new_password2"] = "password";
list($out) = capture(function() { return(editUser()); });
check("refuses the default password as a new password", strpos($out, "other than the default") !== FALSE);
$_POST["new_password1"] = $_POST["new_password2"] = "n3w-secret";
capture(function() { return(editUser()); });
check("changes the password", password_verify("n3w-secret", passwordHashFor("admin")));
check("no longer requires a password change", empty($_SESSION["must_change_password"]));
checkSame("stays logged in", "admin", $_SESSION["user"]);

section("Updating a 0.1 database");
resetDatabase("tests/fixtures/schema-0.1.sql");
$db->query("INSERT INTO ".table("users")." (`email`, `password`) VALUES ('twice@example.org', 'x'), ('twice@example.org', 'y');");
$db->query("INSERT INTO ".table("terms")." (`shortname`, `parent`, `broader`) VALUES ('orphan', 999, 998);");
$_SESSION["user"] = "admin";
$out = runUpdate();
check("runs the 0.2 step", strpos($out, "updated to version 0.2") !== FALSE);
check("stops before 0.3 and lists duplicated email addresses", strpos($out, "twice@example.org") !== FALSE);
checkSame("leaves the database at 0.2", "0.2", (string)getConfig()["version_db"]);
check("adds the reference column", $db->query("SELECT `reference` FROM ".table("terms")." LIMIT 1;") !== FALSE);
$db->query("DELETE FROM ".table("users")." WHERE `password` = 'y';");
$out = runUpdate();
check("runs the 0.3 step once the duplicate is removed", strpos($out, "updated to version 0.3") !== FALSE);
check("and then the 0.4 step", strpos($out, "updated to version 0.4") !== FALSE);
checkSame("leaving the database at 0.4", "0.4", (string)getConfig()["version_db"]);
check("email addresses must now be unique", !$db->query("INSERT INTO ".table("users")." (`email`) VALUES ('twice@example.org');"));
check("creates the login_attempts table", $db->query("SELECT 1 FROM ".table("login_attempts")." LIMIT 1;") !== FALSE);
$orphan = termRow("orphan");
check("clears links to terms that don't exist", $orphan["parent"] === null && $orphan["broader"] === null);
check("adds the created and modified columns, leaving existing terms without dates",
  array_key_exists("created", $orphan) && $orphan["created"] === null && $orphan["modified"] === null);
check("adds the vocabulary prefix column", $db->query("SELECT `prefix` FROM ".table("cv")." LIMIT 1;") !== FALSE);
checkSame("and the linked data settings, empty", array("", "", ""),
  array(getConfig()["publisher"], getConfig()["license"], getConfig()["prefix"]));
check("widens the language column for longer language tags", !termLanguageColumnTooNarrow());
check("running it again changes nothing", strpos(runUpdate(), "No updates required") !== FALSE);
$db->query("ALTER TABLE ".table("terms")." MODIFY COLUMN `language` VARCHAR(5) DEFAULT NULL;");
$out = runUpdate();
check("widens the language column of a database that was already at 0.4, keeping its version",
  strpos($out, "35 characters") !== FALSE && !termLanguageColumnTooNarrow() && (string)getConfig()["version_db"] == "0.4");

section("Two sites in one database");
$firstPrefix = $table_prefix;
$table_prefix = "second_";
runSQLFile("inst/ontomasticon.sql");
dbQuery("UPDATE ".table("config")." SET `value` = 'Second glossary' WHERE `key` = 'site_name';");
checkSame("a second site's tables have its prefix", 1, $db->query("SHOW TABLES LIKE 'second\\_config';")->num_rows);
checkSame("it reads its own configuration", "Second glossary", getConfig()["site_name"]);
check("and not the other site's terms", termRow("orphan") === null);
$table_prefix = $firstPrefix;
check("the other site still has its own", termRow("orphan") !== null && getConfig()["site_name"] != "Second glossary");
