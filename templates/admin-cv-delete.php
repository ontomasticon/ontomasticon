<form action="<?php print formAction(); ?>" method="post"><?php print csrfField(); ?>
  <p><?php print t("Warning! This cannot be undone."); ?></p>
  <button type="submit" name="delete_cv"><?php print t("Delete CV and its terms"); ?></button>
</form>
