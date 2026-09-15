<div id="sub-menu">
  <?php
  $links = array();
  if (userAllow("administer")) {
    $links[] = l("Configure site", "/admin/configure");
  }
  if (userAllow("manage-users")) {
    $links[] = l("Users", "/admin/users");
  }
  if (userAllow("create-cv")) {
    $links[] = l("Add controlled vocabulary","/admin/cv/add");
  }
  if (userAllow("edit-terms")) {
    $links[] = l("Add term", "/admin/term/add");
    $links[] = l("Linked data readiness", "/admin/readiness");
  }
  print implode(" | ", $links);
  ?>
</div>

<?php
switch ($GLOBALS["ontomasticon"]["pageInfo"]["active_page"]) {
  case "cv":
    switch ($GLOBALS["ontomasticon"]["pageInfo"]["active_subpage"]) {
      case "add":
        template("admin-cv-add.php");
        break;
      case "edit":
        template("admin-cv-edit.php");
        break;
    }
    break;
  case "term":
    switch ($GLOBALS["ontomasticon"]["pageInfo"]["active_subpage"]) {
      case "add":
        template("admin-term-add.php");
        break;
      case "edit":
        template("admin-term-edit.php");
        break;
    }
    break;
  case "users":
    if ($GLOBALS["ontomasticon"]["pageInfo"]["active_subpage"] == "add") {
      template("admin-user-add.php");
    } elseif ($GLOBALS["ontomasticon"]["pageInfo"]["active_subpage"] == "edit") {
      template("admin-user-edit.php");
    } else {
      template("admin-users.php");
    }
    break;
  case "update":
    template("update.php");
    break;
  case "readiness":
    template("admin-readiness.php");
    break;
  case "config":
  default:
     template("admin-config.php");
     break;
}
