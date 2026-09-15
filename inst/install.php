<h1>Install ontomasticon</h1>

<h2>Checking database configuration</h2>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//Report connection and query failures below rather than throwing exceptions (the default from PHP 8.1)
mysqli_report(MYSQLI_REPORT_OFF);

if (file_exists("../settings/db.php")) {
  print("settings/db.php exists");
  include("../settings/db.php");
} else {
  print("<p>settings/db.php does not exist!</p>");
  print("<p>Refer to <a href='https://ontomasticon.github.io/installation.html'>Installation instructions.</a></p>");
  exit;
}
?>

<h2>Attempting to connect to database</h2>
<?php
if ($db->connect_error) {
    die("Connection failed: " . htmlspecialchars($db->connect_error, ENT_QUOTES, 'UTF-8'));
 }
   echo "<p>Connected successfully</p>";

require("../core/util.php");
if (!validTablePrefix(tablePrefix())) {
  print("<p>The table prefix in settings/db.php may only use letters A to Z, digits and underscores.</p>");
  exit;
}
?>

<h2>Checking the site's tables don't exist yet</h2>
<?php
//Other sites may keep their tables in the same database, with a different table prefix
$tables = array();
foreach (array("config", "cv", "terms", "users", "login_attempts") as $name) {
  $tables[] = tablePrefix().$name;
}
$stmt = $db->prepare("SELECT COUNT(*) AS `count` FROM `information_schema`.`tables` WHERE `table_schema` = DATABASE() AND `table_name` IN (?, ?, ?, ?, ?);");
$stmt->bind_param("sssss", ...$tables);
$stmt->execute();

if ($stmt->get_result()->fetch_assoc()["count"] == 0) {
    print("<p>None of the tables exist.</p>");
} else {
    print("<p>Ontomasticon is already installed in this database with the table prefix '".htmlspecialchars(tablePrefix(), ENT_QUOTES, 'UTF-8')."'. To install another site in the same database, set a different \$table_prefix in settings/db.php.</p>");
    exit;
}
?>

<h2>Creating tables</h2>
<?php
$templine = '';
$lines = file("ontomasticon.sql");
foreach ($lines as $line) {
  if (substr($line, 0, 2) == '--' || trim($line) == '') { continue; }
  $templine .= $line;
  if (substr(trim($line), -1, 1) == ';') {
    $db->query(prefixTables($templine)) or print('Error performing query \'<strong>' . htmlspecialchars($templine, ENT_QUOTES, 'UTF-8') . '</strong>\': ' . htmlspecialchars($db->error, ENT_QUOTES, 'UTF-8') . '<br /><br />');
    $templine = '';
  }
}
?>
<p>Done</p>

<h2>Setting base_url</h2>
<?php
//The site is installed in the directory above inst/, which may be a subdirectory of the domain
$base_url = $_SERVER['SERVER_NAME'].rtrim(str_replace("\\", "/", dirname(dirname($_SERVER['SCRIPT_NAME']))), "/")."/";
print(htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'));
$stmt = $db->prepare("INSERT INTO ".table("config")." VALUES('base_url', ?);");
$stmt->bind_param("s", $base_url);
$stmt->execute();
?>

<h2>Done!</h2>
<p>Further steps to secure the installation will be provided when you first log in.</p>
<p>Login details are admin:password.</p>
<p><a href="https://<?php print(htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8')); ?>">Go to homepage</a>.</p>
