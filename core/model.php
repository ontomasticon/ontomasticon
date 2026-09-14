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

  //The term's URI: the site address followed by its shortname, or its id if the term is opaque.
  //Terms in a vocabulary are fragments of the vocabulary's page.
  public function uri() {
    $name = ($this->opaque == 0) ? $this->shortname : $this->id;
    if ($this->cv == null) {
      return(siteURL().$name);
    }
    return($this->vocabulary()->uri()."#".$name);
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

  public function __construct($shortname = null) {
    $this->shortname = $shortname;
  }

  public function uri() {
    return(siteURL().(($this->shortname === null) ? "" : "cv/".$this->shortname));
  }
}
