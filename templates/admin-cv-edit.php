<h2><?php print t("Controlled vocabularies"); ?></h2>

<?php
if (!userAllow("edit-cvs")) {
  print t("You do not have permission to administer this site");
} else {
  $CV = $GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"];
  if (!isset($GLOBALS["ontomasticon"]["CVs"][$CV])) {
    print t("No matching controlled vocabulary found for")." ".h($CV);
    goto end;
  }
  if(isset($_POST['submit'])){
    editCV();
  }
  //Deleting a CV also deletes its terms, so it has its own permission
  if (isset($_POST['delete']) && userAllow("delete-cv")){
    template("admin-cv-delete.php");
    goto end;
  }
  if (isset($_POST['delete_cv']) && userAllow("delete-cv")){
    if (deleteCV()) {
      printStatus(t("Deleted."));
    }
    goto end;
  }
  ?>
  <h3><?php print t("Edit")." <i>".h($GLOBALS["ontomasticon"]["CVs"][$CV]["shortname"]); ?></i></h3>
  <form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post"><?php print csrfField(); ?>
    <label for="name"><?php print t("Name"); ?></label><br/>
    <input type="text" id="name" name="name"
           value="<?php print h($GLOBALS["ontomasticon"]["CVs"][$CV]["name"]); ?>"
           placeholder="">
           <br/><br/>
    <label for="description"><?php print t("Description"); ?></label><br/>
    <textarea id="description" name="description" rows="4" cols="50"><?php print h($GLOBALS["ontomasticon"]["CVs"][$CV]["description"]);?></textarea><br/>
    <label for="reference"><?php print t("Reference"); ?></label><br/>
    <input type="text" id="reference" name="reference"
           value="<?php print htmlspecialchars($GLOBALS["ontomasticon"]["CVs"][$CV]["reference"]); ?>"
           placeholder="">
           <br/><br/>
    <button type="submit" name="submit"><?php print t("Save"); ?></button>
    <?php if (userAllow("delete-cv")) { ?>
    <button type="submit" name="delete"><?php print t("Delete"); ?></button>
    <?php } ?>
  </form>
<?php
}
end:
