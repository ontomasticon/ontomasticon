DROP TABLE IF EXISTS `config`;
CREATE TABLE `config` (
  `key` varchar(25) NOT NULL,
  `value` longtext DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `cv`;
CREATE TABLE `cv` (
  `shortname` varchar(30) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `prefix` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`shortname`),
  UNIQUE KEY `shortname_UNIQUE` (`shortname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `terms`;
CREATE TABLE `terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shortname` varchar(45) DEFAULT NULL,
  `name` varchar(200) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `language` varchar(35) DEFAULT NULL,
  `opaque` tinyint(4) DEFAULT NULL,
  `type` varchar(10) NOT NULL DEFAULT 'concept',
  `range_cv` varchar(30) DEFAULT NULL,
  `datatype` varchar(10) DEFAULT NULL,
  `cv` varchar(30) DEFAULT NULL,
  `parent` int(11) DEFAULT NULL,
  `invalid_reason` varchar(45) DEFAULT NULL,
  `broader` int(11) DEFAULT NULL,
  `reference` text DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`),
  UNIQUE KEY `shortname_UNIQUE` (`shortname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_UNIQUE` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `attempted` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `email_attempted` (`email`, `attempted`),
  KEY `ip_attempted` (`ip`, `attempted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO config VALUES('version_db', '0.4.3');
INSERT INTO config VALUES('version', '0.4.3');
INSERT INTO config VALUES('mode', 'production');
INSERT INTO config VALUES('default_lang', 'en');
INSERT INTO config VALUES('description', 'Description goes here.');
INSERT INTO config VALUES('site_name', 'Site name.');
INSERT INTO config VALUES('author', 'Firstname Surname');
INSERT INTO config VALUES('publisher', '');
INSERT INTO config VALUES('license', '');
INSERT INTO config VALUES('prefix', '');
INSERT INTO config VALUES('update_check', UNIX_TIMESTAMP()-86400);
INSERT INTO config VALUES('update_check_ok', 1);
INSERT INTO config VALUES('update_available', 0);
INSERT INTO users (email, password, role) VALUES('admin', '$2y$04$oEaHJ.52kzQbFtQzC1zRdOuAkPc5J9il37vqMJofJvZMGqJtaMovW', 'administer');
