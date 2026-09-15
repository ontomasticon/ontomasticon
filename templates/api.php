<?php
switch($GLOBALS["ontomasticon"]["pageInfo"]["active_page"]) {
    case "":
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
};
