<?php
switch($GLOBALS["ontomasticon"]["pageInfo"]["active_page"]) {
    case "":
        //The API page is shown in the visitor's language, like other pages
        header("Vary: Accept-Language", FALSE);
        template("core.php");
        break;
    case "term":
        template("api-term.php");
        break;
    case "cv":
        template("api-cv.php");
        break;
    case "search":
        template("api-search.php");
        break;
    case "mcp":
        template("api-mcp.php");
        break;
    default:
        //An address in the API that doesn't exist
        http_response_code(404);
};
