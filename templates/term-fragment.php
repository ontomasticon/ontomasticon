<div class="term <?php print $GLOBALS["ontomasticon"]["oddeven"]; ?>" id="<?php print h(termAnchor($GLOBALS["ontomasticon"]["term"])); ?>">
  <h3><?php
    print h($GLOBALS["ontomasticon"]["term"]["name"]);
    print " ";
    print termEditLink($GLOBALS["ontomasticon"]["term"]["shortname"]);
  ?></h3>
  <p class="term-uri"><?php print term2URI($GLOBALS["ontomasticon"]["term"], TRUE); ?></p>
  <?php
  //Most terms are concepts, so only other types are shown
  $termType = termType(isset($GLOBALS["ontomasticon"]["term"]["type"]) ? $GLOBALS["ontomasticon"]["term"]["type"] : null);
  if ($termType != "concept") {
    print '<p class="term-type">'.h(t(termTypeLabels()[$termType])).'</p>';
  }
  //Where a property's values come from
  $termRow = $GLOBALS["ontomasticon"]["term"];
  $datatypes = termDatatypes();
  if ($termType == "property" && isset($termRow["datatype"]) && isset($datatypes[$termRow["datatype"]])) {
    print '<p class="term-values">'.h(t("Values")).": ".h(t($datatypes[$termRow["datatype"]]["label"])).'</p>';
  } elseif ($termType == "property" && isset($termRow["range_cv"]) && isset($GLOBALS["ontomasticon"]["CVs"][$termRow["range_cv"]])) {
    $valuesCV = $GLOBALS["ontomasticon"]["CVs"][$termRow["range_cv"]];
    print '<p class="term-values">'.h(t("Values")).": ".l($valuesCV["name"], "/cv/".$valuesCV["shortname"]).'</p>';
  }
  ?>
  <p class="term-language"><?php print h($GLOBALS["ontomasticon"]["term"]["language"]); ?></p>
  <p class="term-description"><?php print $GLOBALS["ontomasticon"]["term"]["description"]; ?></p>
  <?php
  template("term-fragment-reference.php");

  if (is_array($GLOBALS["ontomasticon"]["term"]["children"]) && count($GLOBALS["ontomasticon"]["term"]["children"]) > 0) {
  ?>
    <h4><?php print t("Related terms"); ?></h4>
    <table>
    <?php
    foreach ($GLOBALS["ontomasticon"]["term"]["children"] as $child) {
      print "<tr>";
      print "<td class='invalid_reason'>".h(t($child["invalid_reason"]))."</td>";
      print "<td class='child_term_name'><a href='".h(term2URI($child))."'>".h($child["name"])."</a></td>";
      print "<td class='child_term_language'>".h($child["language"])."</td>";
      print "<td class='child_term_editlink'>".termEditLink($child["shortname"])."</td>";
      print "</tr>";
    }
    ?>
    </table>
  <?php
  }
  ?>

  <?php
  if (is_array($GLOBALS["ontomasticon"]["term"]["broader"]) && count($GLOBALS["ontomasticon"]["term"]["broader"]) > 0) {
    ?>
    <h4><?php print t("Broader term"); ?></h4>
    <table>
    <?php
    foreach ($GLOBALS["ontomasticon"]["term"]["broader"] as $child) {
      print "<tr>";
      print "<td class='invalid_reason'>".h(t($child["invalid_reason"]))."</td>";
      print "<td class='child_term_name'><a href='".h(term2URI($child))."'>".h($child["name"])."</a></td>";
      print "<td class='child_term_language'>".h($child["language"])."</td>";
      print "<td class='child_term_editlink'>".termEditLink($child["shortname"])."</td>";
      print "</tr>";
    }
    ?>
    </table>
  <?php
  }
  ?>

  <?php
  if (is_array($GLOBALS["ontomasticon"]["term"]["narrower"]) && count($GLOBALS["ontomasticon"]["term"]["narrower"]) > 0) {
    ?>
    <h4><?php print t("Narrower terms"); ?></h4>
    <table>
    <?php
    foreach ($GLOBALS["ontomasticon"]["term"]["narrower"] as $child) {
      print "<tr>";
      print "<td class='invalid_reason'>".h(t($child["invalid_reason"]))."</td>";
      print "<td class='child_term_name'><a href='".h(term2URI($child))."'>".h($child["name"])."</a></td>";
      print "<td class='child_term_language'>".h($child["language"])."</td>";
      print "<td class='child_term_editlink'>".termEditLink($child["shortname"])."</td>";
      print "</tr>";
    }
    ?>
    </table>
  <?php
  }
  ?>
</div>
