<?php
//A term's references (see referenceList()), numbered so that [1] in the definition is the first, [2] the second and so on
$references = referenceList($GLOBALS["ontomasticon"]["term"]->reference);
if (count($references) == 1) {
  print "<p class='term-reference'>".t("Reference").": [1] ".$references[0]."</p>";
} elseif (count($references) > 1) {
  print "<div class='term-reference'>".t("References").":<ol class='term-references'>";
  foreach ($references as $reference) {
    print "<li>".$reference."</li>";
  }
  print "</ol></div>";
}
