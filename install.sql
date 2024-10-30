CREATE TABLE IF NOT EXISTS `PREFIX_imaxel_products` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8 NOT NULL,
  `name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `type` tinyint(4) NOT NULL,
  `price` float NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
