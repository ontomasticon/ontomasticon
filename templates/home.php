<?php
//The search box in the page header shows its results on the home page
if (searchPage()) {
  template("search.php");
  return;
}
?>
<div id="description"><?php print tu("description"); ?></div>
<?php
global $db;
if ($GLOBALS["ontomasticon"]["cv_count"] > 0) {
  printCVs(getCVs($db));
}
$GLOBALS["ontomasticon"]["terms"] = validTerms(currentPageTerms());
template("term-list.php");
