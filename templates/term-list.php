<?php
//A list of terms, with their related terms loaded, given in $GLOBALS["ontomasticon"]["terms"]. On a glossary (see isGlossary()),
//they are shown in alphabetical order under a heading for each letter, with links to the letters above and below, and
//each term's acronym is listed as well, pointing to the term.
$terms = $GLOBALS["ontomasticon"]["terms"];
$glossary = isGlossary() && count($terms) > 0;
$groups = ($glossary) ? glossaryGroups(glossaryEntries($terms)) : array("" => $terms);
if ($glossary) {
  print glossaryIndex($groups);
}
$oe = 1;
foreach ($groups as $letter => $group) {
  if ($glossary) {
    print '<h2 class="glossary-letter" id="'.h(glossaryAnchor($letter)).'">'.h($letter).'</h2>';
  }
  foreach ($group as $entry) {
    $term = ($glossary) ? $entry["term"] : $entry;
    //An acronym points to the term's entry, which is in the same list
    if ($glossary && $entry["see"]) {
      print '<p class="glossary-see">'.h($entry["label"]).', '.h(t("see")).' <a href="#'.h($term->anchor()).'">'.h(glossaryLabel($term)).'</a></p>';
      continue;
    }
    $GLOBALS["ontomasticon"]["term"] = $term;
    $GLOBALS["ontomasticon"]["oddeven"] = oe($oe);
    template("term-fragment.php");
    $oe *= -1;
  }
}
if ($glossary) {
  print glossaryIndex($groups);
}
