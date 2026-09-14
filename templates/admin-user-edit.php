<h2><?php print t("Edit user"); ?></h2>

<?php
if (!userAllow("manage-users")) {
  print t("You do not have permission to administer this site");
} else {
  $user = getUser($GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"]);
  if ($user == null) {
    print t("No matching user found");
    goto end;
  }
  $me = loadUser($_SESSION["user"]);
  //User 1 is the installation's admin account, and admins can't delete themselves
  $canDelete = ($user["id"] != 1 && $user["id"] != $me["id"]);

  if (isset($_POST['delete_user']) && $canDelete) {
    if (deleteUser($user["id"])) {
      printStatus(t("Deleted."));
    }
    goto end;
  }
  if (isset($_POST['delete']) && $canDelete) {
    ?>
    <form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post"><?php print csrfField(); ?>
      <p><?php print t("Warning! This cannot be undone."); ?></p>
      <button type="submit" name="delete_user"><?php print t("Delete user"); ?></button>
    </form>
    <?php
    goto end;
  }
  if (isset($_POST['submit'])) {
    updateUserDetails($user);
    $user = getUser($user["id"]);
  }
  ?>
  <form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post"><?php print csrfField(); ?>
    <label for="first_name"><?php print t("First name"); ?></label><br/>
    <input type="text" id="first_name" name="first_name" value="<?php print h($user["first_name"]); ?>"><br/><br/>
    <label for="last_name"><?php print t("Surname"); ?></label><br/>
    <input type="text" id="last_name" name="last_name" value="<?php print h($user["last_name"]); ?>"><br/><br/>
    <label for="email"><?php print t("Email"); ?></label><br/>
    <input type="text" id="email" name="email" value="<?php print h($user["email"]); ?>"><br/><br/>
    <button type="submit" name="submit"><?php print t("Save"); ?></button>
    <?php if ($canDelete) { ?>
    <button type="submit" name="delete"><?php print t("Delete"); ?></button>
    <?php } ?>
  </form>
<?php
}
end:
