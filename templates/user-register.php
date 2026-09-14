<h1><?php print t("Create user"); ?></h1>

<?php
if (!userAllow("create-user")) {
  print t("You do not have permission to create users");
} else {
  global $db;
  if(isset($_POST['submit'])){
    createUser();
  }
  ?>
  <form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post">
    <input type="text" name="first_name" value="" placeholder="<?php print t("First name"); ?>">
    <input type="text" name="surname" value="" placeholder="<?php print t("Surname"); ?>">
    <input type="text" name="email" value="" placeholder="<?php print t("Email"); ?>">
    <input type="password" name="password" value="" placeholder="<?php print t("Password"); ?>">
    <button type="submit" name="submit"><?php print t("Create user"); ?></button>
  </form>
  <?php
}
