<h2><?php print t("User login"); ?></h2>

<?php

if(isset($_POST['submit'])){
  login();
}
if (isset($_POST['logout'])){
  logout();
}

if (isset($_SESSION["user"])) {
  print t("Logged in as")." ".h($_SESSION["user"]);
  ?>
  <form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post">
    <button type="submit" name="logout"><?php print t("Logout"); ?></button>
  </form>
 <?php
} else {
?>
  <form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post">
    <input type="text" name="email" value="" placeholder="<?php print t("Email"); ?>">
    <input type="password" name="password" value="" placeholder="<?php print t("Password"); ?>">
    <button type="submit" name="submit"><?php print t("Login"); ?></button>
  </form>
<?php
}
