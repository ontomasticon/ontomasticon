<?php
$error = termDeleteError(termID($GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"]));
if ($error !== null) {
  printError($error);
} else {
?>
<form action="<?php print formAction(); ?>" method="post"><?php print csrfField(); ?>
  <p><?php print t("Warning! This cannot be undone."); ?></p>
  <button type="submit" name="delete_term"><?php print t("Delete term"); ?></button>
</form>
<?php
}
