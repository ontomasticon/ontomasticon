<h2><?php print t("User login"); ?></h2>

<?php
//Login and logout are handled in index.php, before any output is sent
if (isset($GLOBALS["ontomasticon"]["login_message"])) {
  print t($GLOBALS["ontomasticon"]["login_message"]);
}

if (isset($_SESSION["user"])) {
  print t("Logged in as")." ".h($_SESSION["user"]);
  ?>
  <form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post"><?php print csrfField(); ?>
    <button type="submit" name="logout"><?php print t("Logout"); ?></button>
  </form>
 <?php
} else {
?>
  <form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post"><?php print csrfField(); ?>
    <input type="text" name="email" value="" placeholder="<?php print t("Email"); ?>">
    <input type="password" name="password" value="" placeholder="<?php print t("Password"); ?>">
    <button type="submit" name="submit"><?php print t("Login"); ?></button>
  </form>
<?php
}
