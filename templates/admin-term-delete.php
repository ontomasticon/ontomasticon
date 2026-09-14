<form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post">
  <p><?php print t("Warning! This cannot be undone."); ?></p>
  <button type="submit" name="delete_term"><?php print t("Delete terms"); ?></button>
</form>
