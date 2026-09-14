<h2><?php print t("Users"); ?></h2>

<?php
if (!userAllow("manage-users")) {
  print t("You do not have permission to administer this site");
} else {
  if (isset($_POST['set_role'])) {
    print "<p>".t(setUserRole())."</p>";
  }
  $me = loadUser($_SESSION["user"]);
  ?>
  <?php if (userAllow("create-user")) { ?>
  <p><?php print l("Add user", "/admin/users/add"); ?></p>
  <?php } ?>
  <table>
    <tr><th><?php print t("Name"); ?></th><th><?php print t("Email"); ?></th><th><?php print t("Role"); ?></th></tr>
    <?php foreach (getUsers() as $user) { ?>
    <tr>
      <td><?php print h($user["first_name"]." ".$user["last_name"]); ?></td>
      <td><?php print h($user["email"]); ?></td>
      <td>
      <?php if ($user["id"] == 1) {
        print t("Admin (installation account)");
      } elseif ($user["id"] == $me["id"]) {
        print h(t(roleName($user["role"])));
      } else { ?>
        <form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post"><?php print csrfField(); ?>
          <input type="hidden" name="user_id" value="<?php print h($user["id"]); ?>">
          <?php print roleSelect($user["role"]); ?>
          <button type="submit" name="set_role"><?php print t("Save"); ?></button>
        </form>
      <?php } ?>
      </td>
    </tr>
    <?php } ?>
  </table>
<?php
}
