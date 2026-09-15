<h2><?php print t("Terms"); ?></h2>

<?php
if (!userAllow("edit-terms")) {
  print t("You do not have permission to administer this site");
} else {
  $sn = getTerm($GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"]);
  if ($sn == null) {
    print t("No matching term found");
    goto end;
  }
  if(isset($_POST['submit'])){
    editTerm();
    $sn = getTerm($GLOBALS["ontomasticon"]["pageInfo"]["active_subsubpage"]);
  }
  if (isset($_POST['delete'])){
    template("admin-term-delete.php");
    goto end;
  }
  if (isset($_POST['delete_term'])){
    if (deleteTerm()) {
      printStatus(t("Deleted."));
    }
    goto end;
  }
  ?>

  <h3><?php print t("Edit")." <i>".h($sn["shortname"]); ?></i></h3>
  <form action="<?php print formAction(); ?>" method="post"><?php print csrfField(); ?>
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
    <label for="type-concept"><?php print t("Type"); ?></label><br/>
    <small><?php print t("A concept is a term or a value, such as a type of call. A property is a characteristic that is measured or recorded, such as pulse duration. A class is a kind of thing, such as a syllable."); ?></small><br/>
    <?php foreach (termTypeLabels() as $value => $label) { ?>
      <input type="radio" id="type-<?php print $value; ?>" name="type" value="<?php print $value; ?>" <?php print val2check(termType(isset($sn["type"]) ? $sn["type"] : null), $value); ?>>
      <label for="type-<?php print $value; ?>"><?php print t($label); ?></label><br/>
    <?php } ?>
    <br/>
    <?php $valuesChoice = termValuesChoice($sn); ?>
    <label for="values"><?php print t("Values"); ?></label><br/>
    <small><?php print t("For a property, where its values come from. Other types of term don't have values."); ?></small><br/>
    <select id="values" name="values">
      <option value=""><?php print t("Not stated"); ?></option>
      <optgroup label="<?php print h(t("Controlled vocabularies")); ?>">
      <?php foreach ($GLOBALS["ontomasticon"]["CVs"] as $CV) { ?>
        <option value="cv:<?php print h($CV["shortname"]); ?>" <?php print ($valuesChoice == "cv:".$CV["shortname"]) ? "selected" : ""; ?>><?php print h($CV["name"]); ?></option>
      <?php } ?>
      </optgroup>
      <optgroup label="<?php print h(t("Datatypes")); ?>">
      <?php foreach (termDatatypes() as $value => $datatype) { ?>
        <option value="datatype:<?php print $value; ?>" <?php print ($valuesChoice == "datatype:".$value) ? "selected" : ""; ?>><?php print h(t($datatype["label"])); ?></option>
      <?php } ?>
      </optgroup>
    </select><br/><br/>
    <label for="nocv"><?php print t("Controlled vocabulary"); ?></label><br/>
    <input type="radio" id="nocv" name="cv" value="none" <?php print val2check($sn["cv"], ""); ?>>
    <label for="nocv"><?php print t("None"); ?></label><br/>
    <?php foreach ($GLOBALS["ontomasticon"]["CVs"] as $CV) { ?>
      <input type="radio" id="<?php print h($CV["shortname"]); ?>" name="cv"
        value="<?php print h($CV["shortname"]); ?>" <?php print val2check($sn["cv"], $CV["shortname"]); ?>>
      <label for="<?php print h($CV["shortname"]); ?>"><?php print h($CV["name"]); ?></label><br/>
    <?php }
    print "<br/>"; ?>
    <label for="none"><?php print t("Invalidity"); ?></label><br/>
    <input type="radio" id="none" name="invalid" value="none" <?php print val2check($sn["invalid_reason"], ""); ?>>
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
