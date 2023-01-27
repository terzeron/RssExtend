CREATE TABLE `rss` (
  `id` int NOT NULL AUTO_INCREMENT,
  `feed_id` char(20) NOT NULL DEFAULT '',
  `url` varchar(4096) DEFAULT NULL,
  `mtime` datetime DEFAULT NULL,
  `ctime` datetime DEFAULT NULL,
  `published_time` datetime DEFAULT NULL,
  `status` bit(1) DEFAULT NULL,
  `enabled` bit(1) DEFAULT b'1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `url_idx` (`url`(1024));
