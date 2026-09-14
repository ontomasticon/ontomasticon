<h2><?php print t("Configure site"); ?></h2>

<?php
if (!userAllow("administer")) {
  print t("You do not have permission to administer this site");
} else {
  if(isset($_POST['submit'])){
    saveConfig();
  }
  ?>
  <form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post">
    <label for="site_name"><?php print t("Site name"); ?></label><br/>
    <input type="text" id="site_name" name="site_name"
           value="<?php print h($GLOBALS["ontomasticon"]["config"]["site_name"]);?>"
           placeholder="">
           <br/><br/>
    <label for="author"><?php print t("Author"); ?></label><br/>
    <input type="text" id="author" name="author"
           value="<?php print h($GLOBALS["ontomasticon"]["config"]["author"]);?>"
           placeholder=""><br/><br/>
    <label for="default_lang"><?php print t("Default language"); ?></label><br/>
    <input type="text" id="default_lang" name="default_lang"
           value="<?php print h($GLOBALS["ontomasticon"]["config"]["default_lang"]);?>"
           placeholder=""><br/><br/>
    <label for="base_url"><?php print t("Base URL"); ?></label><br/>
    <input type="text" id="base_url" name="base_url"
           value="<?php print h($GLOBALS["ontomasticon"]["config"]["base_url"]);?>"
           placeholder=""><br/><br/>
    <label for="description"><?php print t("Description"); ?></label><br/>
    <textarea id="description" name="description" rows="4" cols="50"><?php print h($GLOBALS["ontomasticon"]["config"]["description"]);?></textarea><br/><br/>
    <button type="submit" name="submit"><?php print t("Save"); ?></button>
  </form>
<?php
}
