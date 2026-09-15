<?php
//The page for a term outside a vocabulary, at its URI
$GLOBALS["ontomasticon"]["term"] = currentPageTerm();
$GLOBALS["ontomasticon"]["oddeven"] = "odd";
template("term-fragment.php");
print "<p class='all-terms'>".l("All terms", "/")."</p>";
