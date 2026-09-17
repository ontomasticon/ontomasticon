<?php
//The MCP server (see core/mcp.php). One byte more than a request may have is read, to tell when a request is too large.
$response = mcpResponse($_SERVER["REQUEST_METHOD"], mcpRequestHeaders(), (string)file_get_contents("php://input", FALSE, null, 0, MCP_BODY_LIMIT + 1));
http_response_code($response["status"]);
foreach ($response["headers"] as $header) {
  header($header);
}
if ($response["body"] !== null) {
  print($response["body"]);
}
exit;
