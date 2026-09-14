<h1><?php print t("User settings"); ?></h1>

<?php
if (!isset($_SESSION["user"])) {
  print t("You must be logged in to change your settings");
} else {
  if(isset($_POST['submit'])){
    editUser();
  }
  if (!empty($_SESSION["must_change_password"])) {
    printError(t("You are using the default password. Change it before continuing."));
  }
  $user = loadUSer($_SESSION["user"]);
  ?>

  <form action="<?php print formAction(); ?>" method="post"><?php print csrfField(); ?>
    <label for="first_name"><?php print t("First name"); ?></label><br>
    <input type="text" name="first_name" value="<?php print h($user["first_name"]); ?>" placeholder=""><br>

    <label for="last_name"><?php print t("last name"); ?></label><br>
    <input type="text" name="last_name" value="<?php print h($user["last_name"]); ?>" placeholder=""><br>

    <label for="old_password"><?php print t("Current password"); ?></label><br>
    <input type="password" name="old_password" value="" placeholder=""><br>

    <label for="new_password1"><?php print t("New password"); ?></label><br>
    <input type="password" name="new_password1" value="" placeholder=""><br>

    <label for="new_password2"><?php print t("Repeat new password"); ?></label><br>
    <input type="password" name="new_password2" value="" placeholder=""><br>

    <br>

    <button type="submit" name="submit"><?php print t("Save settings"); ?></button>
  </form>
  <?php
}
