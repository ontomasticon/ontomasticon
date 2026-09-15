<h2><?php print t("Terms"); ?></h2>

<?php
if (!userAllow("edit-terms")) {
  print t("You do not have permission to administer this site");
} else {
  if(isset($_POST['submit'])){
    addTerm();
  }
  ?>
  <h3><?php print t("Add term"); ?></h3>
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
    <label for="language"><?php print t("Language"); ?></label><br/>
    <input type="text" id="language" name="language"
           placeholder="">
           <br/><br/>
    <label for="opaque"><?php print t("Opaque"); ?></label><br/>
    <input type="checkbox" id="opaque" name="opaque" value="opaque">
       <br/><br/>
    <label for="type-concept"><?php print t("Type"); ?></label><br/>
    <small><?php print t("A concept is a term or a value, such as a type of call. A property is a characteristic that is measured or recorded, such as pulse duration. A class is a kind of thing, such as a syllable."); ?></small><br/>
    <?php foreach (termTypeLabels() as $value => $label) { ?>
      <input type="radio" id="type-<?php print $value; ?>" name="type" value="<?php print $value; ?>" <?php print val2check("concept", $value); ?>>
      <label for="type-<?php print $value; ?>"><?php print t($label); ?></label><br/>
    <?php } ?>
    <br/>
    <label for="values"><?php print t("Values"); ?></label><br/>
    <small><?php print t("For a property, where its values come from. Other types of term don't have values."); ?></small><br/>
    <select id="values" name="values">
      <option value=""><?php print t("Not stated"); ?></option>
      <optgroup label="<?php print h(t("Controlled vocabularies")); ?>">
      <?php foreach ($GLOBALS["ontomasticon"]["CVs"] as $CV) { ?>
        <option value="cv:<?php print h($CV["shortname"]); ?>"><?php print h($CV["name"]); ?></option>
      <?php } ?>
      </optgroup>
      <optgroup label="<?php print h(t("Datatypes")); ?>">
      <?php foreach (termDatatypes() as $value => $datatype) { ?>
        <option value="datatype:<?php print $value; ?>"><?php print h(t($datatype["label"])); ?></option>
      <?php } ?>
      </optgroup>
    </select><br/><br/>
    <label for="nocv"><?php print t("Controlled vocabulary"); ?></label><br/>
    <input type="radio" id="nocv" name="cv" value="none">
    <label for="nocv"><?php print t("None"); ?></label><br/>
    <?php foreach ($GLOBALS["ontomasticon"]["CVs"] as $CV) { ?>
      <input type="radio" id="<?php print h($CV["shortname"]); ?>" name="cv"
        value="<?php print h($CV["shortname"]); ?>">
      <label for="<?php print h($CV["shortname"]); ?>"><?php print h($CV["name"]); ?></label><br/>
    <?php }
    print "<br/>"; ?>
    <label for="none"><?php print t("Invalidity"); ?></label><br/>
    <input type="radio" id="none" name="invalid" value="none">
    <label for="none"><?php print t("None"); ?></label><br>
    <input type="radio" id="synonym" name="invalid" value="Synonym">
    <label for="synonym"><?php print t("Synonym"); ?></label><br/><br/>
    <label for="parent"><?php print t("Parent"); ?></label><br/>
    <input type="text" id="parent" name="parent"
           placeholder="">
           <br/><br/>
    <label for="broader"><?php print t("Broader term"); ?></label><br/>
    <input type="text" id="broader" name="broader"
           placeholder="">
           <br/><br/>
    <label for="reference"><?php print t("References"); ?></label><br/>
    <small><?php print t("One reference per line. In the definition, [1] is the first reference, [2] the second, and so on."); ?></small><br/>
    <textarea id="reference" name="reference" rows="3" cols="50"></textarea>
           <br/><br/>
    <button type="submit" name="submit"><?php print t("Save"); ?></button>
  </form>
<?php
}
end:
