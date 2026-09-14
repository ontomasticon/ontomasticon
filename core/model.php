<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// Terms and vocabularies as objects, for output formats that follow the links between terms

class Term {
  public $id;
  public $shortname;
  public $name;
  public $description;
  public $language;
  public $opaque;
  public $cv;
  public $parentID;
  public $broaderID;
  public $invalidReason;
  public $reference;

  //Related terms that have been loaded, by relation name
  private $related = array();

  //Make a term from a row of the terms table. Columns missing from the row are left as NULL.
  public static function fromRow($row) {
    $columns = array(
      "id" => "id", "shortname" => "shortname", "name" => "name", "description" => "description",
      "language" => "language", "opaque" => "opaque", "cv" => "cv", "parent" => "parentID",
      "broader" => "broaderID", "invalid_reason" => "invalidReason", "reference" => "reference"
    );
    $term = new Term();
    foreach ($columns as $column => $property) {
      if (isset($row[$column])) {
        $term->$property = $row[$column];
      }
    }
    return($term);
  }

  //The term with a shortname, or NULL if there is no match
  public static function find($shortname) {
    return(Term::loadOne("`shortname` = ?", array($shortname)));
  }

  //The term with an id, or NULL if there is no match
  public static function findByID($id) {
    return(Term::loadOne("`id` = ?", array($id)));
  }

  //The term a URI identifies, or NULL if it isn't exactly the URI of a term. The URI ends
  //with the term's shortname, or its id if the term is opaque.
  public static function findByURI($uri) {
    $name = preg_replace('#^.*[/\#]#', '', $uri);
    if ($name === "") {
      return(null);
    }
    foreach (array(Term::find($name), ctype_digit($name) ? Term::findByID($name) : null) as $term) {
      if ($term != null && $term->uri() === $uri) {
        return($term);
      }
    }
    return(null);
  }

  //The terms in a vocabulary, including deprecated ones. A NULL shortname gives the site's
  //terms that aren't in a vocabulary.
  public static function inVocabulary($shortname) {
    if ($shortname === null) {
      return(Term::loadAll("(`cv` IS NULL OR `cv` = '')", array()));
    }
    return(Term::loadAll("`cv` = ?", array($shortname)));
  }

  //Load the related terms of a list of terms with three queries in all, rather than several for each term
  public static function loadRelations($terms) {
    $ids = array();
    $linkedIDs = array();
    foreach ($terms as $term) {
      $ids[] = $term->id;
      foreach (array($term->broaderID, $term->parentID) as $id) {
        if ($id != null) {
          $linkedIDs[] = $id;
        }
      }
    }
    $byID = Term::groupBy("id", Term::loadIn("`id`", $linkedIDs));
    $narrower = Term::groupBy("broaderID", Term::loadIn("`broader`", $ids, " AND `invalid_reason` IS NULL"));
    $children = Term::groupBy("parentID", Term::loadIn("`parent`", $ids));
    foreach ($terms as $term) {
      $term->setRelated("broader", ($term->broaderID != null && isset($byID[$term->broaderID])) ? $byID[$term->broaderID][0] : null);
      $term->setRelated("parent", ($term->parentID != null && isset($byID[$term->parentID])) ? $byID[$term->parentID][0] : null);
      $term->setRelated("narrower", isset($narrower[$term->id]) ? $narrower[$term->id] : array());
      $term->setRelated("children", isset($children[$term->id]) ? $children[$term->id] : array());
    }
  }

