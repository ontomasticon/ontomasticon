<h2><?php print t("Terms"); ?></h2>

<?php
if (!userAllow("administer")) {
  print t("You do not have permission to administer this site");
} else {
  $sn = getTerm($GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"]);
  if(isset($_POST['submit'])){
    editTerm();
    $sn = getTerm($GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"]);
  }
  if (isset($_POST['delete'])){
    template("admin-term-delete.php");
    goto end;
  }
  if (isset($_POST['delete_term'])){
    deleteTerm();
    print "<p>".t("Deleted.")."</p>";
    goto end;
  }
  ?>

  <h3><?php print t("Edit")." <i>".h($sn["shortname"]); ?></i></h3>
  <form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post">
    <label for="name"><?php print t("Name"); ?></label><br/>
    <input type="text" id="name" name="name"
           value="<?php print h($sn["name"]); ?>"
           placeholder="">
           <br/><br/>
    <label for="description"><?php print t("Description"); ?></label><br/>
    <textarea id="description" name="description" rows="4" cols="50"><?php print h($sn["description"]);?></textarea><br/>
    <label for="language"><?php print t("Language"); ?></label><br/>
    <input type="text" id="language" name="language"
           value="<?php print htmlspecialchars($sn["language"]); ?>"
           placeholder="">
           <br/><br/>
    <label for="opaque"><?php print t("Opaque"); ?></label><br/>
    <input type="checkbox" id="opaque" name="opaque" value="opaque" <?php print bool2check($sn["opaque"]); ?>>
       <br/><br/>
    <label for="nocv"><?php print t("Controlled vocabulary"); ?></label><br/>
    <input type="radio" id="nocv" name="cv" value="none" <?php print val2check($sn["cv"], ""); ?>>
    <label for="nocv"><?php print t("None"); ?></label><br/>
    <?php foreach ($GLOBALS["ontomasticon"]["CVs"] as $CV) { ?>
      <input type="radio" id="<?php print h($CV["shortname"]); ?>" name="cv"
        value="<?php print h($CV["shortname"]); ?>" <?php print val2check($sn["cv"], $CV["shortname"]); ?>>
      <label for="<?php print h($CV["shortname"]); ?>"><?php print h($CV["name"]); ?></label><br/>
    <?php }
    print "<br/"; ?>
    <label for="none"><?php print t("Invalidity"); ?></label><br/>
    <input type="radio" id="none" name="invalid" value="none" <?php print val2check($sn["cv"], ""); ?>>
    <label for="none"><?php print t("None"); ?></label><br>
    <input type="radio" id="synonym" name="invalid" value="Synonym" <?php print val2check($sn["invalid_reason"], "Synonym"); ?>>
    <label for="synonym"><?php print t("Synonym"); ?></label><br/><br/>
    <label for="parent"><?php print t("Parent"); ?></label><br/>
    <input type="text" id="parent" name="parent"
           value="<?php ($sn["parent"]=="") ? "" : print htmlspecialchars($sn["parent"]); ?>"
           placeholder="">
           <br/><br/>
    <label for="broader"><?php print t("Broader term"); ?></label><br/>
    <input type="text" id="broader" name="broader"
           value="<?php print ($sn["broader"]=="") ? "" : htmlspecialchars($sn["broader"]); ?>"
           placeholder="">
           <br/><br/>
    <label for="reference"><?php print t("Reference"); ?></label><br/>
    <input type="text" id="reference" name="reference"
           value="<?php print ($sn["reference"]=="") ? "" : htmlspecialchars($sn["reference"]); ?>"
           placeholder="">
           <br/><br/>
    <button type="submit" name="submit"><?php print t("Save"); ?></button>
    <button type="submit" name="delete"><?php print t("Delete"); ?></button>
  </form>
<?php
}
end:
