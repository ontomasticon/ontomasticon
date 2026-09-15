<?php
//A list of terms, given as rows of the terms table in $GLOBALS["ontomasticon"]["terms"]. With the glossary display
//setting, they are shown in alphabetical order under a heading for each letter, with links to the letters above and below.
$terms = $GLOBALS["ontomasticon"]["terms"];
$glossary = glossaryDisplay() && count($terms) > 0;
$groups = ($glossary) ? glossaryGroups($terms) : array("" => $terms);
if ($glossary) {
  print glossaryIndex($groups);
}
$oe = 1;
foreach ($groups as $letter => $group) {
  if ($glossary) {
    print '<h2 class="glossary-letter" id="'.h(glossaryAnchor($letter)).'">'.h($letter).'</h2>';
  }
  foreach ($group as $term) {
    $GLOBALS["ontomasticon"]["term"] = $term;
    $GLOBALS["ontomasticon"]["oddeven"] = oe($oe);
    template("term-fragment.php");
    $oe *= -1;
  }
}
if ($glossary) {
  print glossaryIndex($groups);
}
