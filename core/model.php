<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// Terms and vocabularies as objects, for the site's pages and the other formats that follow the links between terms

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
  //When the term was added and last changed, as database DATETIMEs in UTC, or NULL if not known
  public $created;
  public $modified;
  //"concept", "property" or "class" (see termTypes())
  public $type = "concept";
  //Where a property's values come from: a vocabulary's shortname, or one of termDatatypes(), or NULL
  public $rangeCV;
  public $datatype;
  //An acronym the term is also known by, or NULL
  public $acronym;

  //Related terms that have been loaded, by relation name
  private $related = array();

  //Make a term from a row of the terms table. Columns missing from the row are left as NULL,
  //except the type, which is a concept unless the row gives another type.
  public static function fromRow($row) {
    $columns = array(
      "id" => "id", "shortname" => "shortname", "name" => "name", "acronym" => "acronym", "description" => "description",
      "language" => "language", "opaque" => "opaque", "cv" => "cv", "parent" => "parentID",
      "broader" => "broaderID", "invalid_reason" => "invalidReason", "reference" => "reference",
      "created" => "created", "modified" => "modified", "range_cv" => "rangeCV", "datatype" => "datatype"
    );
    $term = new Term();
    foreach ($columns as $column => $property) {
      if (isset($row[$column])) {
        $term->$property = $row[$column];
      }
    }
    $term->type = termType(isset($row["type"]) ? $row["type"] : null);
    return($term);
  }

  //The terms in the rows of a query on the terms table, such as a search, or none if the query failed
  public static function fromResult($result) {
    $terms = array();
    if ($result) {
      foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
        $terms[] = Term::fromRow($row);
      }
      $result->close();
    }
    return($terms);
  }

  //The term with a shortname, or NULL if there is no match
  public static function find($shortname) {
    return(Term::loadOne("`shortname` = ?", array($shortname)));
  }

  //The term with an id, or NULL if there is no match
  public static function findByID($id) {
    return(Term::loadOne("`id` = ?", array($id)));
  }

  //The terms with the ids in a list, in order of short name
  public static function findByIDs($ids) {
    return(Term::loadIn("`id`", $ids));
  }

  //Every term, in and outside vocabularies, including deprecated ones
  public static function all() {
    return(Term::loadAll("1 = 1", array()));
  }

  //The term a URI identifies, or NULL if it isn't exactly the URI of a term. The URI ends
  //with the term's shortname, or its id if the term is opaque.
  public static function findByURI($uri) {
    $name = preg_replace('#^.*[/\#]#', '', $uri);
    if ($name === "") {
      return(null);
    }
    //preg_match rather than ctype_digit, as the ctype extension isn't always available
    $byID = (preg_match('/^[0-9]+$/D', $name) === 1) ? Term::findByID($name) : null;
    foreach (array(Term::find($name), $byID) as $term) {
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

  //Load the related terms of a list of terms with four queries in all, rather than several for each term
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
    $relatedIDs = Term::relatedIDs($ids);
    foreach ($relatedIDs as $termRelatedIDs) {
      $linkedIDs = array_merge($linkedIDs, $termRelatedIDs);
    }
    $byID = Term::groupBy("id", Term::loadIn("`id`", $linkedIDs));
    $narrower = Term::groupBy("broaderID", Term::loadIn("`broader`", $ids, " AND `invalid_reason` IS NULL"));
    $children = Term::groupBy("parentID", Term::loadIn("`parent`", $ids));
    foreach ($terms as $term) {
      $term->setRelated("broader", ($term->broaderID != null && isset($byID[$term->broaderID])) ? $byID[$term->broaderID][0] : null);
      $term->setRelated("parent", ($term->parentID != null && isset($byID[$term->parentID])) ? $byID[$term->parentID][0] : null);
      $term->setRelated("narrower", isset($narrower[$term->id]) ? $narrower[$term->id] : array());
      $term->setRelated("children", isset($children[$term->id]) ? $children[$term->id] : array());
      $related = array();
      foreach (isset($relatedIDs[$term->id]) ? $relatedIDs[$term->id] : array() as $id) {
        if (isset($byID[$id])) {
          $related[] = $byID[$id][0];
        }
      }
      $term->setRelated("related", $related);
    }
  }

  //The ids of the related terms (see saveRelatedTerms()) of each of the terms with the ids in $ids, as a term's id => the
  //ids of its related terms, in order of their short names
  private static function relatedIDs($ids) {
    $ids = array_values(array_unique($ids));
    $related = array();
    if (count($ids) == 0) {
      return($related);
    }
    $sql  = "SELECT `r`.`term`, `r`.`related` FROM ".table("related_terms")." AS `r` JOIN ".table("terms")." AS `t` ON `t`.`id` = `r`.`related` ";
    $sql .= "WHERE `r`.`term` IN (".implode(", ", array_fill(0, count($ids), "?")).") ORDER BY `t`.`shortname`;";
    $result = dbQuery($sql, $ids);
    if ($result) {
      foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
        $related[$row["term"]][] = $row["related"];
      }
      $result->close();
    }
    return($related);
  }

  //Terms matching a condition on the terms table, with ? placeholders filled from $params
  private static function loadAll($where, $params) {
    return(Term::fromResult(dbQuery("SELECT * FROM ".table("terms")." WHERE ".$where." ORDER BY `shortname`;", $params)));
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

  //The URI of one of the term's words on a glossary (see termLexicalEntries()), such as "entry" for its name: the term's
  //URI with the word as its fragment, or, when the URI already has a fragment, added to it after a colon, which short
  //names can't contain
  public function entryURI($word) {
    $uri = $this->uri();
    return($uri.((strpos($uri, "#") === FALSE) ? "#" : ":").$word);
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

  //The term's related terms (see saveRelatedTerms()), in order of short name
  public function related() {
    return($this->relation("related"));
  }

  //Set a relation (broader, narrower, parent, children or related) instead of loading it from the database
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
      case "related":
        return(($this->id == null) ? array() : Term::loadAll("`id` IN (SELECT `related` FROM ".table("related_terms")." WHERE `term` = ?)", array($this->id)));
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
  //The prefix for the vocabulary's terms in RDF, or NULL if there isn't one
  public $prefix;
  //The site's publisher and license, which apply to all of its vocabularies
  public $publisher;
  public $license;

  public function __construct($shortname = null) {
    $this->shortname = $shortname;
    $this->publisher = configValue("publisher");
    $this->license = configValue("license");
  }

  //The vocabulary with a shortname, or NULL if there is no match
  public static function find($shortname) {
    $result = dbQuery("SELECT * FROM ".table("cv")." WHERE `shortname` = ?;", array($shortname));
    $row = ($result) ? $result->fetch_assoc() : null;
    if ($row == null) {
      return(null);
    }
    $vocabulary = new Vocabulary($row["shortname"]);
    $vocabulary->name = $row["name"];
    $vocabulary->description = $row["description"];
    $vocabulary->reference = $row["reference"];
    //The prefix column is added by the 0.4 database update
    $vocabulary->prefix = isset($row["prefix"]) ? $row["prefix"] : null;
    return($vocabulary);
  }

  //The site's terms that aren't in a vocabulary, described by the site's name, description, author and prefix
  public static function site() {
    $config = $GLOBALS["ontomasticon"]["config"];
    $vocabulary = new Vocabulary();
    foreach (array("name" => "site_name", "description" => "description", "creator" => "author", "prefix" => "prefix") as $property => $key) {
      $vocabulary->$property = isset($config[$key]) ? $config[$key] : null;
    }
    return($vocabulary);
  }

  public function uri() {
    return(siteURL().(($this->shortname === null) ? "" : "cv/".$this->shortname));
  }

  //The URI the vocabulary's term URIs start with
  public function namespaceURI() {
    return(($this->shortname === null) ? siteURL() : $this->uri()."#");
  }

  //The vocabulary's terms, including deprecated ones, with their related terms loaded
  public function terms() {
    $terms = Term::inVocabulary($this->shortname);
    Term::loadRelations($terms);
    return($terms);
  }
}
