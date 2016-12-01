CREATE TABLE IF NOT EXISTS `wikiprojects` (
  `wp_id` int(11) NOT NULL AUTO_INCREMENT,
  `wp_page_title` varbinary(255) DEFAULT NULL,
  `wp_project` varbinary(255) DEFAULT NULL,
  `wp_lang` binary(2) NOT NULL DEFAULT 'en',
  PRIMARY KEY (`wp_id`),
  KEY `wp_page_title` (`wp_page_title`)
) DEFAULT CHARSET=binary;
