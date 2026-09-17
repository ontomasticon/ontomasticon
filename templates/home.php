<?php
//The search box in the page header shows its results on the home page
if (searchPage()) {
  template("search.php");
  return;
}
?>
<div id="description"><?php print tu("description"); ?></div>
<?php
if (count($GLOBALS["ontomasticon"]["CVs"]) > 0) {
  printCVs($GLOBALS["ontomasticon"]["CVs"]);
}
$GLOBALS["ontomasticon"]["terms"] = validTerms(currentPageTerms());
template("term-list.php");
