<?php
if ($GLOBALS["ontomasticon"]["pageInfo"]["active_page"] == "") {
  //cv/ without a vocabulary name lists the vocabularies
  if (count($GLOBALS["ontomasticon"]["CVs"]) > 0) {
    printCVs($GLOBALS["ontomasticon"]["CVs"]);
  } else {
    print t("There are no controlled vocabularies yet");
  }
  return;
}

$activeCV = null;
foreach ($GLOBALS["ontomasticon"]["CVs"] as $CV) {
  if ($CV["shortname"] == $GLOBALS["ontomasticon"]["pageInfo"]["active_page"]) {
    $activeCV = $CV;
  }
}

if ($activeCV == null) {
  print t("No matching controlled vocabulary found for")." ".h($GLOBALS["ontomasticon"]["pageInfo"]["active_page"]);
  //An old or mistyped link may be meant for one of the site's vocabularies
  if (count($GLOBALS["ontomasticon"]["CVs"]) > 0) {
    printCVs($GLOBALS["ontomasticon"]["CVs"]);
  }
} else {
  ?>
  <h2><?php print t("Controlled Vocabulary").": ".h($activeCV["name"]); ?></h2>
  <div id="description"><?php print $activeCV["description"]; ?></div>
<?php
  $GLOBALS["ontomasticon"]["terms"] = validTerms(currentPageTerms());
  template("term-list.php");
}
