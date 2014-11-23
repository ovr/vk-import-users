CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) NOT NULL,
  `firstname` varchar(200) NOT NULL,
  `lastname` varchar(200) DEFAULT NULL,
  `countryId` int(3) DEFAULT NULL,
  `cityId` int(11) DEFAULT NULL,
  `sex` enum('1','2','','') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `users` ADD PRIMARY KEY (`id`);