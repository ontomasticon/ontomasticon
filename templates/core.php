<?php session_start(); ?>
<!DOCTYPE html>
<html lang="<?php print h(t($GLOBALS["ontomasticon"]["config"]["default_lang"])); ?>">
<head>
<meta charset = "UTF-8">
<title><?php print h(tu("site_name")); ?></title>
<meta name="Generator" content="Ontomasticon (https://ontomasticon.github.io/)"/>
<meta name="author" content="<?php print h($GLOBALS["ontomasticon"]["config"]["author"]); ?>">
<meta name="description" content="<?php print h(strip_tags(tu("description"))); ?>">
<link rel="stylesheet" type="text/css" href="/css/default.css" />
<link rel="icon" type="image/png" href="/images/ontomasticon.png">
<?php
if (file_exists("settings/user.css")) {
  ?>
  <link rel="stylesheet" type="text/css" href="<?php print h($GLOBALS["ontomasticon"]["config"]["base_url"]); ?>settings/user.css" />
  <?php
}
?>

<body>
<div id="header">
  <img src="/images/ontomasticon.svg" id="logo" />
  <h1 id="site_title"><?php print l(tu("site_name"), "/"); ?></h1>
</div>

<?php
if (userAllow("administer")) {
  $status = adminSanity();
  if ($status != NULL) {
    print '<div id="admin-warnings">';
    foreach($status as $key => $value) {
      print '<b>'.$key.'</b><p>'.$value.'</p><br>';
    }
    print '</div>';
  }
}


switch($GLOBALS["ontomasticon"]["pageInfo"]["page_type"]) {
  case "cv":
    template("cv.php");
    break;
  case "home":
    template("home.php");
    break;
  case "user":
    template("user.php");
    break;
  case "admin":
    template("admin.php");
    break;
  case "api":
    template("api-home.php");
    break;
}


?>

<div id="citation">
<p>To cite this website:</p>
<?php printCitation(); ?>
</div>

<div id="footer">
<?php printFooter(); ?>
</div>

<div id="menubar">
<?php print adminLink(); ?><br/>
<?php print userLink(); ?><br/>
<?php print logInOut(); ?>
</body>

</html>
