<?php
// Ontomasticon tests. Run with:  php tests/run.php
//
// Unit tests always run. Database tests also run when these environment variables are set:
//   TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASSWORD, TEST_DB_NAME
// WARNING: the database tests drop every table in TEST_DB_NAME. Never point them at a real site.

error_reporting(E_ALL);
chdir(dirname(__DIR__));

//Hold output until the end, so the code under test can still start and change sessions
ob_start();
session_start(array("use_cookies" => 0, "cache_limiter" => ""));

$GLOBALS["test_count"] = 0;
$GLOBALS["test_failures"] = array();
$GLOBALS["test_section"] = "";

//PHP warnings and notices count as failures; deprecations are reported but don't fail the run
set_error_handler(function($severity, $message, $file, $line) {
  if (!(error_reporting() & $severity)) {
    return(FALSE);
  }
  $text = $message." in ".$file." on line ".$line;
  if ($severity == E_DEPRECATED || $severity == E_USER_DEPRECATED) {
    print "  DEPRECATED ".$text."\n";
  } else {
    failTest("PHP error: ".$text);
  }
  return(TRUE);
});

function section($name) {
  $GLOBALS["test_section"] = $name;
  print "\n".$name."\n";
}

function failTest($message) {
  $GLOBALS["test_failures"][] = $GLOBALS["test_section"].": ".$message;
  print "  FAIL ".$message."\n";
}

//Check that a condition is TRUE
function check($description, $condition) {
  $GLOBALS["test_count"]++;
  if ($condition) {
    print "  ok   ".$description."\n";
  } else {
    failTest($description);
  }
}

//Check that a value is exactly what was expected
function checkSame($description, $expected, $actual) {
  $GLOBALS["test_count"]++;
  if ($expected === $actual) {
    print "  ok   ".$description."\n";
  } else {
    failTest($description."\n         expected: ".var_export($expected, TRUE)."\n         actual:   ".var_export($actual, TRUE));
  }
}

//Run a function, returning what it printed and what it returned
function capture($fn) {
  ob_start();
  $result = $fn();
  $output = ob_get_clean();
  return(array($output, $result));
}

//The codebase version, read the same way the update check reads it
preg_match('/^\$version = ([0-9."\']+);/m', file_get_contents("index.php"), $matches);
$version = trim($matches[1], "\"'");

require("core/core.php");

require("tests/unit.php");

if (getenv("TEST_DB_NAME")) {
  require("tests/database.php");
} else {
  print "\nDatabase tests skipped: set TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASSWORD and TEST_DB_NAME to run them.\n";
}

$failures = count($GLOBALS["test_failures"]);
print "\n".$GLOBALS["test_count"]." checks, ".$failures." failed\n";
foreach ($GLOBALS["test_failures"] as $failure) {
  print "  - ".$failure."\n";
}
ob_end_flush();
exit(($failures > 0) ? 1 : 0);
