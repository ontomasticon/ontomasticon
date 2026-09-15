<?php

if (!userAllow("administer")) {
  print t("You do not have permission to administer this site");
} else {
  global $db;
  $version_db = (string)$GLOBALS["ontomasticon"]["config"]["version_db"];
  $updated = FALSE;
  $failed = FALSE;

  if (version_compare($version_db, "0.2", "<")) {
    $sql = "ALTER TABLE `terms` ADD COLUMN `reference` VARCHAR(500) NULL AFTER `broader`;";
    //1060: the column already exists
    if (mysqli_query($db, $sql) || $db->errno == 1060) {
      $version_db = setDBVersion("0.2");
      $updated = TRUE;
      print "<p>".t("Ontomasticon has been updated to version 0.2")."</p>";
    } else {
      $failed = TRUE;
      print "<div class='error'><p>".t("Update to version 0.2 failed").": ".h($db->error)."</p></div>";
    }
  }

  if (!$failed && version_compare($version_db, "0.3", "<")) {
    //Email addresses must be unique before the constraint can be added
    $result = dbQuery("SELECT `email` FROM `users` WHERE `email` IS NOT NULL GROUP BY `email` HAVING COUNT(*) > 1;");
    $duplicates = ($result) ? $result->fetch_all(MYSQLI_ASSOC) : array();
    if (count($duplicates) > 0) {
      $failed = TRUE;
      print "<div class='error'><p>".t("Cannot update to version 0.3 because these email addresses belong to more than one user. Change or remove the duplicate accounts in the database, then run the update again.")."</p><ul>";
      foreach ($duplicates as $duplicate) {
        print "<li>".h($duplicate["email"])."</li>";
      }
      print "</ul></div>";
    } else {
      $steps = array(
        //Each email address belongs to one user. 1061 (below): the key already exists
        "ALTER TABLE `users` ADD UNIQUE KEY `email_UNIQUE` (`email`);",
        //Failed logins, for rate limiting
        "CREATE TABLE IF NOT EXISTS `login_attempts` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `email` varchar(255) DEFAULT NULL,
          `ip` varchar(45) DEFAULT NULL,
          `attempted` int(11) NOT NULL,
          PRIMARY KEY (`id`),
          KEY `email_attempted` (`email`, `attempted`),
          KEY `ip_attempted` (`ip`, `attempted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        //Deleting terms used to leave other terms pointing at them
        "UPDATE `terms` AS `t` LEFT JOIN `terms` AS `p` ON `t`.`parent` = `p`.`id` SET `t`.`parent` = NULL WHERE `t`.`parent` IS NOT NULL AND `p`.`id` IS NULL;",
        "UPDATE `terms` AS `t` LEFT JOIN `terms` AS `b` ON `t`.`broader` = `b`.`id` SET `t`.`broader` = NULL WHERE `t`.`broader` IS NOT NULL AND `b`.`id` IS NULL;"
      );
      foreach ($steps as $sql) {
        if (!mysqli_query($db, $sql) && $db->errno != 1061) {
          $failed = TRUE;
          print "<div class='error'><p>".t("Update to version 0.3 failed").": ".h($db->error)."</p></div>";
          break;
        }
      }
      if (!$failed) {
        $version_db = setDBVersion("0.3");
        $updated = TRUE;
        print "<p>".t("Ontomasticon has been updated to version 0.3")."</p>";
      }
    }
  }

  if (!$failed && version_compare($version_db, "0.4", "<")) {
    $steps = array(
      //When each term was added and last changed. These aren't known for terms that already exist, so they are left empty.
      "ALTER TABLE `terms` ADD COLUMN `created` DATETIME NULL AFTER `reference`;",
      "ALTER TABLE `terms` ADD COLUMN `modified` DATETIME NULL AFTER `created`;",
      //The prefix for a vocabulary's terms in RDF
      "ALTER TABLE `cv` ADD COLUMN `prefix` VARCHAR(20) NULL AFTER `reference`;",
      //Settings for publishing the vocabularies as linked data
      "INSERT IGNORE INTO `config` VALUES ('publisher', '');",
      "INSERT IGNORE INTO `config` VALUES ('license', '');",
      "INSERT IGNORE INTO `config` VALUES ('prefix', '');"
    );
    foreach ($steps as $sql) {
      //1060: the column already exists
      if (!mysqli_query($db, $sql) && $db->errno != 1060) {
        $failed = TRUE;
        print "<div class='error'><p>".t("Update to version 0.4 failed").": ".h($db->error)."</p></div>";
        break;
      }
    }
    if (!$failed) {
      $version_db = setDBVersion("0.4");
      $updated = TRUE;
      print "<p>".t("Ontomasticon has been updated to version 0.4")."</p>";
    }
  }

  //Term languages were widened for language tags such as zh-Hant without a new version, so this runs whenever
  //the column is still narrow, including on databases that were already updated to 0.4
  if (!$failed && termLanguageColumnTooNarrow()) {
    if (mysqli_query($db, "ALTER TABLE `terms` MODIFY COLUMN `language` VARCHAR(".TERM_LANGUAGE_LENGTH.") DEFAULT NULL;")) {
      $updated = TRUE;
      print "<p>".t("Term languages can now be up to 35 characters long.")."</p>";
    } else {
      $failed = TRUE;
      print "<div class='error'><p>".t("Updating term languages failed").": ".h($db->error)."</p></div>";
    }
  }

  if ($updated) {
    $GLOBALS["ontomasticon"]["config"] = getConfig($db);
  } elseif (!$failed) {
    print t("No updates required.");
  }
}
