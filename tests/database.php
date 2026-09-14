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

//Drop every table, then run an SQL file the way the installer does
function resetDatabase($sqlFile) {
  global $db;
  $result = $db->query("SHOW TABLES;");
  foreach ($result->fetch_all() as $table) {
    $db->query("DROP TABLE `".$table[0]."`;");
  }
  $statement = "";
  foreach (file($sqlFile) as $line) {
    if (substr($line, 0, 2) == '--' || trim($line) == '') { continue; }
    $statement .= $line;
    if (substr(trim($line), -1, 1) == ';') {
      if (!$db->query($statement)) {
        failTest("Setting up from ".$sqlFile." failed: ".$db->error);
      }
      $statement = "";
    }
  }
  $db->query("INSERT INTO `config` VALUES('base_url', 'glossary.example.org/');");
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
  $result = dbQuery("SELECT * FROM `terms` WHERE `shortname` = ?;", array($shortname));
  return(($result) ? $result->fetch_assoc() : null);
}

function passwordHashFor($email) {
  $result = dbQuery("SELECT `password` FROM `users` WHERE `email` = ?;", array($email));
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
check("terms have a reference column", $db->query("SELECT `reference` FROM `terms` LIMIT 1;") !== FALSE);
check("the login_attempts table exists", $db->query("SELECT 1 FROM `login_attempts` LIMIT 1;") !== FALSE);

section("Adding terms");
termForm(array("shortname" => "sound", "name" => "Sound"));
list($out, $ok) = capture(function() { return(addTerm()); });
check("adds a term", $ok && termRow("sound") != null);
termForm(array("shortname" => "sound", "name" => "Duplicate"));
list($out, $ok) = capture(function() { return(addTerm()); });
check("refuses a short name that is already used", !$ok && strpos($out, "already a term") !== FALSE);
checkSame("keeps the original term", "Sound", termRow("sound")["name"]);
termForm(array("shortname" => ""));
list($out, $ok) = capture(function() { return(addTerm()); });
check("refuses an empty short name", !$ok && strpos($out, "short name is required") !== FALSE);
termForm(array("shortname" => "song", "parent" => "missing"));
list($out, $ok) = capture(function() { return(addTerm()); });
check("refuses a parent term that doesn't exist", !$ok && strpos($out, "no term with the short name") !== FALSE && termRow("song") == null);
termForm(array("shortname" => "bird_song", "name" => "Bird song", "parent" => "sound", "reference" => "Smith 2020"));
capture(function() { return(addTerm()); });
termForm(array("shortname" => "animal_sound", "name" => "Animal sound", "broader" => "sound"));
capture(function() { return(addTerm()); });
checkSame("saves the parent as the parent's id", termRow("sound")["id"], termRow("bird_song")["parent"]);
checkSame("saves the reference", "Smith 2020", termRow("bird_song")["reference"]);
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
termForm(array("name" => "Birdsong", "parent" => "sound", "reference" => "Jones 2021"));
list($out, $ok) = capture(function() { return(editTerm()); });
check("saves changes", $ok && termRow("bird_song")["name"] == "Birdsong" && termRow("bird_song")["reference"] == "Jones 2021");
termForm(array("name" => "Changed", "parent" => "missing"));
list($out, $ok) = capture(function() { return(editTerm()); });
check("refuses a parent term that doesn't exist", !$ok && termRow("bird_song")["name"] == "Birdsong");

section("Deleting terms");
$GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"] = "sound";
list($out, $ok) = capture(function() { return(deleteTerm()); });
check("deletes the term", $ok && termRow("sound") == null);
checkSame("clears parent links to it", null, termRow("bird_song")["parent"]);
checkSame("clears broader links to it", null, termRow("animal_sound")["broader"]);

section("Controlled vocabularies");
$_POST = array("shortname" => "birds", "name" => "Birds", "description" => "", "reference" => "");
list($out, $ok) = capture(function() { return(addCV()); });
check("adds a vocabulary", $ok && array_key_exists("birds", getCVs()));
list($out, $ok) = capture(function() { return(addCV()); });
check("refuses a short name that is already used", !$ok && strpos($out, "already a controlled vocabulary") !== FALSE);
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
$GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"] = "birds";
list($out, $ok) = capture(function() { return(deleteCV()); });
check("deletes the vocabulary and its terms", $ok && !array_key_exists("birds", getCVs()) && termRow("robin") == null);
checkSame("clears links from other terms to the deleted terms", null, termRow("wren_song")["parent"]);

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
$db->query("DELETE FROM `login_attempts`;");
checkSame("logs in again once the failures have expired", null, tryLogin("ada@example.org", "correct horse"));

checkSame("the admin account can log in with the installer's password", null, tryLogin("admin", "password"));
check("but must then change it", !empty($_SESSION["must_change_password"]));
check("its cost 4 password hash is upgraded", !password_needs_rehash(passwordHashFor("admin"), PASSWORD_DEFAULT));

$legacyHash = password_hash($db->real_escape_string("it's"), PASSWORD_BCRYPT, array("cost" => 4));
dbQuery("INSERT INTO `users` (`email`, `password`) VALUES (?, ?);", array("legacy@example.org", $legacyHash));
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
$db->query("INSERT INTO `users` (`email`, `password`) VALUES ('twice@example.org', 'x'), ('twice@example.org', 'y');");
$db->query("INSERT INTO `terms` (`shortname`, `parent`, `broader`) VALUES ('orphan', 999, 998);");
$_SESSION["user"] = "admin";
$out = runUpdate();
check("runs the 0.2 step", strpos($out, "updated to version 0.2") !== FALSE);
check("stops before 0.3 and lists duplicated email addresses", strpos($out, "twice@example.org") !== FALSE);
checkSame("leaves the database at 0.2", "0.2", (string)getConfig()["version_db"]);
check("adds the reference column", $db->query("SELECT `reference` FROM `terms` LIMIT 1;") !== FALSE);
$db->query("DELETE FROM `users` WHERE `password` = 'y';");
$out = runUpdate();
check("runs the 0.3 step once the duplicate is removed", strpos($out, "updated to version 0.3") !== FALSE);
checkSame("leaves the database at 0.3", "0.3", (string)getConfig()["version_db"]);
check("email addresses must now be unique", !$db->query("INSERT INTO `users` (`email`) VALUES ('twice@example.org');"));
check("creates the login_attempts table", $db->query("SELECT 1 FROM `login_attempts` LIMIT 1;") !== FALSE);
$orphan = termRow("orphan");
check("clears links to terms that don't exist", $orphan["parent"] === null && $orphan["broader"] === null);
check("running it again changes nothing", strpos(runUpdate(), "No updates required") !== FALSE);
