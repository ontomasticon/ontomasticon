<h2><?php print t("Linked data readiness"); ?></h2>

<?php
if (!userAllow("edit-terms")) {
  print t("You do not have permission to administer this site");
} else {
  ?>
  <p><?php print t("Problems that stop the vocabularies meeting TDWG's requirements for controlled vocabulary terms, or giving clean RDF in JSON-LD and Turtle."); ?></p>
  <?php
  $issues = siteReadinessIssues();
  if (count($issues) == 0) {
    printStatus(t("No problems found."));
  }
  foreach ($issues as $issue) {
    print "<h3 id='".h($issue["id"])."'>".h($issue["problem"])." (".count($issue["items"]).")</h3>";
    print "<ul class='readiness'>";
    foreach ($issue["items"] as $item) {
      //Labels are names from the site's data, so they are escaped but not translated. Links are paths within the site.
      print "<li><a href='".h(sitePath($item["link"]))."'>".h($item["label"])."</a></li>";
    }
    print "</ul>";
  }
}
