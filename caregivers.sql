-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 192.168.1.95    Database: caregivers
-- ------------------------------------------------------
-- Server version	11.4.10-MariaDB-ubu2404

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit`
--

DROP TABLE IF EXISTS `audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` int(11) NOT NULL,
  `page` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ip` varchar(255) NOT NULL,
  `viewed` int(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit`
--

LOCK TABLES `audit` WRITE;
/*!40000 ALTER TABLE `audit` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cg_caregivers`
--

DROP TABLE IF EXISTS `cg_caregivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cg_caregivers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `email` varchar(155) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `color` varchar(9) NOT NULL DEFAULT '#3788d8',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`user_id`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cg_caregivers`
--

LOCK TABLES `cg_caregivers` WRITE;
/*!40000 ALTER TABLE `cg_caregivers` DISABLE KEYS */;
INSERT INTO `cg_caregivers` VALUES (1,'Lowell Bergey','2677180001','bergey@thebergeys.com',NULL,'#3788d8',1,'2026-05-18 21:14:36'),(2,'Phyllis','215-527-8092','phyllisleatherman@yahoo.com',4,'#9bb1c5',1,'2026-05-18 21:39:23'),(3,'Gloria','610-324-0238','gbergey90@gmail.com',3,'#08af67',1,'2026-05-18 21:39:58'),(4,'Eileen','484-744-0758','eileenparmer80@gmail.com',NULL,'#e6e033',1,'2026-05-18 21:45:04'),(5,'Ester Potrusilova','856-375-7490','',NULL,'#7675b8',1,'2026-05-18 21:51:07');
/*!40000 ALTER TABLE `cg_caregivers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cg_clients`
--

DROP TABLE IF EXISTS `cg_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cg_clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `notes` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cg_clients`
--

LOCK TABLES `cg_clients` WRITE;
/*!40000 ALTER TABLE `cg_clients` DISABLE KEYS */;
INSERT INTO `cg_clients` VALUES (1,'Gladys Knechel','Auto-created on install. Rename me on the Clients page.',1,'2026-05-18 21:04:28');
/*!40000 ALTER TABLE `cg_clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cg_settings`
--

DROP TABLE IF EXISTS `cg_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cg_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `skey` varchar(64) NOT NULL,
  `sval` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_skey` (`skey`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cg_settings`
--

LOCK TABLES `cg_settings` WRITE;
/*!40000 ALTER TABLE `cg_settings` DISABLE KEYS */;
INSERT INTO `cg_settings` VALUES (1,'sms_provider','voipms'),(2,'sms_user_id',''),(3,'sms_pass',''),(4,'sms_did',''),(5,'sms_private_ip',''),(6,'sms_private_port',''),(7,'sms_private_user',''),(8,'sms_private_pass',''),(9,'default_client_id','1');
/*!40000 ALTER TABLE `cg_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cg_shift_attachments`
--

DROP TABLE IF EXISTS `cg_shift_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cg_shift_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shift_id` int(11) NOT NULL,
  `note_id` int(11) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `orig_name` varchar(255) NOT NULL,
  `mime` varchar(80) NOT NULL,
  `size_bytes` int(11) NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shift` (`shift_id`),
  KEY `idx_note` (`note_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cg_shift_attachments`
--

LOCK TABLES `cg_shift_attachments` WRITE;
/*!40000 ALTER TABLE `cg_shift_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `cg_shift_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cg_shift_notes`
--

DROP TABLE IF EXISTS `cg_shift_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cg_shift_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shift_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `author_user_id` int(11) DEFAULT NULL,
  `author_caregiver_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `edited_at` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shift` (`shift_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cg_shift_notes`
--

LOCK TABLES `cg_shift_notes` WRITE;
/*!40000 ALTER TABLE `cg_shift_notes` DISABLE KEYS */;
INSERT INTO `cg_shift_notes` VALUES (11,6,'did great',1,NULL,'2026-05-19 07:51:01',NULL,NULL),(12,4,'ate breakfast',1,NULL,'2026-05-19 07:51:12',NULL,NULL),(13,1,'slept well',1,NULL,'2026-05-19 07:51:27',NULL,NULL);
/*!40000 ALTER TABLE `cg_shift_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cg_shifts`
--

DROP TABLE IF EXISTS `cg_shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cg_shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `caregiver_id` int(11) NOT NULL,
  `start_dt` datetime NOT NULL,
  `end_dt` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_caregiver` (`caregiver_id`),
  KEY `idx_range` (`client_id`,`start_dt`,`end_dt`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cg_shifts`
--

LOCK TABLES `cg_shifts` WRITE;
/*!40000 ALTER TABLE `cg_shifts` DISABLE KEYS */;
INSERT INTO `cg_shifts` VALUES (1,1,1,'2026-05-18 00:00:00','2026-05-18 12:00:00',1,'2026-05-18 21:14:56','2026-05-18 22:15:52'),(2,1,1,'2026-05-19 05:00:00','2026-05-19 14:00:00',1,'2026-05-18 21:30:45','2026-05-18 21:30:45'),(3,1,2,'2026-05-19 14:00:00','2026-05-19 23:00:00',1,'2026-05-18 21:31:00','2026-05-18 21:40:17'),(4,1,3,'2026-05-19 00:00:00','2026-05-19 05:00:00',1,'2026-05-18 21:31:06','2026-05-18 21:40:10'),(5,1,1,'2026-05-19 23:00:00','2026-05-20 00:00:00',1,'2026-05-18 21:31:10','2026-05-18 21:31:10'),(6,1,1,'2026-05-20 05:00:00','2026-05-20 11:00:00',1,'2026-05-18 21:31:29','2026-05-18 21:31:29'),(7,1,1,'2026-05-20 13:00:00','2026-05-20 21:00:00',1,'2026-05-18 21:31:49','2026-05-18 21:31:49'),(8,1,5,'2026-05-22 11:00:00','2026-05-22 17:00:00',1,'2026-05-18 21:52:36','2026-05-18 21:52:36');
/*!40000 ALTER TABLE `cg_shifts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crons`
--

DROP TABLE IF EXISTS `crons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `active` int(1) NOT NULL DEFAULT 1,
  `sort` int(3) NOT NULL,
  `name` varchar(255) NOT NULL,
  `file` varchar(255) NOT NULL,
  `createdby` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crons`
--

LOCK TABLES `crons` WRITE;
/*!40000 ALTER TABLE `crons` DISABLE KEYS */;
INSERT INTO `crons` VALUES (1,0,100,'Auto-Backup','backup.php',1,'2017-09-16 07:49:22','2017-11-11 20:15:36');
/*!40000 ALTER TABLE `crons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crons_logs`
--

DROP TABLE IF EXISTS `crons_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crons_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cron_id` int(11) NOT NULL,
  `datetime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crons_logs`
--

LOCK TABLES `crons_logs` WRITE;
/*!40000 ALTER TABLE `crons_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `crons_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email`
--

DROP TABLE IF EXISTS `email`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `website_name` varchar(100) NOT NULL,
  `smtp_server` varchar(100) NOT NULL,
  `smtp_port` int(10) NOT NULL,
  `email_login` varchar(150) NOT NULL,
  `email_pass` varchar(100) NOT NULL,
  `from_name` varchar(100) NOT NULL,
  `from_email` varchar(150) NOT NULL,
  `transport` varchar(255) NOT NULL,
  `verify_url` varchar(255) NOT NULL,
  `email_act` int(1) NOT NULL,
  `debug_level` int(1) NOT NULL DEFAULT 0,
  `isSMTP` int(1) NOT NULL DEFAULT 0,
  `isHTML` varchar(5) NOT NULL DEFAULT 'true',
  `useSMTPauth` varchar(6) NOT NULL DEFAULT 'true',
  `authtype` varchar(50) DEFAULT 'CRAM-MD5',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email`
--

LOCK TABLES `email` WRITE;
/*!40000 ALTER TABLE `email` DISABLE KEYS */;
INSERT INTO `email` VALUES (1,'User Spice','smtp.gmail.com',587,'yourEmail@gmail.com','1234','User Spice','yourEmail@gmail.com','tls','http://localhost/userspice',0,0,1,'true','true','CRAM-MD5');
/*!40000 ALTER TABLE `email` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `groups_menus`
--

DROP TABLE IF EXISTS `groups_menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `groups_menus` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(11) unsigned NOT NULL,
  `menu_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `menu_id` (`menu_id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `groups_menus`
--

LOCK TABLES `groups_menus` WRITE;
/*!40000 ALTER TABLE `groups_menus` DISABLE KEYS */;
INSERT INTO `groups_menus` VALUES (5,0,3),(6,0,1),(7,0,2),(8,0,51),(9,0,52),(10,0,37),(11,0,38),(12,2,39),(13,2,40),(14,2,41),(15,2,42),(16,2,43),(17,2,44),(18,2,45),(19,0,46),(20,0,47),(21,0,49),(25,0,18),(26,0,20),(27,0,21),(28,0,7),(29,0,8),(30,2,9),(31,2,10),(32,2,11),(33,2,12),(34,2,13),(35,2,14),(36,2,15),(37,0,16),(38,1,15);
/*!40000 ALTER TABLE `groups_menus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `keys`
--

DROP TABLE IF EXISTS `keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stripe_ts` varchar(255) NOT NULL,
  `stripe_tp` varchar(255) NOT NULL,
  `stripe_ls` varchar(255) NOT NULL,
  `stripe_lp` varchar(255) NOT NULL,
  `recap_pub` varchar(100) NOT NULL,
  `recap_pri` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `keys`
--

LOCK TABLES `keys` WRITE;
/*!40000 ALTER TABLE `keys` DISABLE KEYS */;
/*!40000 ALTER TABLE `keys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `cloak_from` int(11) DEFAULT NULL,
  `logdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `logtype` varchar(25) NOT NULL,
  `lognote` mediumtext NOT NULL,
  `ip` varchar(75) DEFAULT NULL,
  `metadata` blob DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logs`
--

LOCK TABLES `logs` WRITE;
/*!40000 ALTER TABLE `logs` DISABLE KEYS */;
INSERT INTO `logs` VALUES (1,1,NULL,'2022-12-23 12:05:38','System Updates','Update 2022-05-04a successfully deployed.','::1',NULL),(2,1,NULL,'2022-12-23 12:05:43','login','User logged in.','::1',NULL),(3,1,NULL,'2022-12-23 12:06:38','System Updates','Update 2022-11-06a successfully deployed.','::1',NULL),(4,1,NULL,'2022-12-23 12:06:38','System Updates','Update 2022-11-20a successfully deployed.','::1',NULL),(5,1,NULL,'2022-12-23 12:06:38','System Updates','Update 2022-12-04a successfully deployed.','::1',NULL),(6,1,NULL,'2022-12-23 12:06:38','System Updates','Update 2022-12-22a successfully deployed.','::1',NULL),(7,1,NULL,'2022-12-23 12:06:38','System Updates','Update 2022-12-23a successfully deployed.','::1',NULL),(8,1,NULL,'2022-12-23 12:16:27','login','User logged in.','::1',NULL),(9,1,NULL,'2024-09-25 09:30:55','System Updates','Update 2023-01-02a successfully deployed.','::1',NULL),(10,1,NULL,'2024-09-25 09:30:55','System Updates','Update 2023-01-03a successfully deployed.','::1',NULL),(11,1,NULL,'2024-09-25 09:30:55','System Updates','Update 2023-01-03b successfully deployed.','::1',NULL),(12,1,NULL,'2024-09-25 09:30:55','System Updates','Update 2023-01-05a successfully deployed.','::1',NULL),(13,1,NULL,'2024-09-25 09:30:55','System Updates','Update 2023-01-07a successfully deployed.','::1',NULL),(14,1,NULL,'2024-09-25 09:30:55','System Updates','Update 2023-02-10a successfully deployed.','::1',NULL),(15,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2023-05-19a successfully deployed.','::1',NULL),(16,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2023-06-29a successfully deployed.','::1',NULL),(17,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2023-06-29b successfully deployed.','::1',NULL),(18,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2023-11-15a successfully deployed.','::1',NULL),(19,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2023-11-17a successfully deployed.','::1',NULL),(20,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2024-03-12a successfully deployed.','::1',NULL),(21,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2024-03-13a successfully deployed.','::1',NULL),(22,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2024-03-14a successfully deployed.','::1',NULL),(23,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2024-03-15a successfully deployed.','::1',NULL),(24,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2024-03-17a successfully deployed.','::1',NULL),(25,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2024-03-17b successfully deployed.','::1',NULL),(26,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2024-03-18a successfully deployed.','::1',NULL),(27,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2024-03-20a successfully deployed.','::1',NULL),(28,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2024-03-22a successfully deployed.','::1',NULL),(29,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2024-04-01a successfully deployed.','::1',NULL),(30,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2024-04-13a successfully deployed.','::1',NULL),(31,1,NULL,'2024-09-25 09:30:56','System Updates','Update 2024-06-24a successfully deployed.','::1',NULL),(32,1,NULL,'2024-09-25 09:31:58','login','User logged in.','::1',NULL),(33,1,NULL,'2025-04-12 10:51:28','System Updates','Update 2024-09-25a successfully deployed.','::1',NULL),(34,1,NULL,'2025-04-12 10:51:28','System Updates','Update 2024-11-22a successfully deployed.','::1',NULL),(35,1,NULL,'2025-04-12 10:51:28','System Updates','Update 2024-12-16a successfully deployed.','::1',NULL),(36,1,NULL,'2025-04-12 10:51:28','System Updates','Update 2024-12-21a successfully deployed.','::1',NULL),(37,1,NULL,'2025-04-12 10:51:28','System Updates','Update 2025-02-23a successfully deployed.','::1',NULL),(38,1,NULL,'2025-04-12 10:51:28','System Updates','Update 2025-03-02a successfully deployed.','::1',NULL),(39,1,NULL,'2025-04-12 10:51:28','System Updates','Update 2025-03-03a successfully deployed.','::1',NULL),(40,1,NULL,'2025-04-12 10:52:00','login','User logged in.','::1',NULL),(41,1,NULL,'2025-04-12 10:52:55','User','Updated password.','::1',NULL),(42,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-04-24a successfully deployed.','::1',NULL),(43,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-05-27a successfully deployed.','::1',NULL),(44,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-06-01a successfully deployed.','::1',NULL),(45,1,NULL,'2026-05-19 00:30:49','System Updates','us_passkeys table found, attempting to alter it.','::1',NULL),(46,1,NULL,'2026-05-19 00:30:49','System Updates','Changed credentialId to credential_id VARBINARY(255).','::1',NULL),(47,1,NULL,'2026-05-19 00:30:49','System Updates','Changed publicKey to credential_public_key BLOB.','::1',NULL),(48,1,NULL,'2026-05-19 00:30:49','System Updates','Added column user_handle to us_passkeys.','::1',NULL),(49,1,NULL,'2026-05-19 00:30:49','System Updates','Added column transports to us_passkeys.','::1',NULL),(50,1,NULL,'2026-05-19 00:30:49','System Updates','Added column attestation_type to us_passkeys.','::1',NULL),(51,1,NULL,'2026-05-19 00:30:49','System Updates','Added column trust_path to us_passkeys.','::1',NULL),(52,1,NULL,'2026-05-19 00:30:49','System Updates','Added column aaguid to us_passkeys.','::1',NULL),(53,1,NULL,'2026-05-19 00:30:49','System Updates','Added column signature_counter to us_passkeys.','::1',NULL),(54,1,NULL,'2026-05-19 00:30:49','System Updates','Added column other_ui_data to us_passkeys.','::1',NULL),(55,1,NULL,'2026-05-19 00:30:49','System Updates','Added index idx_user_id to us_passkeys.','::1',NULL),(56,1,NULL,'2026-05-19 00:30:49','System Updates','Added unique index uidx_credential_id to us_passkeys.','::1',NULL),(57,1,NULL,'2026-05-19 00:30:49','System Updates','Finished attempting to alter us_passkeys table.','::1',NULL),(58,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-06-03a successfully deployed.','::1',NULL),(59,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-06-14a successfully deployed.','::1',NULL),(60,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-06-15a successfully deployed.','::1',NULL),(61,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-06-20a successfully deployed.','::1',NULL),(62,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-06-21a successfully deployed.','::1',NULL),(63,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-06-21b successfully deployed.','::1',NULL),(64,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-06-22a successfully deployed.','::1',NULL),(65,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-06-24a successfully deployed.','::1',NULL),(66,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-06-28a successfully deployed.','::1',NULL),(67,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-07-26a successfully deployed.','::1',NULL),(68,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-07-30a successfully deployed.','::1',NULL),(69,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-08-08a successfully deployed.','::1',NULL),(70,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-08-15a successfully deployed.','::1',NULL),(71,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-08-22a successfully deployed.','::1',NULL),(72,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2025-11-09a successfully deployed.','::1',NULL),(73,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2026-01-01a successfully deployed.','::1',NULL),(74,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2026-01-04a successfully deployed.','::1',NULL),(75,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2026-01-11a successfully deployed.','::1',NULL),(76,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2026-01-24a successfully deployed.','::1',NULL),(77,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2026-02-28a successfully deployed.','::1',NULL),(78,1,NULL,'2026-05-19 00:30:49','System Updates','Update 2026-03-17a successfully deployed.','::1',NULL),(79,1,NULL,'2026-05-19 00:31:08','login','User logged in.','::1',NULL),(80,1,NULL,'2026-05-19 01:17:00','login','User logged in.','192.168.1.152',NULL),(81,1,NULL,'2026-05-19 01:30:09','login','User logged in.','192.168.1.152',NULL),(82,1,NULL,'2026-05-19 01:35:58','User Manager','Added user gbergey.','::1',NULL),(83,1,NULL,'2026-05-19 01:36:17','User Manager','User # 3 Updated. Added 1 permission(s) to Gloria Bergey.','::1',NULL),(84,1,NULL,'2026-05-19 01:38:14','User Manager','Added user pleatherman.','::1',NULL),(85,1,NULL,'2026-05-19 01:38:20','User Manager','User # 4 Updated. Added 1 permission(s) to Phyllis Leatherman.','::1',NULL),(86,3,NULL,'2026-05-19 01:54:17','login','User logged in.','192.168.1.150',NULL),(87,1,NULL,'2026-05-19 02:11:04','login','User logged in.','192.168.1.152',NULL);
/*!40000 ALTER TABLE `logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menus`
--

DROP TABLE IF EXISTS `menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menus` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `menu_title` varchar(255) NOT NULL,
  `parent` int(10) NOT NULL,
  `dropdown` int(1) NOT NULL,
  `logged_in` int(1) NOT NULL,
  `display_order` int(10) NOT NULL,
  `label` varchar(255) NOT NULL,
  `link` varchar(255) NOT NULL,
  `icon_class` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menus`
--

LOCK TABLES `menus` WRITE;
/*!40000 ALTER TABLE `menus` DISABLE KEYS */;
INSERT INTO `menus` VALUES (1,'main',2,0,1,1,'{{home}}','','fa fa-fw fa-home'),(2,'main',-1,1,1,14,'','','fa fa-fw fa-cogs'),(3,'main',-1,0,1,11,'{{username}}','users/account.php','fa fa-fw fa-user'),(4,'main',-1,1,0,3,'{{help}}','','fa fa-fw fa-life-ring'),(5,'main',-1,0,0,2,'{{register}}','users/join.php','fa fa-fw fa-plus-square'),(6,'main',-1,0,0,1,'{{login}}','users/login.php','fa fa-fw fa-sign-in'),(7,'main',2,0,1,2,'{{account}}','users/account.php','fa fa-fw fa-user'),(8,'main',2,0,1,3,'{{hr}}','',''),(9,'main',2,0,1,4,'{{dashboard}}','users/admin.php','fa fa-fw fa-cogs'),(10,'main',2,0,1,5,'{{users}}','users/admin.php?view=users','fa fa-fw fa-user'),(11,'main',2,0,1,6,'{{perms}}','users/admin.php?view=permissions','fa fa-fw fa-lock'),(12,'main',2,0,1,7,'{{pages}}','users/admin.php?view=pages','fa fa-fw fa-wrench'),(13,'main',2,0,1,9,'{{logs}}','users/admin.php?view=logs','fa fa-fw fa-search'),(14,'main',2,0,1,10,'{{hr}}','',''),(15,'main',2,0,1,11,'{{logout}}','users/logout.php','fa fa-fw fa-sign-out'),(16,'main',-1,0,0,0,'{{home}}','','fa fa-fw fa-home'),(17,'main',-1,0,1,10,'{{home}}','','fa fa-fw fa-home'),(18,'main',4,0,0,1,'{{forgot}}','users/forgot_password.php','fa fa-fw fa-wrench'),(20,'main',4,0,0,99999,'{{resend}}','users/verify_resend.php','fa fa-exclamation-triangle');
/*!40000 ALTER TABLE `menus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `message_threads`
--

DROP TABLE IF EXISTS `message_threads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `message_threads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `msg_to` int(11) NOT NULL,
  `msg_from` int(11) NOT NULL,
  `msg_subject` varchar(255) NOT NULL,
  `last_update` datetime NOT NULL,
  `last_update_by` int(11) NOT NULL,
  `archive_from` int(1) NOT NULL DEFAULT 0,
  `archive_to` int(1) NOT NULL DEFAULT 0,
  `hidden_from` int(1) NOT NULL DEFAULT 0,
  `hidden_to` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `message_threads`
--

LOCK TABLES `message_threads` WRITE;
/*!40000 ALTER TABLE `message_threads` DISABLE KEYS */;
/*!40000 ALTER TABLE `message_threads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `msg_from` int(11) NOT NULL,
  `msg_to` int(11) NOT NULL,
  `msg_body` mediumtext NOT NULL,
  `msg_read` int(1) NOT NULL,
  `msg_thread` int(11) NOT NULL,
  `deleted` int(1) NOT NULL,
  `sent_on` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `message` longtext NOT NULL,
  `is_read` tinyint(4) NOT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `date_created` datetime DEFAULT NULL,
  `date_read` datetime DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `class` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `private` int(11) NOT NULL DEFAULT 0,
  `re_auth` int(1) NOT NULL DEFAULT 0,
  `core` int(1) DEFAULT 0,
  `lang_key` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_page` (`page`)
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pages`
--

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
INSERT INTO `pages` VALUES (1,'index.php','Home',0,0,1,NULL),(2,'z_us_root.php','',0,0,1,NULL),(3,'users/account.php','Account Dashboard',1,0,1,NULL),(4,'users/admin.php','Admin Dashboard',1,0,1,NULL),(14,'users/forgot_password.php','Forgotten Password',0,0,1,NULL),(15,'users/forgot_password_reset.php','Reset Forgotten Password',0,0,1,NULL),(16,'users/index.php','Home',0,0,1,NULL),(17,'users/init.php','',0,0,1,NULL),(18,'users/join.php','Join',0,0,1,NULL),(20,'users/login.php','Login',0,0,1,NULL),(21,'users/logout.php','Logout',0,0,1,NULL),(24,'users/user_settings.php','User Settings',1,0,1,NULL),(25,'users/verify.php','Account Verification',0,0,1,NULL),(26,'users/verify_resend.php','Account Verification',0,0,1,NULL),(45,'users/maintenance.php','Maintenance',0,0,1,NULL),(68,'users/update.php','Update Manager',1,0,1,NULL),(81,'users/admin_pin.php','Verification PIN Set',1,0,1,NULL),(90,'users/complete.php',NULL,1,0,0,NULL);
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permission_page_matches`
--

DROP TABLE IF EXISTS `permission_page_matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permission_page_matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permission_id` int(11) DEFAULT NULL,
  `page_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permission_page_matches`
--

LOCK TABLES `permission_page_matches` WRITE;
/*!40000 ALTER TABLE `permission_page_matches` DISABLE KEYS */;
INSERT INTO `permission_page_matches` VALUES (3,1,24),(14,2,4),(15,1,3),(38,2,68),(54,1,81);
/*!40000 ALTER TABLE `permission_page_matches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `descrip` varchar(255) DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'User','Standard User'),(2,'Administrator','UserSpice Administrator'),(3,'Caregiver','Can view all shifts and manage their own');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plg_social_logins`
--

DROP TABLE IF EXISTS `plg_social_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plg_social_logins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plugin` varchar(50) NOT NULL,
  `provider` varchar(50) NOT NULL,
  `enabledsetting` varchar(50) NOT NULL,
  `image` varchar(255) NOT NULL,
  `link` varchar(255) NOT NULL,
  `built_in` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plg_social_logins`
--

LOCK TABLES `plg_social_logins` WRITE;
/*!40000 ALTER TABLE `plg_social_logins` DISABLE KEYS */;
/*!40000 ALTER TABLE `plg_social_logins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plg_tags`
--

DROP TABLE IF EXISTS `plg_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plg_tags` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tag` varchar(255) DEFAULT NULL,
  `descrip` varchar(255) DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plg_tags`
--

LOCK TABLES `plg_tags` WRITE;
/*!40000 ALTER TABLE `plg_tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `plg_tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plg_tags_matches`
--

DROP TABLE IF EXISTS `plg_tags_matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plg_tags_matches` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tag_id` int(11) unsigned NOT NULL,
  `tag_name` varchar(255) DEFAULT NULL,
  `user_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plg_tags_matches`
--

LOCK TABLES `plg_tags_matches` WRITE;
/*!40000 ALTER TABLE `plg_tags_matches` DISABLE KEYS */;
/*!40000 ALTER TABLE `plg_tags_matches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `profiles`
--

DROP TABLE IF EXISTS `profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `bio` mediumtext NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `profiles`
--

LOCK TABLES `profiles` WRITE;
/*!40000 ALTER TABLE `profiles` DISABLE KEYS */;
INSERT INTO `profiles` VALUES (1,1,'&lt;h1&gt;This is the Admin&#039;s bio.&lt;/h1&gt;'),(2,2,'This is your bio');
/*!40000 ALTER TABLE `profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `recaptcha` int(1) NOT NULL DEFAULT 0,
  `force_ssl` int(1) NOT NULL,
  `css_sample` int(1) NOT NULL,
  `site_name` varchar(100) NOT NULL,
  `language` varchar(15) DEFAULT NULL,
  `site_offline` int(1) NOT NULL,
  `force_pr` int(1) NOT NULL,
  `glogin` int(1) NOT NULL DEFAULT 0,
  `fblogin` int(1) NOT NULL,
  `gid` text DEFAULT NULL,
  `gsecret` text DEFAULT NULL,
  `gredirect` text DEFAULT NULL,
  `ghome` text DEFAULT NULL,
  `fbid` text DEFAULT NULL,
  `fbsecret` text DEFAULT NULL,
  `fbcallback` text DEFAULT NULL,
  `graph_ver` text DEFAULT NULL,
  `finalredir` text DEFAULT NULL,
  `req_cap` int(1) NOT NULL,
  `req_num` int(1) NOT NULL,
  `min_pw` int(2) NOT NULL,
  `max_pw` int(3) NOT NULL,
  `min_un` int(2) NOT NULL,
  `max_un` int(3) NOT NULL,
  `messaging` int(1) NOT NULL,
  `snooping` int(1) NOT NULL,
  `echouser` int(11) NOT NULL,
  `wys` int(1) NOT NULL,
  `change_un` int(1) NOT NULL,
  `backup_dest` text DEFAULT NULL,
  `backup_source` text DEFAULT NULL,
  `backup_table` text DEFAULT NULL,
  `msg_notification` int(1) NOT NULL,
  `permission_restriction` int(1) NOT NULL,
  `auto_assign_un` int(1) NOT NULL,
  `page_permission_restriction` int(1) NOT NULL,
  `msg_blocked_users` int(1) NOT NULL,
  `msg_default_to` int(1) NOT NULL,
  `notifications` int(1) NOT NULL,
  `notif_daylimit` int(3) NOT NULL,
  `recap_public` text DEFAULT NULL,
  `recap_private` text DEFAULT NULL,
  `page_default_private` int(1) NOT NULL,
  `navigation_type` tinyint(1) NOT NULL,
  `copyright` varchar(255) NOT NULL,
  `custom_settings` int(1) NOT NULL,
  `system_announcement` varchar(255) NOT NULL,
  `twofa` int(1) DEFAULT 0,
  `force_notif` tinyint(1) DEFAULT NULL,
  `cron_ip` varchar(255) DEFAULT NULL,
  `registration` tinyint(1) DEFAULT NULL,
  `join_vericode_expiry` int(9) unsigned NOT NULL,
  `reset_vericode_expiry` int(9) unsigned NOT NULL,
  `admin_verify` tinyint(1) NOT NULL,
  `admin_verify_timeout` int(9) NOT NULL,
  `session_manager` tinyint(1) NOT NULL,
  `template` varchar(255) DEFAULT 'standard',
  `saas` tinyint(1) DEFAULT NULL,
  `redirect_uri_after_login` mediumtext DEFAULT NULL,
  `show_tos` tinyint(1) DEFAULT 1,
  `default_language` varchar(11) DEFAULT NULL,
  `allow_language` tinyint(1) DEFAULT NULL,
  `spice_api` varchar(75) DEFAULT NULL,
  `announce` datetime DEFAULT NULL,
  `bleeding_edge` tinyint(1) DEFAULT 0,
  `err_time` int(11) DEFAULT 15,
  `container_open_class` text DEFAULT NULL,
  `debug` tinyint(1) DEFAULT 0,
  `widgets` text DEFAULT NULL,
  `no_passwords` tinyint(1) DEFAULT 0,
  `email_login` tinyint(1) DEFAULT 0,
  `pwl_length` int(3) DEFAULT 5,
  `passkeys` tinyint(1) DEFAULT 0,
  `totp` tinyint(1) DEFAULT 0,
  `oauth_server` tinyint(1) DEFAULT 0,
  `oauth` tinyint(1) DEFAULT 0,
  `behind_reverse_proxy` tinyint(1) DEFAULT 0,
  `max_users_dt` int(11) NOT NULL DEFAULT 2000,
  `social_login_location` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,0,0,0,'UserSpice','en',0,0,0,0,'','','','','','','','','',0,0,6,150,4,150,0,1,0,1,0,'/','everything','',0,0,0,0,0,1,0,7,'6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI','6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe',1,1,'UserSpice',1,'',0,0,'off',1,24,15,1,120,0,'customizer',NULL,NULL,1,'en-US',0,NULL,'2026-05-19 07:52:38',0,15,'container-fluid',0,'settings,misc,tools,plugins,snapshot,active_users,active-users',0,0,6,0,0,0,0,0,0,1);
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `updates`
--

DROP TABLE IF EXISTS `updates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `migration` varchar(15) NOT NULL,
  `applied_on` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `update_skipped` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=142 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `updates`
--

LOCK TABLES `updates` WRITE;
/*!40000 ALTER TABLE `updates` DISABLE KEYS */;
INSERT INTO `updates` VALUES (15,'1XdrInkjV86F','2018-02-18 22:33:24',NULL),(16,'3GJYaKcqUtw7','2018-04-25 16:51:08',NULL),(17,'3GJYaKcqUtz8','2018-04-25 16:51:08',NULL),(18,'69qa8h6E1bzG','2018-04-25 16:51:08',NULL),(19,'2XQjsKYJAfn1','2018-04-25 16:51:08',NULL),(20,'549DLFeHMNw7','2018-04-25 16:51:08',NULL),(21,'4Dgt2XVjgz2x','2018-04-25 16:51:08',NULL),(22,'VLBp32gTWvEo','2018-04-25 16:51:08',NULL),(23,'Q3KlhjdtxE5X','2018-04-25 16:51:08',NULL),(24,'ug5D3pVrNvfS','2018-04-25 16:51:08',NULL),(25,'69FbVbv4Jtrz','2018-04-25 16:51:09',NULL),(26,'4A6BdJHyvP4a','2018-04-25 16:51:09',NULL),(27,'37wvsb5BzymK','2018-04-25 16:51:09',NULL),(28,'c7tZQf926zKq','2018-04-25 16:51:09',NULL),(29,'ockrg4eU33GP','2018-04-25 16:51:09',NULL),(30,'XX4zArPs4tor','2018-04-25 16:51:09',NULL),(31,'pv7r2EHbVvhD','2018-04-26 00:00:00',NULL),(32,'uNT7NpgcBDFD','2018-04-26 00:00:00',NULL),(33,'mS5VtQCZjyJs','2018-12-11 14:19:16',NULL),(34,'23rqAv5elJ3G','2018-12-11 14:19:51',NULL),(35,'qPEARSh49fob','2019-01-01 12:01:01',NULL),(36,'FyMYJ2oeGCTX','2019-01-01 12:01:01',NULL),(37,'iit5tHSLatiS','2019-01-01 12:01:01',NULL),(38,'hcA5B3PLhq6E','2020-07-16 11:27:53',NULL),(39,'VNEno3E4zaNz','2020-07-16 11:27:53',NULL),(40,'2ZB9mg1l0JXe','2020-07-16 11:27:53',NULL),(41,'B9t6He7qmFXa','2020-07-16 11:27:53',NULL),(42,'86FkFVV4TGRg','2020-07-16 11:27:53',NULL),(43,'y4A1Y0u9n2Rt','2020-07-16 11:27:53',NULL),(44,'Tm5xY22MM8eC','2020-07-16 11:27:53',NULL),(45,'0YXdrInkjV86F','2020-07-16 11:27:53',NULL),(46,'99plgnkjV86','2020-07-16 11:27:53',NULL),(47,'0DaShInkjV86','2020-07-16 11:27:53',NULL),(48,'0DaShInkjVz1','2020-07-16 11:27:53',NULL),(49,'y4A1Y0u9n2SS','2020-07-16 11:27:53',NULL),(50,'0DaShInkjV87','2020-07-16 11:27:53',NULL),(51,'0DaShInkjV88','2020-07-16 11:27:53',NULL),(52,'2019-09-04a','2020-07-16 11:27:53',NULL),(53,'2019-09-05a','2020-07-16 11:27:53',NULL),(54,'2019-09-26a','2020-07-16 11:27:53',NULL),(55,'2019-11-19a','2020-07-16 11:27:53',NULL),(56,'2019-12-28a','2020-07-16 11:27:53',NULL),(57,'2020-01-21a','2020-07-16 11:27:54',NULL),(58,'2020-03-26a','2020-07-16 11:27:54',NULL),(59,'2020-04-17a','2020-07-16 11:27:54',NULL),(60,'2020-06-06a','2020-07-16 11:27:54',NULL),(61,'2020-06-30a','2020-07-16 11:27:54',NULL),(62,'2020-07-01a','2020-07-16 11:27:54',NULL),(63,'2020-07-16a','2020-10-08 01:26:22',NULL),(64,'2020-07-30a','2020-10-08 01:26:22',NULL),(65,'2020-10-06a','2022-04-15 17:37:11',NULL),(66,'2020-11-03a','2022-04-15 17:37:11',NULL),(67,'2020-11-08a','2022-04-15 17:37:11',NULL),(68,'2020-11-10a','2022-04-15 17:37:11',NULL),(69,'2020-11-10b','2022-04-15 17:37:11',NULL),(70,'2020-12-17a','2022-04-15 17:37:11',NULL),(71,'2020-12-28a','2022-04-15 17:37:11',NULL),(72,'2021-01-20a','2022-04-15 17:37:11',NULL),(73,'2021-02-16a','2022-04-15 17:37:11',NULL),(74,'2021-04-14a','2022-04-15 17:37:11',NULL),(75,'2021-04-15a','2022-04-15 17:37:11',NULL),(76,'2021-05-20a','2022-04-15 17:37:11',NULL),(77,'2021-07-11a','2022-04-15 17:37:11',NULL),(78,'2021-08-22a','2022-04-15 17:37:11',NULL),(79,'2021-08-24a','2022-04-15 17:37:11',NULL),(80,'2021-09-25a','2022-04-15 17:37:11',NULL),(81,'2021-12-26a','2022-04-15 17:37:11',NULL),(82,'2022-05-04a','2022-12-23 12:05:38',NULL),(83,'2022-11-06a','2022-12-23 12:06:38',NULL),(84,'2022-11-20a','2022-12-23 12:06:38',NULL),(85,'2022-12-04a','2022-12-23 12:06:38',NULL),(86,'2022-12-22a','2022-12-23 12:06:38',NULL),(87,'2022-12-23a','2022-12-23 12:06:38',NULL),(88,'2023-01-02a','2024-09-25 09:30:55',NULL),(89,'2023-01-03a','2024-09-25 09:30:55',NULL),(90,'2023-01-03b','2024-09-25 09:30:55',NULL),(91,'2023-01-05a','2024-09-25 09:30:55',NULL),(92,'2023-01-07a','2024-09-25 09:30:55',NULL),(93,'2023-02-10a','2024-09-25 09:30:55',NULL),(94,'2023-05-19a','2024-09-25 09:30:56',NULL),(95,'2023-06-29a','2024-09-25 09:30:56',NULL),(96,'2023-06-29b','2024-09-25 09:30:56',NULL),(97,'2023-11-15a','2024-09-25 09:30:56',NULL),(98,'2023-11-17a','2024-09-25 09:30:56',NULL),(99,'2024-03-12a','2024-09-25 09:30:56',NULL),(100,'2024-03-13a','2024-09-25 09:30:56',NULL),(101,'2024-03-14a','2024-09-25 09:30:56',NULL),(102,'2024-03-15a','2024-09-25 09:30:56',NULL),(103,'2024-03-17a','2024-09-25 09:30:56',NULL),(104,'2024-03-17b','2024-09-25 09:30:56',NULL),(105,'2024-03-18a','2024-09-25 09:30:56',NULL),(106,'2024-03-20a','2024-09-25 09:30:56',NULL),(107,'2024-03-22a','2024-09-25 09:30:56',NULL),(108,'2024-04-01a','2024-09-25 09:30:56',NULL),(109,'2024-04-13a','2024-09-25 09:30:56',NULL),(110,'2024-06-24a','2024-09-25 09:30:56',NULL),(111,'2024-09-25a','2025-04-12 10:51:28',NULL),(112,'2024-11-22a','2025-04-12 10:51:28',NULL),(113,'2024-12-16a','2025-04-12 10:51:28',NULL),(114,'2024-12-21a','2025-04-12 10:51:28',NULL),(115,'2025-02-23a','2025-04-12 10:51:28',NULL),(116,'2025-03-02a','2025-04-12 10:51:28',NULL),(117,'2025-03-03a','2025-04-12 10:51:28',NULL),(118,'2025-04-24a','2026-05-19 00:30:49',NULL),(119,'2025-05-27a','2026-05-19 00:30:49',NULL),(120,'2025-06-01a','2026-05-19 00:30:49',NULL),(121,'2025-06-03a','2026-05-19 00:30:50',NULL),(122,'2025-06-14a','2026-05-19 00:30:50',NULL),(123,'2025-06-15a','2026-05-19 00:30:50',NULL),(124,'2025-06-20a','2026-05-19 00:30:50',NULL),(125,'2025-06-21a','2026-05-19 00:30:50',NULL),(126,'2025-06-21b','2026-05-19 00:30:50',NULL),(127,'2025-06-22a','2026-05-19 00:30:50',NULL),(128,'2025-06-24a','2026-05-19 00:30:50',NULL),(129,'2025-06-28a','2026-05-19 00:30:50',NULL),(130,'2025-07-26a','2026-05-19 00:30:50',NULL),(131,'2025-07-30a','2026-05-19 00:30:50',NULL),(132,'2025-08-08a','2026-05-19 00:30:50',NULL),(133,'2025-08-15a','2026-05-19 00:30:50',NULL),(134,'2025-08-22a','2026-05-19 00:30:50',NULL),(135,'2025-11-09a','2026-05-19 00:30:50',NULL),(136,'2026-01-01a','2026-05-19 00:30:50',NULL),(137,'2026-01-04a','2026-05-19 00:30:50',NULL),(138,'2026-01-11a','2026-05-19 00:30:50',NULL),(139,'2026-01-24a','2026-05-19 00:30:50',NULL),(140,'2026-02-28a','2026-05-19 00:30:50',NULL),(141,'2026-03-17a','2026-05-19 00:30:50',NULL);
/*!40000 ALTER TABLE `updates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_announcements`
--

DROP TABLE IF EXISTS `us_announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dismissed` int(11) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `ignore` varchar(50) DEFAULT NULL,
  `class` varchar(50) DEFAULT NULL,
  `dismissed_by` int(11) DEFAULT 0,
  `update_announcement` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_announcements`
--

LOCK TABLES `us_announcements` WRITE;
/*!40000 ALTER TABLE `us_announcements` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_email_logins`
--

DROP TABLE IF EXISTS `us_email_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_email_logins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `vericode` varchar(128) DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `login_ip` varchar(50) NOT NULL,
  `login_date` datetime NOT NULL,
  `expired` tinyint(1) DEFAULT 0,
  `expires` datetime DEFAULT NULL,
  `verification_code` varchar(128) DEFAULT NULL,
  `invalid_attempts` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_email_logins`
--

LOCK TABLES `us_email_logins` WRITE;
/*!40000 ALTER TABLE `us_email_logins` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_email_logins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_fingerprint_assets`
--

DROP TABLE IF EXISTS `us_fingerprint_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_fingerprint_assets` (
  `kFingerprintAssetID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `fkFingerprintID` int(11) NOT NULL,
  `IP_Address` varchar(255) NOT NULL,
  `User_Browser` varchar(255) NOT NULL,
  `User_OS` varchar(255) NOT NULL,
  PRIMARY KEY (`kFingerprintAssetID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_fingerprint_assets`
--

LOCK TABLES `us_fingerprint_assets` WRITE;
/*!40000 ALTER TABLE `us_fingerprint_assets` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_fingerprint_assets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_fingerprints`
--

DROP TABLE IF EXISTS `us_fingerprints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_fingerprints` (
  `kFingerprintID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `fkUserID` int(11) NOT NULL,
  `Fingerprint` varchar(32) NOT NULL,
  `Fingerprint_Expiry` datetime NOT NULL,
  `Fingerprint_Added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`kFingerprintID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_fingerprints`
--

LOCK TABLES `us_fingerprints` WRITE;
/*!40000 ALTER TABLE `us_fingerprints` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_fingerprints` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_form_validation`
--

DROP TABLE IF EXISTS `us_form_validation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_form_validation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `value` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `params` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_form_validation`
--

LOCK TABLES `us_form_validation` WRITE;
/*!40000 ALTER TABLE `us_form_validation` DISABLE KEYS */;
INSERT INTO `us_form_validation` VALUES (1,'min','Minimum # of Characters','number'),(2,'max','Maximum # of Characters','number'),(3,'is_numeric','Must be a number','true'),(4,'valid_email','Must be a valid email address','true'),(5,'<','Must be a number less than','number'),(6,'>','Must be a number greater than','number'),(7,'<=','Must be a number less than or equal to','number'),(8,'>=','Must be a number greater than or equal to','number'),(9,'!=','Must not be equal to','text'),(10,'==','Must be equal to','text'),(11,'is_integer','Must be an integer','true'),(12,'is_timezone','Must be a valid timezone name','true'),(13,'is_datetime','Must be a valid DateTime','true');
/*!40000 ALTER TABLE `us_form_validation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_form_views`
--

DROP TABLE IF EXISTS `us_form_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_form_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form_name` varchar(255) NOT NULL,
  `view_name` varchar(255) NOT NULL,
  `fields` mediumtext NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_form_views`
--

LOCK TABLES `us_form_views` WRITE;
/*!40000 ALTER TABLE `us_form_views` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_form_views` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_forms`
--

DROP TABLE IF EXISTS `us_forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_forms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_forms`
--

LOCK TABLES `us_forms` WRITE;
/*!40000 ALTER TABLE `us_forms` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_forms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_ip_blacklist`
--

DROP TABLE IF EXISTS `us_ip_blacklist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_ip_blacklist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(50) NOT NULL,
  `last_user` int(11) NOT NULL DEFAULT 0,
  `reason` int(11) NOT NULL DEFAULT 0,
  `expires` datetime DEFAULT NULL,
  `descrip` varchar(255) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_ip_blacklist`
--

LOCK TABLES `us_ip_blacklist` WRITE;
/*!40000 ALTER TABLE `us_ip_blacklist` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_ip_blacklist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_ip_list`
--

DROP TABLE IF EXISTS `us_ip_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_ip_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_ip_list`
--

LOCK TABLES `us_ip_list` WRITE;
/*!40000 ALTER TABLE `us_ip_list` DISABLE KEYS */;
INSERT INTO `us_ip_list` VALUES (2,'::1',1,'2026-05-19 00:31:08'),(3,'192.168.1.152',1,'2026-05-19 02:11:04'),(4,'192.168.1.150',3,'2026-05-19 01:54:17');
/*!40000 ALTER TABLE `us_ip_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_ip_whitelist`
--

DROP TABLE IF EXISTS `us_ip_whitelist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_ip_whitelist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(50) NOT NULL,
  `descrip` varchar(255) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_ip_whitelist`
--

LOCK TABLES `us_ip_whitelist` WRITE;
/*!40000 ALTER TABLE `us_ip_whitelist` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_ip_whitelist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_login_fails`
--

DROP TABLE IF EXISTS `us_login_fails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_login_fails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `login_method` varchar(50) NOT NULL,
  `ip` varchar(50) NOT NULL,
  `ts` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_login_fails`
--

LOCK TABLES `us_login_fails` WRITE;
/*!40000 ALTER TABLE `us_login_fails` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_login_fails` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_management`
--

DROP TABLE IF EXISTS `us_management`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_management` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page` varchar(255) NOT NULL,
  `view` varchar(255) NOT NULL,
  `feature` varchar(255) NOT NULL,
  `access` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_management`
--

LOCK TABLES `us_management` WRITE;
/*!40000 ALTER TABLE `us_management` DISABLE KEYS */;
INSERT INTO `us_management` VALUES (1,'_admin_manage_ip.php','ip','IP Whitelist/Blacklist',''),(2,'_admin_nav.php','nav','Navigation [List/Add/Delete]',''),(3,'_admin_nav_item.php','nav_item','Navigation [View/Edit]',''),(4,'_admin_pages.php','pages','Page Management [List]',''),(5,'_admin_page.php','page','Page Management [View/Edit]',''),(6,'_admin_security_logs.php','security_logs','Security Logs',''),(7,'_admin_templates.php','templates','Templates',''),(8,'_admin_tools_check_updates.php','updates','Check Updates',''),(16,'_admin_menus.php','menus','Manage UltraMenu',''),(17,'_admin_logs.php','logs','System Logs','');
/*!40000 ALTER TABLE `us_management` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_menu_items`
--

DROP TABLE IF EXISTS `us_menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_menu_items` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `menu` int(11) unsigned NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `link` text DEFAULT NULL,
  `icon_class` varchar(255) DEFAULT NULL,
  `li_class` varchar(255) DEFAULT NULL,
  `a_class` varchar(255) DEFAULT NULL,
  `link_target` varchar(50) DEFAULT NULL,
  `parent` int(11) DEFAULT NULL,
  `display_order` int(11) DEFAULT NULL,
  `disabled` tinyint(1) DEFAULT 0,
  `permissions` varchar(1000) DEFAULT NULL,
  `tags` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_menu_items`
--

LOCK TABLES `us_menu_items` WRITE;
/*!40000 ALTER TABLE `us_menu_items` DISABLE KEYS */;
INSERT INTO `us_menu_items` VALUES (1,1,'dropdown','','','fa fa-cogs',NULL,NULL,'_self',0,14,0,'[1]',NULL),(2,1,'link','{{LOGGED_IN_USERNAME}}','users/account.php','fa fa-user',NULL,NULL,'_self',0,11,0,'[1]',NULL),(3,1,'dropdown','{{MENU_HELP}}','','fa fa-life-ring',NULL,NULL,'_self',0,3,0,'[0]',NULL),(4,1,'link','{{SIGNUP_TEXT}}','users/join.php','fa fa-plus-square',NULL,NULL,'_self',0,2,0,'[0]',NULL),(5,1,'link','{{SIGNIN_BUTTONTEXT}}','users/login.php','fa fa-sign-in',NULL,NULL,'_self',0,1,0,'[0]',NULL),(6,1,'link','{{MENU_HOME}}','','fa fa-home',NULL,NULL,'_self',0,0,0,'[0]',NULL),(7,1,'link','{{MENU_HOME}}','','fa fa-home',NULL,NULL,'_self',0,10,0,'[]',NULL),(8,1,'link','{{MENU_HOME}}','','fa fa-home',NULL,NULL,'_self',1,1,0,'[1]',NULL),(9,1,'link','{{MENU_ACCOUNT}}','users/account.php','fa fa-user',NULL,NULL,'_self',1,2,0,'[1]',NULL),(10,1,'separator','','','',NULL,NULL,'_self',1,3,0,'[1]',NULL),(11,1,'link','{{MENU_DASH}}','users/admin.php','fa fa-cogs',NULL,NULL,'_self',1,4,0,'[2]',NULL),(12,1,'link','{{MENU_USER_MGR}}','users/admin.php?view=users','fa fa-user',NULL,NULL,'_self',1,5,0,'[2]',NULL),(13,1,'link','{{MENU_PERM_MGR}}','users/admin.php?view=permissions','fa fa-lock',NULL,NULL,'_self',1,6,0,'[2]',NULL),(14,1,'link','{{MENU_PAGE_MGR}}','users/admin.php?view=pages','fa fa-wrench',NULL,NULL,'_self',1,7,0,'[2]',NULL),(15,1,'link','{{MENU_LOGS_MGR}}','users/admin.php?view=logs','fa fa-search',NULL,NULL,'_self',1,9,0,'[2]',NULL),(16,1,'separator','','','',NULL,NULL,'_self',1,10,0,'[2]',NULL),(17,1,'link','{{MENU_LOGOUT}}','users/logout.php','fa fa-sign-out',NULL,NULL,'_self',1,11,0,'[2,1]',NULL),(18,1,'link','{{SIGNIN_FORGOTPASS}}','users/forgot_password.php','fa fa-wrench',NULL,NULL,'_self',3,1,0,'[0]',NULL),(19,1,'link','{{VER_RESEND}}','users/verify_resend.php','fa fa-exclamation-triangle',NULL,NULL,'_self',3,99999,0,'[0]',NULL),(45,2,'dropdown','Tools','','fa fa-wrench','','','_self',0,3,0,'[2]',NULL),(46,2,'link','User Manager','users/admin.php?view=users','fa fa-user',NULL,NULL,NULL,45,15,0,'[2]',NULL),(47,2,'link','Bug Report','users/admin.php?view=bugs','fa fa-bug',NULL,NULL,NULL,45,1,0,'[2]',NULL),(48,2,'link','IP Manager','users/admin.php?view=ip','fa fa-warning',NULL,NULL,NULL,45,3,0,'[0]',NULL),(49,2,'link','Cron Jobs','users/admin.php?view=cron','fa fa-terminal',NULL,NULL,NULL,45,2,0,'[2]',NULL),(50,2,'link','Security Logs','users/admin.php?view=security_logs','fa fa-lock',NULL,NULL,NULL,45,9,0,'[2]',NULL),(51,2,'link','System Logs','users/admin.php?view=logs','fa fa-list-ol',NULL,NULL,NULL,45,10,0,'[2]',NULL),(52,2,'link','Templates','users/admin.php?view=templates','fa fa-eye',NULL,NULL,NULL,45,11,0,'[2]',NULL),(53,2,'link','Updates','users/admin.php?view=updates','fa fa-arrow-circle-o-up',NULL,NULL,NULL,45,12,0,'[2]',NULL),(54,2,'link','Page Manager','users/admin.php?view=pages','fa fa-file',NULL,NULL,NULL,45,7,0,'[2]',NULL),(55,2,'link','Permissions','users/admin.php?view=permissions','fa fa-unlock-alt',NULL,NULL,NULL,45,8,0,'[2]',NULL),(56,2,'dropdown','Settings','','fa fa-gear','','','_self',0,4,0,'[2]',NULL),(57,2,'link','General','users/admin.php?view=general','fa fa-check',NULL,NULL,NULL,56,1,0,'[2]',NULL),(58,2,'link','Registration','users/admin.php?view=reg','fa fa-users',NULL,NULL,NULL,56,2,0,'[2]',NULL),(59,2,'link','Email','users/admin.php?view=email','fa fa-envelope',NULL,NULL,NULL,56,3,0,'[0]',NULL),(60,2,'link','Navigation (Classic)','users/admin.php?view=nav','fa fa-rocket',NULL,NULL,NULL,56,4,0,'[2]',NULL),(61,2,'link','UltraMenu','users/admin.php?view=menus','fa fa-lock',NULL,NULL,NULL,56,5,0,'[2]',NULL),(62,2,'link','Dashboard Access','users/admin.php?view=access','fa fa-file-code-o',NULL,NULL,NULL,56,5,0,'[2]',NULL),(63,2,'dropdown','Plugins','#','fa fa-plug','','','_self',0,5,0,'[2]',NULL),(64,2,'snippet','All Plugins','users/includes/menu_hooks/plugins.php','',NULL,NULL,NULL,63,2,0,'[2]',NULL),(65,2,'link','Plugin Manager','users/admin.php?view=plugins','fa fa-puzzle-piece',NULL,NULL,NULL,63,1,0,'[2]',NULL),(66,2,'link','Spice Shaker','users/admin.php?view=spice','fa fa-user-secret','','','_self',0,2,0,'[2]',NULL),(67,2,'link','Home','#','fa fa-home','','','_self',0,1,0,'[2]',NULL),(68,2,'link','Dashboard','users/admin.php','fa-solid fa-desktop','','','_self',0,1,0,'[2]',NULL);
/*!40000 ALTER TABLE `us_menu_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_menus`
--

DROP TABLE IF EXISTS `us_menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_menus` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `menu_name` varchar(255) DEFAULT NULL,
  `type` varchar(75) DEFAULT NULL,
  `nav_class` varchar(255) DEFAULT NULL,
  `theme` varchar(25) DEFAULT NULL,
  `z_index` int(11) DEFAULT NULL,
  `brand_html` text DEFAULT NULL,
  `disabled` tinyint(1) DEFAULT 0,
  `justify` varchar(10) DEFAULT 'right',
  `show_active` tinyint(1) DEFAULT 0,
  `screen_reader_mode` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_menus`
--

LOCK TABLES `us_menus` WRITE;
/*!40000 ALTER TABLE `us_menus` DISABLE KEYS */;
INSERT INTO `us_menus` VALUES (1,'Main Menu','horizontal','','dark',50,'&lt;a href=&quot;{{root}}&quot;&gt;Gladys Knechel&lt;/a&gt;',0,'right',0,0),(2,'Dashboard Menu','horizontal','','dark',55,'&lt;a href=&quot;{{root}}&quot; title=&quot;Home Page&quot;&gt;Gladys Knechel&lt;/a&gt;',0,'right',0,0);
/*!40000 ALTER TABLE `us_menus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_oauth_client_login_options`
--

DROP TABLE IF EXISTS `us_oauth_client_login_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_oauth_client_login_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `oauth` tinyint(1) DEFAULT 0,
  `client_name` varchar(255) DEFAULT 'UserSpice Login',
  `client_icon` varchar(255) DEFAULT 'oauth.png',
  `client_id` varchar(80) DEFAULT NULL,
  `client_secret` varchar(80) DEFAULT NULL,
  `redirect_uri` varchar(200) DEFAULT NULL,
  `server_url` varchar(255) DEFAULT NULL,
  `server_target` varchar(255) DEFAULT 'users/auth/',
  `login_title` varchar(255) DEFAULT 'UserSpice',
  `login_script` varchar(255) DEFAULT 'default_script.php',
  `response_secret` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id` (`client_id`),
  UNIQUE KEY `client_secret` (`client_secret`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_oauth_client_login_options`
--

LOCK TABLES `us_oauth_client_login_options` WRITE;
/*!40000 ALTER TABLE `us_oauth_client_login_options` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_oauth_client_login_options` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_oauth_client_login_tokens`
--

DROP TABLE IF EXISTS `us_oauth_client_login_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_oauth_client_login_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `access_token` varchar(255) NOT NULL,
  `refresh_token` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `scope` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_oauth_client_login_tokens`
--

LOCK TABLES `us_oauth_client_login_tokens` WRITE;
/*!40000 ALTER TABLE `us_oauth_client_login_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_oauth_client_login_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_oauth_client_logins`
--

DROP TABLE IF EXISTS `us_oauth_client_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_oauth_client_logins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `new_user` tinyint(1) DEFAULT 0,
  `ts` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_oauth_client_logins`
--

LOCK TABLES `us_oauth_client_logins` WRITE;
/*!40000 ALTER TABLE `us_oauth_client_logins` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_oauth_client_logins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_oauth_server_clients`
--

DROP TABLE IF EXISTS `us_oauth_server_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_oauth_server_clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_name` varchar(80) NOT NULL,
  `client_description` varchar(200) DEFAULT NULL,
  `client_enabled` tinyint(1) DEFAULT 1,
  `client_id` varchar(80) NOT NULL,
  `client_secret` varchar(80) NOT NULL,
  `redirect_uri` varchar(200) NOT NULL,
  `ip_restrict` varchar(200) DEFAULT NULL,
  `login_title` varchar(255) DEFAULT 'Login with UserSpice',
  `login_form` varchar(255) DEFAULT 'default_login.php',
  `login_script` varchar(255) DEFAULT 'default_script.php',
  `response_secret` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id` (`client_id`),
  UNIQUE KEY `client_secret` (`client_secret`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_oauth_server_clients`
--

LOCK TABLES `us_oauth_server_clients` WRITE;
/*!40000 ALTER TABLE `us_oauth_server_clients` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_oauth_server_clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_oauth_server_codes`
--

DROP TABLE IF EXISTS `us_oauth_server_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_oauth_server_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `auth_code` varchar(80) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `redirect_uri` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `auth_code` (`auth_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_oauth_server_codes`
--

LOCK TABLES `us_oauth_server_codes` WRITE;
/*!40000 ALTER TABLE `us_oauth_server_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_oauth_server_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_oauth_server_settings`
--

DROP TABLE IF EXISTS `us_oauth_server_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_oauth_server_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `other_columns` text DEFAULT NULL,
  `include_tags` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_oauth_server_settings`
--

LOCK TABLES `us_oauth_server_settings` WRITE;
/*!40000 ALTER TABLE `us_oauth_server_settings` DISABLE KEYS */;
INSERT INTO `us_oauth_server_settings` VALUES (1,'language,created',1);
/*!40000 ALTER TABLE `us_oauth_server_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_oauth_server_tokens`
--

DROP TABLE IF EXISTS `us_oauth_server_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_oauth_server_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `access_token` varchar(80) NOT NULL,
  `refresh_token` varchar(80) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `access_token` (`access_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_oauth_server_tokens`
--

LOCK TABLES `us_oauth_server_tokens` WRITE;
/*!40000 ALTER TABLE `us_oauth_server_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_oauth_server_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_passkeys`
--

DROP TABLE IF EXISTS `us_passkeys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_passkeys` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT 0,
  `credential_id` varbinary(255) DEFAULT NULL,
  `credential_public_key` blob DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `times_used` int(1) DEFAULT 0,
  `last_used` timestamp NULL DEFAULT NULL,
  `last_ip` varchar(255) DEFAULT NULL,
  `passkey_note` varchar(255) DEFAULT NULL,
  `user_handle` varbinary(64) DEFAULT NULL,
  `transports` text DEFAULT NULL,
  `attestation_type` varchar(32) DEFAULT NULL,
  `trust_path` text DEFAULT NULL,
  `aaguid` varchar(36) DEFAULT NULL,
  `signature_counter` bigint(20) unsigned DEFAULT 0,
  `other_ui_data` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uidx_credential_id` (`credential_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_passkeys`
--

LOCK TABLES `us_passkeys` WRITE;
/*!40000 ALTER TABLE `us_passkeys` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_passkeys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_password_strength`
--

DROP TABLE IF EXISTS `us_password_strength`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_password_strength` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `enforce_rules` tinyint(1) DEFAULT 0,
  `meter_active` tinyint(1) DEFAULT 0,
  `min_length` int(11) DEFAULT 8,
  `max_length` int(11) DEFAULT 24,
  `require_lowercase` tinyint(1) DEFAULT 1,
  `require_uppercase` tinyint(1) DEFAULT 1,
  `require_numbers` tinyint(1) DEFAULT 1,
  `require_symbols` tinyint(1) DEFAULT 1,
  `min_score` int(11) DEFAULT 5,
  `uppercase_score` int(11) NOT NULL DEFAULT 6,
  `lowercase_score` int(11) NOT NULL DEFAULT 6,
  `number_score` int(11) NOT NULL DEFAULT 6,
  `symbol_score` int(11) NOT NULL DEFAULT 11,
  `greater_eight` int(11) NOT NULL DEFAULT 15,
  `greater_twelve` int(11) NOT NULL DEFAULT 28,
  `greater_sixteen` int(11) NOT NULL DEFAULT 40,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_password_strength`
--

LOCK TABLES `us_password_strength` WRITE;
/*!40000 ALTER TABLE `us_password_strength` DISABLE KEYS */;
INSERT INTO `us_password_strength` VALUES (1,0,1,10,150,1,1,1,1,75,6,6,6,11,15,28,40);
/*!40000 ALTER TABLE `us_password_strength` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_php_eol`
--

DROP TABLE IF EXISTS `us_php_eol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_php_eol` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `release_version` varchar(20) NOT NULL,
  `eol_date` date NOT NULL,
  `last_checked` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_release_version` (`release_version`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_php_eol`
--

LOCK TABLES `us_php_eol` WRITE;
/*!40000 ALTER TABLE `us_php_eol` DISABLE KEYS */;
INSERT INTO `us_php_eol` VALUES (1,'8.4','2028-12-31','2026-05-19 11:52:40'),(2,'8.3','2027-12-31','2026-05-19 11:52:40'),(3,'8.2','2026-12-31','2026-05-19 11:52:40'),(4,'8.1','2025-12-31','2026-05-19 11:52:40'),(5,'8.0','2023-11-26','2026-05-19 11:52:40'),(6,'7.4','2022-11-28','2026-05-19 11:52:40'),(7,'7.3','2021-12-06','2026-05-19 11:52:40'),(8,'7.2','2020-11-30','2026-05-19 11:52:40'),(9,'7.1','2019-12-01','2026-05-19 11:52:40'),(10,'7.0','2019-01-10','2026-05-19 11:52:40'),(11,'5.6','2018-12-31','2026-05-19 11:52:40');
/*!40000 ALTER TABLE `us_php_eol` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_php_known_bad`
--

DROP TABLE IF EXISTS `us_php_known_bad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_php_known_bad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL,
  `last_checked` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_version` (`version`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_php_known_bad`
--

LOCK TABLES `us_php_known_bad` WRITE;
/*!40000 ALTER TABLE `us_php_known_bad` DISABLE KEYS */;
INSERT INTO `us_php_known_bad` VALUES (1,'5.6.1','2026-05-19 11:52:40');
/*!40000 ALTER TABLE `us_php_known_bad` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_plugin_hooks`
--

DROP TABLE IF EXISTS `us_plugin_hooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_plugin_hooks` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `page` varchar(255) NOT NULL,
  `folder` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `hook` varchar(255) NOT NULL,
  `disabled` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_plugin_hooks`
--

LOCK TABLES `us_plugin_hooks` WRITE;
/*!40000 ALTER TABLE `us_plugin_hooks` DISABLE KEYS */;
INSERT INTO `us_plugin_hooks` VALUES (1,'admin.php?view=user','userspice_core','form','hooks/tags_admin_user_form.php',0),(2,'admin.php?view=user','userspice_core','post','hooks/tags_admin_user_post.php',0);
/*!40000 ALTER TABLE `us_plugin_hooks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_plugins`
--

DROP TABLE IF EXISTS `us_plugins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_plugins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plugin` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `updates` mediumtext DEFAULT NULL,
  `last_check` datetime DEFAULT '2020-01-01 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_plugins`
--

LOCK TABLES `us_plugins` WRITE;
/*!40000 ALTER TABLE `us_plugins` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_plugins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_rate_limit_proxy_settings`
--

DROP TABLE IF EXISTS `us_rate_limit_proxy_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_rate_limit_proxy_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proxy_ip` varchar(45) NOT NULL,
  `header_name` varchar(100) NOT NULL,
  `header` varchar(255) DEFAULT NULL,
  `priority` int(11) DEFAULT 0,
  `enabled` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proxy_ip` (`proxy_ip`),
  KEY `idx_header_name` (`header_name`),
  KEY `idx_enabled_priority` (`enabled`,`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_rate_limit_proxy_settings`
--

LOCK TABLES `us_rate_limit_proxy_settings` WRITE;
/*!40000 ALTER TABLE `us_rate_limit_proxy_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_rate_limit_proxy_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_rate_limits`
--

DROP TABLE IF EXISTS `us_rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier_key` varchar(255) NOT NULL,
  `action` varchar(100) NOT NULL,
  `success` tinyint(1) DEFAULT 0,
  `attempt_time` timestamp NULL DEFAULT current_timestamp(),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  PRIMARY KEY (`id`),
  KEY `idx_identifier_action` (`identifier_key`,`action`),
  KEY `idx_attempt_time` (`attempt_time`),
  KEY `idx_cleanup` (`attempt_time`,`success`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_rate_limits`
--

LOCK TABLES `us_rate_limits` WRITE;
/*!40000 ALTER TABLE `us_rate_limits` DISABLE KEYS */;
INSERT INTO `us_rate_limits` VALUES (1,'1f0cf1c171680908fa6e6e85d8c5f27539579793c262a34f64750a1e38d3cfc5','login_attempt',1,'2026-05-19 00:31:08','{\"method\":\"standard_login\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/148.0.0.0 Safari\\/537.36\"}'),(2,'d2a9b0605fea45063c1d3f33d6c4b1a1898b0390bf91daeec07fe100a85dc6d6','login_attempt',1,'2026-05-19 00:31:08','{\"method\":\"standard_login\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/148.0.0.0 Safari\\/537.36\"}'),(3,'1f0cf1c171680908fa6e6e85d8c5f27539579793c262a34f64750a1e38d3cfc5','login_attempt',1,'2026-05-19 01:17:00','{\"method\":\"standard_login\",\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/148.0.0.0 Mobile Safari\\/537.36\"}'),(4,'d2a9b0605fea45063c1d3f33d6c4b1a1898b0390bf91daeec07fe100a85dc6d6','login_attempt',1,'2026-05-19 01:17:00','{\"method\":\"standard_login\",\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/148.0.0.0 Mobile Safari\\/537.36\"}'),(5,'3cce07c0c978618141ae26bff89417da33f0d6ef29531e23e89391307d5749c7','login_attempt',1,'2026-05-19 01:17:00','{\"method\":\"standard_login\",\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/148.0.0.0 Mobile Safari\\/537.36\"}'),(6,'1f0cf1c171680908fa6e6e85d8c5f27539579793c262a34f64750a1e38d3cfc5','login_attempt',1,'2026-05-19 01:30:09','{\"method\":\"standard_login\",\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/148.0.0.0 Mobile Safari\\/537.36\"}'),(7,'d2a9b0605fea45063c1d3f33d6c4b1a1898b0390bf91daeec07fe100a85dc6d6','login_attempt',1,'2026-05-19 01:30:09','{\"method\":\"standard_login\",\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/148.0.0.0 Mobile Safari\\/537.36\"}'),(8,'3cce07c0c978618141ae26bff89417da33f0d6ef29531e23e89391307d5749c7','login_attempt',1,'2026-05-19 01:30:09','{\"method\":\"standard_login\",\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/148.0.0.0 Mobile Safari\\/537.36\"}'),(9,'8aa3cef597a2abbb293e016425ccc4386bda317a7df961c91072bb2374645d9b','login_attempt',1,'2026-05-19 01:54:17','{\"method\":\"standard_login\",\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/148.0.0.0 Mobile Safari\\/537.36\"}'),(10,'fe52f9156381ee75d0d9f842c079c4a37725a5763d61f7c01f57d0826494e27f','login_attempt',1,'2026-05-19 01:54:17','{\"method\":\"standard_login\",\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/148.0.0.0 Mobile Safari\\/537.36\"}'),(11,'e89c5808a3e7a42d1e68678c889b8d6b502adb782c83556fdadf3d06d575ae17','login_attempt',1,'2026-05-19 01:54:17','{\"method\":\"standard_login\",\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/148.0.0.0 Mobile Safari\\/537.36\"}'),(12,'1f0cf1c171680908fa6e6e85d8c5f27539579793c262a34f64750a1e38d3cfc5','login_attempt',1,'2026-05-19 02:11:04','{\"method\":\"standard_login\",\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/148.0.0.0 Mobile Safari\\/537.36\"}'),(13,'d2a9b0605fea45063c1d3f33d6c4b1a1898b0390bf91daeec07fe100a85dc6d6','login_attempt',1,'2026-05-19 02:11:04','{\"method\":\"standard_login\",\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/148.0.0.0 Mobile Safari\\/537.36\"}'),(14,'3cce07c0c978618141ae26bff89417da33f0d6ef29531e23e89391307d5749c7','login_attempt',1,'2026-05-19 02:11:04','{\"method\":\"standard_login\",\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/148.0.0.0 Mobile Safari\\/537.36\"}');
/*!40000 ALTER TABLE `us_rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_saas_levels`
--

DROP TABLE IF EXISTS `us_saas_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_saas_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` varchar(255) NOT NULL,
  `users` int(11) NOT NULL,
  `details` mediumtext NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_saas_levels`
--

LOCK TABLES `us_saas_levels` WRITE;
/*!40000 ALTER TABLE `us_saas_levels` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_saas_levels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_saas_orgs`
--

DROP TABLE IF EXISTS `us_saas_orgs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_saas_orgs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org` varchar(255) NOT NULL,
  `owner` int(11) NOT NULL,
  `level` int(11) NOT NULL,
  `active` int(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_saas_orgs`
--

LOCK TABLES `us_saas_orgs` WRITE;
/*!40000 ALTER TABLE `us_saas_orgs` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_saas_orgs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_totp_secrets`
--

DROP TABLE IF EXISTS `us_totp_secrets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_totp_secrets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `secret_enc` varchar(255) NOT NULL,
  `backup_codes_h` text DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_totp_secrets`
--

LOCK TABLES `us_totp_secrets` WRITE;
/*!40000 ALTER TABLE `us_totp_secrets` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_totp_secrets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_user_sessions`
--

DROP TABLE IF EXISTS `us_user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_user_sessions` (
  `kUserSessionID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `fkUserID` int(11) unsigned NOT NULL,
  `UserFingerprint` varchar(255) NOT NULL,
  `UserSessionIP` varchar(255) NOT NULL,
  `UserSessionOS` varchar(255) NOT NULL,
  `UserSessionBrowser` varchar(255) NOT NULL,
  `UserSessionStarted` datetime NOT NULL,
  `UserSessionLastUsed` datetime DEFAULT NULL,
  `UserSessionLastPage` varchar(255) NOT NULL,
  `UserSessionEnded` tinyint(1) NOT NULL DEFAULT 0,
  `UserSessionEnded_Time` datetime DEFAULT NULL,
  PRIMARY KEY (`kUserSessionID`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_user_sessions`
--

LOCK TABLES `us_user_sessions` WRITE;
/*!40000 ALTER TABLE `us_user_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `us_user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `us_versions`
--

DROP TABLE IF EXISTS `us_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `us_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `release_version` varchar(20) DEFAULT NULL,
  `bleeding_edge` varchar(20) DEFAULT NULL,
  `experimental` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `us_versions`
--

LOCK TABLES `us_versions` WRITE;
/*!40000 ALTER TABLE `us_versions` DISABLE KEYS */;
INSERT INTO `us_versions` VALUES (1,'6.0.8','6.0.8','6.0.8');
/*!40000 ALTER TABLE `us_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_permission_matches`
--

DROP TABLE IF EXISTS `user_permission_matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_permission_matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_permission_matches`
--

LOCK TABLES `user_permission_matches` WRITE;
/*!40000 ALTER TABLE `user_permission_matches` DISABLE KEYS */;
INSERT INTO `user_permission_matches` VALUES (100,1,1),(101,1,2),(111,3,1),(112,3,2),(113,4,1),(114,4,3),(115,3,3);
/*!40000 ALTER TABLE `user_permission_matches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permissions` tinyint(1) NOT NULL,
  `email` varchar(155) NOT NULL,
  `email_new` varchar(155) DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `pin` varchar(255) DEFAULT NULL,
  `fname` varchar(255) DEFAULT NULL,
  `lname` varchar(255) DEFAULT NULL,
  `language` varchar(15) DEFAULT 'en-US',
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `vericode` text DEFAULT NULL,
  `vericode_expiry` datetime DEFAULT NULL,
  `oauth_provider` text DEFAULT NULL,
  `oauth_uid` text DEFAULT NULL,
  `gender` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `locale` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `gpluslink` text DEFAULT NULL,
  `account_owner` tinyint(4) NOT NULL DEFAULT 1,
  `account_id` int(11) NOT NULL DEFAULT 0,
  `account_mgr` int(11) NOT NULL DEFAULT 0,
  `fb_uid` text DEFAULT NULL,
  `picture` text DEFAULT NULL,
  `created` datetime NOT NULL,
  `protected` tinyint(1) NOT NULL DEFAULT 0,
  `msg_exempt` tinyint(1) NOT NULL DEFAULT 0,
  `dev_user` tinyint(1) NOT NULL DEFAULT 0,
  `msg_notification` tinyint(1) NOT NULL DEFAULT 1,
  `cloak_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `oauth_tos_accepted` tinyint(1) DEFAULT NULL,
  `un_changed` tinyint(1) NOT NULL DEFAULT 0,
  `force_pr` tinyint(1) NOT NULL DEFAULT 0,
  `logins` int(11) unsigned NOT NULL DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `join_date` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `passkey_enabled` tinyint(1) DEFAULT 0,
  `totp_enabled` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `EMAIL` (`email`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,'lbergey01@gmail.com',NULL,'admin','$2y$14$j7RjVnX5gMCtctk5phDsp.L36EdTJQezEx7Jo5y5TCH5kIIP8/wZq',NULL,'Lowell','Bergey','en-US',1,'s2GiSN9THd7V0Lb','2022-11-25 05:32:17','','','','','',1,0,0,'','','0000-00-00 00:00:00',1,1,0,1,1,NULL,0,0,4,'2026-05-18 22:11:04','2026-05-19 02:30:43','2025-04-12 00:00:00',1,0,0),(3,1,'gbergey90@gmail.com',NULL,'gbergey','$2y$13$RplL9hy5/IZfIjVQkGyZ2eyuZMHn9ithmnALKKrcapuIFytuhdxxm',NULL,'Gloria','Bergey','en-US',1,'29a7a9ddcf72271ee74df61ee5b35300630a1f265dc3bbb700f2a961a31374f9','2026-05-18 21:51:17',NULL,NULL,NULL,NULL,NULL,1,0,0,NULL,NULL,'0000-00-00 00:00:00',0,0,0,1,0,1,0,0,1,'2026-05-18 21:54:17','2026-05-18 21:35:57','2026-05-18 00:00:00',1,0,0),(4,1,'phyllisleatherman@yahoo.com',NULL,'pleatherman','$2y$13$B14oa7K5IFpRCpVt5K99ReNIuLoqT7DMCtYpi4YYbSi13nDdgoDwS',NULL,'Phyllis','Leatherman','en-US',1,'fe57d4b40d2923237c6b54fdf23f91d0a6bbe4d62353a86c0828999a05193c5f','2026-05-18 21:53:20',NULL,NULL,NULL,NULL,NULL,1,0,0,NULL,NULL,'0000-00-00 00:00:00',0,0,0,1,0,1,0,0,0,NULL,'2026-05-18 21:38:13','2026-05-18 00:00:00',1,0,0);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users_online`
--

DROP TABLE IF EXISTS `users_online`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_online` (
  `id` int(11) NOT NULL,
  `ip` varchar(15) NOT NULL,
  `timestamp` varchar(15) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users_online`
--

LOCK TABLES `users_online` WRITE;
/*!40000 ALTER TABLE `users_online` DISABLE KEYS */;
/*!40000 ALTER TABLE `users_online` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users_session`
--

DROP TABLE IF EXISTS `users_session`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_session` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `hash` varchar(255) NOT NULL,
  `uagent` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users_session`
--

LOCK TABLES `users_session` WRITE;
/*!40000 ALTER TABLE `users_session` DISABLE KEYS */;
/*!40000 ALTER TABLE `users_session` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'caregivers'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-19 10:28:29
