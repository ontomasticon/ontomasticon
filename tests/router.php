<?php
// Router for PHP's built-in web server, used by the HTTP tests:
//   php -S 127.0.0.1:8765 tests/router.php
// Serves files that exist directly and sends everything else to index.php, as .htaccess does.

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
if ($path != "/" && is_file(dirname(__DIR__).$path)) {
  return(FALSE);
}
chdir(dirname(__DIR__));
require("index.php");
