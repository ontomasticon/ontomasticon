<h1>Install ontomasticon</h1>

<h2>Checking database configuration</h2>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (file_exists("../settings/db.php")) {
  print("settings/db.php exists");
  include("../settings/db.php");
} else {
  print("<p>settings/db.php does not exist!</p>");
  print("<p>Refer to <a href='https://ontomasticon.github.io/installation.html'>Installation instructions.</a></p>");
  exit;
}
?>

<h2>Attempting to conect to database</h2>
<?php
if ($db->connect_error) {
    die("Connection failed: " . $conn->connect_error);
 }
   echo "<p>Connected successfully</p>";
?>

<h2>Checking database is empty</h2>
<?php
$sql = "SELECT COUNT(DISTINCT `table_name`) as `count` FROM `information_schema`.`columns` WHERE `table_schema` = DATABASE();";
$res = $db->query($sql);

if ($res->fetch_assoc()["count"] == 0) {
    print("<p>Database is empty.</p>");
} else {
    print("Datbase is not empty");
    exit;
}
?>

<h2>Creating tables</h2>
<?php
$templine = '';
$lines = file("ontomasticon.sql");
foreach ($lines as $line) {
  if (substr($line, 0, 2) == '--' || $line == '') { continue; }
  $templine .= $line;
  if (substr(trim($line), -1, 1) == ';') {
    $db->query($templine) or print('Error performing query \'<strong>' . $templine . '\': ' . mysql_error() . '<br /><br />');
    $templine = '';
  }
}
?>
<p>Done</p>

<h2>Setting base_url</h2>
<?php 
print(htmlspecialchars($_SERVER['SERVER_NAME'], ENT_QUOTES, 'UTF-8'));
$base_url = $_SERVER['SERVER_NAME']."/";
$stmt = $db->prepare("INSERT INTO `config` VALUES('base_url', ?);");
$stmt->bind_param("s", $base_url);
$stmt->execute();
?>

<h2>Done!</h2>
<p>Further steps to secure the installation will be provided when you first log in.</p>
<p>Login details are admin:password.</p>
<p><a href="https://<?php print($_SERVER['SERVER_NAME']); ?>">Go to homepage</a>.</p>