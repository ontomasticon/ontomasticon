<h2><?php print t("Controlled vocabularies"); ?></h2>

<?php
if (!userAllow("create-cv")) {
  print t("You do not have permission to administer this site");
} else {
  if(isset($_POST['submit'])){
    addCV();
  }
  ?>
  <h3><?php print t("Add controlled vocabulary"); ?></h3>
  <form action="<?php print formAction(); ?>" method="post"><?php print csrfField(); ?>
    <label for="shortname"><?php print t("Shortname"); ?></label><br/>
    <input type="text" id="shortname" name="shortname"
           placeholder="">
           <br/><br/>
    <label for="name"><?php print t("Name"); ?></label><br/>
    <input type="text" id="name" name="name"
           placeholder="">
           <br/><br/>
    <label for="description"><?php print t("Description"); ?></label><br/>
    <textarea id="description" name="description" rows="4" cols="50"></textarea><br/>
    <label for="reference"><?php print t("Reference"); ?></label><br/>
    <input type="text" id="reference" name="reference"
           placeholder="">
           <br/><br/>
    <button type="submit" name="submit"><?php print t("Save"); ?></button>
  </form>
<?php
}
