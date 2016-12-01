CREATE TABLE IF NOT EXISTS `copyright_diffs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project` varbinary(20) NOT NULL,
  `lang` varbinary(20) NOT NULL,
  `diff` int(10) unsigned NOT NULL,
  `diff_timestamp` binary(14) NOT NULL,
  `page_title` varbinary(255) NOT NULL,
  `page_ns` int(11) NOT NULL,
  `ithenticate_id` int(11) NOT NULL,
  `report` blob,
  `status` varbinary(255) DEFAULT NULL,
  `status_user` varbinary(255) DEFAULT NULL,
  `review_timestamp` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `diff_idx` (`project`,`lang`,`diff`),
  KEY `copyright_page_idx` (`project`,`lang`,`page_title`,`page_ns`),
  KEY `copyright_time_idx` (`project`,`lang`,`diff_timestamp`)
) DEFAULT CHARSET=binary;
