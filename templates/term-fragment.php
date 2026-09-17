<?php
//A term's entry, for the term in $GLOBALS["ontomasticon"]["term"], with its related terms loaded
$term = $GLOBALS["ontomasticon"]["term"];
?>
<div class="term <?php print $GLOBALS["ontomasticon"]["oddeven"]; ?>" id="<?php print h($term->anchor()); ?>">
  <h3><?php
    print h($term->name);
    print " ";
    print termEditLink($term->shortname);
  ?></h3>
  <p class="term-uri"><?php print l($term->uri(), $term->uri()); ?></p>
  <?php
  //A glossary gives a term's acronym
  if (isGlossary() && $term->acronym != "") {
    print '<p class="term-acronym">'.h(t("Acronym")).": ".h($term->acronym).'</p>';
  }
  //Most terms are concepts, so only other types are shown
  if ($term->type != "concept") {
    print '<p class="term-type">'.h(t(termTypeLabels()[$term->type])).'</p>';
  }
  //Where a property's values come from
  $datatypes = termDatatypes();
  if ($term->type == "property" && isset($datatypes[(string)$term->datatype])) {
    print '<p class="term-values">'.h(t("Values")).": ".h(t($datatypes[$term->datatype]["label"])).'</p>';
  } elseif ($term->type == "property" && isset($GLOBALS["ontomasticon"]["CVs"][(string)$term->rangeCV])) {
    $valuesCV = $GLOBALS["ontomasticon"]["CVs"][$term->rangeCV];
    print '<p class="term-values">'.h(t("Values")).": ".l($valuesCV["name"], "/cv/".$valuesCV["shortname"]).'</p>';
  }
  ?>
  <p class="term-language"><?php print h($term->language); ?></p>
  <p class="term-description"><?php print $term->description; ?></p>
  <?php
  template("term-fragment-reference.php");

  //The parent term is related too: a synonym shows the term it is a synonym of, as that term shows its synonyms
  $parentTerms = ($term->parent() === null) ? array() : array($term->parent());
  $children = termPageChildren($term);
  $relatedTerms = $term->related();
  if (count($parentTerms) > 0 || count($relatedTerms) > 0 || count($children) > 0) {
  ?>
    <h4><?php print t("Related terms"); ?></h4>
    <table>
    <?php
    foreach ($parentTerms as $parentTerm) {
      print "<tr>";
      print "<td class='invalid_reason'>".(($term->isSynonym()) ? h(t("Synonym of")) : "")."</td>";
      print "<td class='child_term_name'><a href='".h($parentTerm->uri())."'>".h($parentTerm->name)."</a></td>";
      print "<td class='child_term_language'>".h($parentTerm->language)."</td>";
      print "<td class='child_term_editlink'>".termEditLink($parentTerm->shortname)."</td>";
      print "</tr>";
    }
    foreach ($children as $child) {
      //A synonym in a vocabulary has no entry of its own, so the fragment of its URI is its row here, in the entry of the term it is a synonym of
      $synonymHere = ($child->isSynonym() && $child->cv != "" && $child->cv == $term->cv);
      print "<tr".(($synonymHere) ? " id='".h($child->anchor())."'" : "").">";
      print "<td class='invalid_reason'>".h(t($child->invalidReason))."</td>";
      print "<td class='child_term_name'><a href='".h($child->uri())."'>".h($child->name)."</a></td>";
      print "<td class='child_term_language'>".h($child->language)."</td>";
      print "<td class='child_term_editlink'>".termEditLink($child->shortname)."</td>";
      print "</tr>";
    }
    //Related terms that aren't already listed as the parent or a child
    $listed = array_column(array_merge($parentTerms, $children), "id");
    foreach ($relatedTerms as $relatedTerm) {
      if (in_array($relatedTerm->id, $listed)) {
        continue;
      }
      print "<tr>";
      print "<td class='invalid_reason'></td>";
      print "<td class='child_term_name'><a href='".h($relatedTerm->uri())."'>".h($relatedTerm->name)."</a></td>";
      print "<td class='child_term_language'>".h($relatedTerm->language)."</td>";
      print "<td class='child_term_editlink'>".termEditLink($relatedTerm->shortname)."</td>";
      print "</tr>";
    }
    ?>
    </table>
  <?php
  }
  ?>

  <?php
  $broaderTerms = ($term->broader() === null) ? array() : array($term->broader());
  if (count($broaderTerms) > 0) {
    ?>
    <h4><?php print t("Broader term"); ?></h4>
    <table>
    <?php
    foreach ($broaderTerms as $child) {
      print "<tr>";
      print "<td class='invalid_reason'>".h(t($child->invalidReason))."</td>";
      print "<td class='child_term_name'><a href='".h($child->uri())."'>".h($child->name)."</a></td>";
      print "<td class='child_term_language'>".h($child->language)."</td>";
      print "<td class='child_term_editlink'>".termEditLink($child->shortname)."</td>";
      print "</tr>";
    }
    ?>
    </table>
  <?php
  }
  ?>

  <?php
  if (count($term->narrower()) > 0) {
    ?>
    <h4><?php print t("Narrower terms"); ?></h4>
    <table>
    <?php
    foreach ($term->narrower() as $child) {
      print "<tr>";
      print "<td class='invalid_reason'>".h(t($child->invalidReason))."</td>";
      print "<td class='child_term_name'><a href='".h($child->uri())."'>".h($child->name)."</a></td>";
      print "<td class='child_term_language'>".h($child->language)."</td>";
      print "<td class='child_term_editlink'>".termEditLink($child->shortname)."</td>";
      print "</tr>";
    }
    ?>
    </table>
  <?php
  }
  ?>
</div>
