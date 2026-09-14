<div id="sub-menu">
  <?php if (isset($_SESSION["user"])) {
          print l("Settings", "/user/settings");
        } else {
          print l("Login", "/user/login");
        } ?>
</div>
<?php
switch ($GLOBALS["ontomasticon"]["pageInfo"]["active_page"]) {
  case "login":
     template("user-login.php");
     break;
  case "settings":
      template("user-settings.php");
      break;
}