  //Terms matching a condition on the terms table, with ? placeholders filled from $params
  private static function loadAll($where, $params) {
    $terms = array();
    $result = dbQuery("SELECT * FROM `terms` WHERE ".$where." ORDER BY `shortname`;", $params);
    if ($result) {
      foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
        $terms[] = Term::fromRow($row);
      }
      $result->close();
    }
    return($terms);
  }

  private static function loadOne($where, $params) {
    $terms = Term::loadAll($where, $params);
    return((count($terms) > 0) ? $terms[0] : null);
  }

  //Terms whose $column is one of $values, and that meet any further $condition
  private static function loadIn($column, $values, $condition = "") {
    $values = array_values(array_unique($values));
    if (count($values) == 0) {
      return(array());
    }
    $placeholders = implode(", ", array_fill(0, count($values), "?"));
    return(Term::loadAll($column." IN (".$placeholders.")".$condition, $values));
  }

  //Terms grouped into lists by the value of one of their properties
  private static function groupBy($property, $terms) {
    $grouped = array();
    foreach ($terms as $term) {
      $grouped[$term->$property][] = $term;
    }
    return($grouped);
  }

  //The term's URI: the site address followed by its shortname, or its id if the term is opaque.
  //Terms in a vocabulary are fragments of the vocabulary's page.
  public function uri() {
    if ($this->cv == null) {
      return(siteURL().$this->anchor());
    }
    return($this->vocabulary()->uri()."#".$this->anchor());
  }

  //The name that ends the term's URI, and the id of its entry on the page that lists it:
  //its shortname, or its id if the term is opaque
  public function anchor() {
    return(($this->opaque == 0) ? $this->shortname : $this->id);
  }

  public function vocabulary() {
    return(new Vocabulary(($this->cv == null) ? null : $this->cv));
  }

  public function isDeprecated() {
    return($this->invalidReason !== null && $this->invalidReason !== "");
  }

  //Synonyms are linked to the term they are a synonym of as their parent
  public function isSynonym() {
    return($this->invalidReason == "Synonym");
  }

  //The broader term, or NULL
  public function broader() {
    return($this->relation("broader"));
  }

  //Valid terms that have this one as their broader term
  public function narrower() {
    return($this->relation("narrower"));
  }

  //The parent term, or NULL
  public function parent() {
    return($this->relation("parent"));
  }

  //Terms, including synonyms, that have this one as their parent
  public function children() {
    return($this->relation("children"));
  }

  //Set a relation (broader, narrower, parent or children) instead of loading it from the database
  public function setRelated($relation, $value) {
    $this->related[$relation] = $value;
  }

  private function relation($relation) {
    if (!array_key_exists($relation, $this->related)) {
      $this->related[$relation] = $this->loadRelation($relation);
    }
    return($this->related[$relation]);
  }

  private function loadRelation($relation) {
    switch ($relation) {
      case "broader":
        return(($this->broaderID == null) ? null : Term::findByID($this->broaderID));
      case "parent":
        return(($this->parentID == null) ? null : Term::findByID($this->parentID));
      case "narrower":
        return(($this->id == null) ? array() : Term::loadAll("`broader` = ? AND `invalid_reason` IS NULL", array($this->id)));
      case "children":
        return(($this->id == null) ? array() : Term::loadAll("`parent` = ?", array($this->id)));
    }
    return(null);
  }
}

class Vocabulary {
  //The vocabulary's shortname, or NULL for the site's terms that aren't in a vocabulary
  public $shortname;
  public $name;
  public $description;
  public $reference;
  public $creator;

  public function __construct($shortname = null) {
    $this->shortname = $shortname;
  }

  //The vocabulary with a shortname, or NULL if there is no match
  public static function find($shortname) {
    $result = dbQuery("SELECT * FROM `cv` WHERE `shortname` = ?;", array($shortname));
    $row = ($result) ? $result->fetch_assoc() : null;
    if ($row == null) {
      return(null);
    }
    $vocabulary = new Vocabulary($row["shortname"]);
    $vocabulary->name = $row["name"];
    $vocabulary->description = $row["description"];
    $vocabulary->reference = $row["reference"];
    return($vocabulary);
  }

  //The site's terms that aren't in a vocabulary, described by the site's name, description and author
  public static function site() {
    $config = $GLOBALS["ontomasticon"]["config"];
    $vocabulary = new Vocabulary();
    foreach (array("name" => "site_name", "description" => "description", "creator" => "author") as $property => $key) {
      $vocabulary->$property = isset($config[$key]) ? $config[$key] : null;
    }
    return($vocabulary);
  }

  public function uri() {
    return(siteURL().(($this->shortname === null) ? "" : "cv/".$this->shortname));
  }

  //The vocabulary's terms, including deprecated ones, with their related terms loaded
  public function terms() {
    $terms = Term::inVocabulary($this->shortname);
    Term::loadRelations($terms);
    return($terms);
  }
}
