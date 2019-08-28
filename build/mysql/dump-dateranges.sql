-- MySQL dump 10.16  Distrib 10.4.6-MariaDB, for ubuntu-linux-gnu (x86_64)
--
-- Host: localhost    Database: dateranges
-- ------------------------------------------------------
-- Server version	10.4.6-MariaDB-1:10.4.6+maria~bionic

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET SQL_MODE = "NO_ZERO_IN_DATE";
SET SQL_MODE = "NO_ZERO_DATE";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT = @@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS = @@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION = @@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE = @@TIME_ZONE */;
/*!40103 SET TIME_ZONE = '+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS = @@UNIQUE_CHECKS, UNIQUE_CHECKS = 0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS = 0 */;
/*!40101 SET @OLD_SQL_MODE = @@SQL_MODE, SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO' */;
/*!40101 SET @OLD_SQL_MODE = @@SQL_MODE, SQL_MODE = 'NO_ZERO_IN_DATE' */;
/*!40101 SET @OLD_SQL_MODE = @@SQL_MODE, SQL_MODE = 'NO_ZERO_DATE' */;
/*!40111 SET @OLD_SQL_NOTES = @@SQL_NOTES, SQL_NOTES = 0 */;

DELIMITER $$
--
-- Functions
--
CREATE FUNCTION `pstart`() RETURNS DATE
    NO SQL DETERMINISTIC
    return @pstart$$

DELIMITER ;

DELIMITER $$
--
-- Functions
--
CREATE FUNCTION `pend`() RETURNS DATE
    NO SQL DETERMINISTIC
    return @pend$$

DELIMITER ;

DELIMITER $$
--
-- Functions
--
CREATE FUNCTION `pprice`() RETURNS FLOAT
    NO SQL DETERMINISTIC
    return @pprice$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `dates`
--
DROP TABLE IF EXISTS `dates`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dates`
(
    `id`            int(10) UNSIGNED    NOT NULL COMMENT 'Primary key',
    `modified`      timestamp           DEFAULT current_timestamp() COMMENT 'Last update timestamp',
    `date_start`    date                NOT NULL COMMENT 'Date range start',
    `date_end`      date                NOT NULL COMMENT 'Date range end',
    `price`         float               NOT NULL COMMENT 'Date range price'
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci COMMENT ='Date ranges';
/*!40101 SET character_set_client = @saved_cs_client */;

-- --------------------------------------------------------
--
-- Stand-in structure for view `all_dates`
-- (See below for the actual view)
--
CREATE TABLE `all_dates`
(
    `date_start`    date,
    `date_end`      date,
    `price`         float
);

--
-- Structure for view `all_dates`
--
DROP TABLE IF EXISTS `all_dates`;

CREATE VIEW `all_dates` AS
select `dates`.`date_start` AS `date_start`, `dates`.`date_end` AS `date_end`, `dates`.`price` AS `price`
from `dates`
order by `dates`.`date_start`;

-- --------------------------------------------------------

--
-- Stand-in structure for view `single_dates`
-- (See below for the actual view)
--
CREATE TABLE `range_dates`
(
    `date_start`    date,
    `date_end`      date,
    `price`         float
);

--
-- Structure for view `single_dates`
--
DROP TABLE IF EXISTS `range_dates`;

CREATE VIEW `range_dates` AS
select `dates`.`date_start` AS `date_start`, `dates`.`date_end` AS `date_end`, `dates`.`price` AS `price`
from `dates`
where `dates`.`date_start` BETWEEN `pstart`() AND `pend`()
or `dates`.`date_end` BETWEEN `pstart`() AND `pend`()
or `dates`.`date_start` = (SELECT MAX(`dates`.`date_start`) FROM `dates` WHERE `dates`.`date_start` < `pstart`())
or `dates`.`date_end` = (SELECT MIN(`dates`.`date_end`) FROM `dates` WHERE `dates`.`date_end` > `pend`())
group by `dates`.`id`
order by `dates`.`date_start`;

-- --------------------------------------------------------

--
-- Indexes for dumped tables
--

--
-- Indexes for table `persons`
--
ALTER TABLE `dates`
    ADD PRIMARY KEY (`id`);

ALTER TABLE `dates`
    ADD UNIQUE (`date_start`);

#ALTER TABLE `dates`
#    ADD UNIQUE (`date_end`);

-- --------------------------------------------------------

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `dates`
--
ALTER TABLE `dates`
    MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
    AUTO_INCREMENT = 3;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT = @OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS = @OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION = @OLD_COLLATION_CONNECTION */;

-- --------------------------------------------------------

--
-- Truncate table before insert `dates`
--
TRUNCATE TABLE `dates`;

--
-- Dumping data for table `dates`
--
LOCK TABLES `dates` WRITE;
/*!40000 ALTER TABLE `dates`
    DISABLE KEYS */;
INSERT INTO `dates` (`id`, `modified`, `date_start`, `date_end`, `price`)
VALUES (1, '2019-08-10 13:22:27', '2019-08-12', '2019-08-13', 678.90),
       (2, '2019-08-10 13:22:27', '2019-08-14', '2019-08-20', 123.45);
/*!40000 ALTER TABLE `dates`
    ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE = @OLD_TIME_ZONE */;

-- --------------------------------------------------------

/*!40101 SET SQL_MODE = @OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS = @OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT = @OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS = @OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION = @OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES = @OLD_SQL_NOTES */;

-- Dump completed on 2019-08-10 08:29:25
