<!DOCTYPE html>
<html lang="<?php print h(currentLanguage()); ?>">
<head>
<meta charset = "UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php print h(pageTitle()); ?></title>
<meta name="Generator" content="Ontomasticon (https://ontomasticon.github.io/)"/>
<meta name="author" content="<?php print h($GLOBALS["ontomasticon"]["config"]["author"]); ?>">
<meta name="description" content="<?php print h(pageDescription()); ?>">
<link rel="stylesheet" type="text/css" href="<?php print h(sitePath("/css/default.css")); ?>" />
<link rel="icon" type="image/png" href="<?php print h(sitePath("/images/ontomasticon.png")); ?>">
<?php
if (file_exists("settings/user.css")) {
  ?>
  <link rel="stylesheet" type="text/css" href="<?php print h(sitePath("/settings/user.css")); ?>" />
  <?php
}
if (canonicalURL() !== null) {
  ?>
  <link rel="canonical" href="<?php print h(canonicalURL()); ?>" />
  <?php
}
if (linkedDataURL() !== null && !pageNotFound()) {
  ?>
  <link rel="alternate" type="application/ld+json" href="<?php print h(linkedDataURL()); ?>" />
  <link rel="alternate" type="text/turtle" href="<?php print h(linkedDataURL("turtle")); ?>" />
  <?php
}
$structuredData = pageStructuredData();
if ($structuredData !== null) {
  print schemaOrgScript($structuredData);
}
if (searchPage()) {
  //Search results change as terms are edited, and each search would be a page of its own
  ?>
  <meta name="robots" content="noindex" />
  <?php
}
?>
<script src="<?php print h(sitePath("/js/search.js")); ?>" defer></script>
</head>

<body>
<div id="header">
  <img src="<?php print h(sitePath("/images/ontomasticon.svg")); ?>" id="logo" alt="" />
  <h1 id="site_title"><?php print l(tu("site_name"), "/"); ?></h1>
  <form id="term-search" role="search" action="<?php print h(sitePath("/")); ?>" method="get"
        data-suggestions="<?php print h(sitePath("/api/search/")); ?>" data-synonym-of="<?php print h(t("Synonym of")); ?>">
    <label for="term-search-input" class="visually-hidden"><?php print h(t("Search terms")); ?></label>
    <input type="search" id="term-search-input" name="q" value="<?php print h(searchPage() ? searchQuery() : ""); ?>"
           placeholder="<?php print h(t("Search terms")); ?>" autocomplete="off" />
    <?php
    //Keep a language chosen with ?lang=, as links do (see l())
    if (isset($_GET["lang"])) {
      print '<input type="hidden" name="lang" value="'.h(detectLanguage()).'" />';
    }
    ?>
    <button type="submit"><?php print h(t("Search")); ?></button>
  </form>
</div>

<?php
if ($GLOBALS["ontomasticon"]["csrf_failed"]) {
  print "<div class='error'><p>".t("The form could not be verified. Please reload the page and try again.")."</p></div>";
}

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
  case "term":
    template((currentPageTerm() === null) ? "not-found.php" : "term.php");
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
<?php print languageSwitcher(); ?>
</div>
</body>

</html>
