<h2><?php print t("Configure site"); ?></h2>

<?php
if (!userAllow("administer")) {
  print t("You do not have permission to administer this site");
} else {
  if(isset($_POST['submit'])){
    saveConfig();
  }
  ?>
  <form action="<?php print formAction(); ?>" method="post"><?php print csrfField(); ?>
    <label for="site_name"><?php print t("Site name"); ?></label><br/>
    <input type="text" id="site_name" name="site_name"
           value="<?php print h($GLOBALS["ontomasticon"]["config"]["site_name"]);?>"
           placeholder="">
           <br/><br/>
    <label for="author"><?php print t("Author"); ?></label><br/>
    <input type="text" id="author" name="author"
           value="<?php print h($GLOBALS["ontomasticon"]["config"]["author"]);?>"
           placeholder=""><br/><br/>
    <label for="publisher"><?php print t("Publisher"); ?></label><br/>
    <small><?php print t("The organisation that publishes the vocabularies, if there is one"); ?></small><br/>
    <input type="text" id="publisher" name="publisher"
           value="<?php print h(configValue("publisher"));?>"
           placeholder=""><br/><br/>
    <label for="default_lang"><?php print t("Default language"); ?></label><br/>
    <input type="text" id="default_lang" name="default_lang"
           value="<?php print h($GLOBALS["ontomasticon"]["config"]["default_lang"]);?>"
           placeholder=""><br/><br/>
    <label for="languages"><?php print t("Other languages"); ?></label><br/>
    <small><?php print t("Language codes the site is also offered in, separated by spaces, for example fr pt-BR. Visitors see the site in the one their browser prefers, and can switch between them."); ?></small><br/>
    <input type="text" id="languages" name="languages"
           value="<?php print h(configValue("languages"));?>"
           placeholder=""><br/><br/>
    <label for="base_url"><?php print t("Base URL"); ?></label><br/>
    <small><?php print t("For example glossary.example.org/ or http://glossary.example.org/ (https:// is assumed if left out)"); ?></small><br/>
    <input type="text" id="base_url" name="base_url"
           value="<?php print h($GLOBALS["ontomasticon"]["config"]["base_url"]);?>"
           placeholder=""><br/><br/>
    <label for="description"><?php print t("Description"); ?></label><br/>
    <textarea id="description" name="description" rows="4" cols="50"><?php print h($GLOBALS["ontomasticon"]["config"]["description"]);?></textarea><br/><br/>
    <label for="glossary_display"><?php print t("Glossary display"); ?></label><br/>
    <small><?php print t("List terms in alphabetical order, under a heading for each letter, with links from A to Z at the top and bottom of the list"); ?></small><br/>
    <input type="checkbox" id="glossary_display" name="glossary_display" value="1" <?php print bool2check(configValue("glossary_display")); ?>>
    <br/><br/>
    <label for="license"><?php print t("License"); ?></label><br/>
    <small><?php print t("The web address of the license the vocabularies are published under, for example https://creativecommons.org/licenses/by/4.0/"); ?></small><br/>
    <input type="text" id="license" name="license"
           value="<?php print h(configValue("license"));?>"
           placeholder=""><br/><br/>
    <label for="prefix"><?php print t("Namespace prefix"); ?></label><br/>
    <small><?php print t("A short prefix for the terms that aren't in a controlled vocabulary, used in RDF, for example gl"); ?></small><br/>
    <input type="text" id="prefix" name="prefix"
           value="<?php print h(configValue("prefix"));?>"
           placeholder=""><br/><br/>
    <button type="submit" name="submit"><?php print t("Save"); ?></button>
  </form>
<?php
}
