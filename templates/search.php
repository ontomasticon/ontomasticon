<?php
//The terms matching a search from the search box in the page header
$query = searchQuery();
?>
<h2><?php print h(t("Search results for")." “".$query."”"); ?></h2>
<?php
$GLOBALS["ontomasticon"]["terms"] = searchTerms($query);
if (count($GLOBALS["ontomasticon"]["terms"]) == 0) {
  print "<p>".h(t("No terms match your search."))."</p>";
} else {
  template("term-list.php");
}
print "<p>".l(t("All terms"), "/")."</p>";
