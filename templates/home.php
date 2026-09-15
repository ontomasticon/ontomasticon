<div id="description"><?php print tu("description"); ?></div>
<?php
global $db;
if ($GLOBALS["ontomasticon"]["cv_count"] > 0) {
  printCVs(getCVs($db));
}
$GLOBALS["ontomasticon"]["terms"] = getTerms();
template("term-list.php");
