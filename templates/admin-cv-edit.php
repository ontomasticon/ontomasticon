<h2><?php print t("Controlled vocabularies"); ?></h2>

<?php
if (!userAllow("administer")) {
  print t("You do not have permission to administer this site");
} else {
  $CV = $GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"];
  if(isset($_POST['submit'])){
    editCV();
  }
  if (isset($_POST['delete'])){
    template("admin-cv-delete.php");
    goto end;
  }
  if (isset($_POST['delete_cv'])){
    deleteCV();
    print "<p>".t("Deleted.")."</p>";
    goto end;
  }
  ?>
  <h3><?php print t("Edit")." <i>".h($GLOBALS["ontomasticon"]["CVs"][$CV]["shortname"]); ?></i></h3>
  <form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post">
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
    <button type="submit" name="delete"><?php print t("Delete"); ?></button>
  </form>
<?php
}
end:
