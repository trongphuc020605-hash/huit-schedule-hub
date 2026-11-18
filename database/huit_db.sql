-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: huit_db
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `huit_db`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `huit_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `huit_db`;

--
-- Table structure for table `branch_travel_times`
--

DROP TABLE IF EXISTS `branch_travel_times`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `branch_travel_times` (
  `from_branch_id` int unsigned NOT NULL,
  `to_branch_id` int unsigned NOT NULL,
  `travel_minutes` smallint unsigned NOT NULL,
  `distance_km` decimal(4,1) NOT NULL,
  PRIMARY KEY (`from_branch_id`,`to_branch_id`),
  KEY `fk_travel_to` (`to_branch_id`),
  CONSTRAINT `fk_travel_from` FOREIGN KEY (`from_branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_travel_to` FOREIGN KEY (`to_branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branch_travel_times`
--

LOCK TABLES `branch_travel_times` WRITE;
/*!40000 ALTER TABLE `branch_travel_times` DISABLE KEYS */;
INSERT INTO `branch_travel_times` VALUES (1,1,0,0.0),(1,2,15,2.6),(1,3,6,0.8),(1,4,35,9.5),(2,1,15,2.6),(2,2,0,0.0),(2,3,14,2.3),(2,4,38,10.4),(3,1,6,0.8),(3,2,14,2.3),(3,3,0,0.0),(3,4,40,11.0),(4,1,35,9.5),(4,2,38,10.4),(4,3,40,11.0),(4,4,0,0.0);
/*!40000 ALTER TABLE `branch_travel_times` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `branches` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `branch_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `campus_zone` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color_hex` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#1D4ED8',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branches_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (1,'Cơ sở 1 — Trụ sở chính Lê Trọng Tấn','140 Lê Trọng Tấn, P. Tây Thạnh, Q. Tân Phú, TP.HCM','CS1','Tân Phú','#1D4ED8','2026-11-18 01:01:40'),(2,'Cơ sở 2 — Tân Kỳ Tân Quý','93 Tân Kỳ Tân Quý, P. Tân Sơn Nhì, Q. Tân Phú, TP.HCM','CS2','Tân Phú','#059669','2026-11-18 01:01:40'),(3,'Cơ sở 3 — Trung tâm Thực hành Chế Lan Viên','31 Chế Lan Viên, P. Tây Thạnh, Q. Tân Phú, TP.HCM','CS3','Tân Phú','#D97706','2026-11-18 01:01:40'),(4,'Cơ sở 4 — Trung tâm Liên kết Quận 12','245 Tô Ký, P. Trung Mỹ Tây, Quận 12, TP.HCM','CS4','Quận 12','#7C3AED','2026-11-18 01:01:40');
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedules`
--

DROP TABLE IF EXISTS `schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` int unsigned NOT NULL,
  `branch_id` int unsigned NOT NULL,
  `subject_name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `room` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `day_of_week` tinyint unsigned NOT NULL,
  `shift_or_periods` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_period` tinyint unsigned NOT NULL,
  `end_period` tinyint unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sched_teacher_slot` (`teacher_id`,`day_of_week`,`start_period`,`end_period`,`start_date`,`end_date`),
  KEY `idx_sched_branch_room_slot` (`branch_id`,`room`,`day_of_week`,`start_period`,`end_period`,`start_date`,`end_date`),
  CONSTRAINT `fk_schedules_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_schedules_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_schedules_dates` CHECK ((`end_date` >= `start_date`)),
  CONSTRAINT `chk_schedules_day` CHECK ((`day_of_week` between 2 and 8)),
  CONSTRAINT `chk_schedules_periods` CHECK (((`start_period` between 1 and 15) and (`end_period` between 1 and 15) and (`end_period` >= `start_period`)))
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedules`
--

LOCK TABLES `schedules` WRITE;
/*!40000 ALTER TABLE `schedules` DISABLE KEYS */;
INSERT INTO `schedules` VALUES (1,2,1,'Phát triển phần mềm mã nguồn mở','A301',2,'Ca 1 (Tiết 1-3)',1,3,'2026-11-01','2027-01-31','Lớp 13DHTH01 - Phòng thực hành Web','2026-11-18 01:01:41','2026-11-18 01:01:41'),(2,2,1,'Lập trình Web PHP & MySQL','A302',2,'Ca 2 (Tiết 4-6)',4,6,'2026-11-01','2027-01-31','Lớp 13DHTH02 - Liền ca cùng CS1','2026-11-18 01:01:41','2026-11-18 01:01:41'),(3,2,1,'Xây dựng RESTful API với Laravel','B204',4,'Ca 1 (Tiết 1-3)',1,3,'2026-11-01','2027-01-31','Lớp 13DHTH03','2026-11-18 01:01:41','2026-11-18 01:01:41'),(4,2,3,'Thực hành Git & DevOps Mã nguồn mở','PM05',4,'Ca 3 (Tiết 7-9)',7,9,'2026-11-01','2027-01-31','Thực hành CS3 Chế Lan Viên','2026-11-18 01:01:41','2026-11-18 01:01:41'),(5,2,2,'Đồ án Phát triển phần mềm mã nguồn mở','C102',6,'Ca 1 (Tiết 1-3)',1,3,'2026-11-01','2027-01-31','Hướng dẫn đồ án chuyên ngành','2026-11-18 01:01:41','2026-11-18 01:01:41'),(6,3,1,'Hệ quản trị Cơ sở dữ liệu MySQL','A401',3,'Ca 1 (Tiết 1-3)',1,3,'2026-11-01','2027-01-31','Demo cảnh báo IMPOSSIBLE_TRAVEL #1','2026-11-18 01:01:41','2026-11-18 01:01:41'),(7,3,4,'Cơ sở dữ liệu NoSQL & Redis','D101',3,'Ca 2 (Tiết 4-6)',4,6,'2026-11-01','2027-01-31','Cần 35p CS1->CS4 nhưng chỉ nghỉ 10p','2026-11-18 01:01:41','2026-11-18 01:01:41'),(8,4,3,'Học máy (Machine Learning)','PM02',5,'Ca 3 (Tiết 7-9)',7,9,'2026-11-01','2027-01-31','Demo cảnh báo IMPOSSIBLE_TRAVEL #2','2026-11-18 01:01:41','2026-11-18 01:01:41'),(9,4,4,'Khoa học dữ liệu với Python','D205',5,'Ca 4 (Tiết 10-12)',10,12,'2026-11-01','2027-01-31','Cần 40p CS3->CS4 nhưng chỉ nghỉ 10p','2026-11-18 01:01:41','2026-11-18 01:01:41'),(10,5,1,'Lập trình hướng đối tượng Java','B305',2,'Ca 1 (Tiết 1-3)',1,3,'2026-11-01','2027-01-31','Demo cảnh báo DEAD_GAP #1','2026-11-18 01:01:41','2026-11-18 01:01:41'),(11,5,1,'Công nghệ phần mềm nâng cao','B306',2,'Ca 4 (Tiết 10-12)',10,12,'2026-11-01','2027-01-31','Trống 6 tiết giữa Ca 1 và Ca 4','2026-11-18 01:01:41','2026-11-18 01:01:41'),(12,6,2,'Mạng máy tính căn bản','C201',4,'Ca 1 (Tiết 1-3)',1,3,'2026-11-01','2027-01-31','Demo cảnh báo DEAD_GAP #2','2026-11-18 01:01:41','2026-11-18 01:01:41'),(13,6,2,'An toàn thông tin & Bảo mật Web','C203',4,'Ca 5 (Tiết 13-15)',13,15,'2026-11-01','2027-01-31','Trống 9 tiết giữa Ca 1 và Ca 5','2026-11-18 01:01:41','2026-11-18 01:01:41'),(14,4,2,'Hệ quản trị Cơ sở dữ liệu MySQL','C202',7,'Ca 3 (Tiết 7-9)',7,9,'2026-11-01','2027-01-31','Lớp 13DHTH03','2026-11-18 01:01:41','2026-11-18 01:01:41'),(15,5,4,'Học máy (Machine Learning)','D104',6,'Ca 1 (Tiết 1-3)',1,3,'2026-11-01','2027-01-31','Lớp 13DHTH04','2026-11-18 01:01:41','2026-11-18 01:01:41'),(16,6,4,'Lập trình hướng đối tượng Java','D301',6,'Ca 2 (Tiết 4-6)',4,6,'2026-11-01','2027-01-31','Lớp 13DHTH05','2026-11-18 01:01:41','2026-11-18 01:01:41'),(17,7,4,'Mạng máy tính căn bản','D203',3,'Ca 1 (Tiết 1-3)',1,3,'2026-11-01','2027-01-31','Lớp 13DHTH06','2026-11-18 01:01:41','2026-11-18 01:01:41'),(18,8,1,'Phát triển ứng dụng TypeScript','A202',4,'Ca 5 (Tiết 13-15)',13,15,'2026-11-01','2027-01-31','Lớp 13DHTH07','2026-11-18 01:01:41','2026-11-18 01:01:41'),(19,9,4,'Lập trình Web PHP & MySQL','D203',4,'Ca 1 (Tiết 1-3)',1,3,'2026-11-01','2027-01-31','Lớp 13DHTH08','2026-11-18 01:01:41','2026-11-18 01:01:41'),(20,10,4,'Hệ quản trị Cơ sở dữ liệu MySQL','D301',5,'Ca 3 (Tiết 7-9)',7,9,'2026-11-01','2027-01-31','Lớp 13DHTH09','2026-11-18 01:01:41','2026-11-18 01:01:41'),(21,11,2,'Trí tuệ nhân tạo ứng dụng','C301',5,'Ca 3 (Tiết 7-9)',7,9,'2026-11-01','2027-01-31','Lớp 13DHTH10','2026-11-18 01:01:41','2026-11-18 01:01:41'),(22,12,4,'Công nghệ phần mềm nâng cao','D104',5,'Ca 2 (Tiết 4-6)',4,6,'2026-11-01','2027-01-31','Lớp 13DHTH11','2026-11-18 01:01:41','2026-11-18 01:01:41'),(23,13,1,'DevOps, Docker & CI/CD','B404',3,'Ca 5 (Tiết 13-15)',13,15,'2026-11-01','2027-01-31','Lớp 13DHTH12','2026-11-18 01:01:41','2026-11-18 01:01:41'),(24,14,2,'Phát triển ứng dụng TypeScript','C101',7,'Ca 2 (Tiết 4-6)',4,6,'2026-11-01','2027-01-31','Lớp 13DHTH01','2026-11-18 01:01:41','2026-11-18 01:01:41'),(25,15,1,'Lập trình Web PHP & MySQL','B301',2,'Ca 4 (Tiết 10-12)',10,12,'2026-11-01','2027-01-31','Lớp 13DHTH02','2026-11-18 01:01:41','2026-11-18 01:01:41'),(26,16,2,'Hệ quản trị Cơ sở dữ liệu MySQL','C301',5,'Ca 5 (Tiết 13-15)',13,15,'2026-11-01','2027-01-31','Lớp 13DHTH03','2026-11-18 01:01:41','2026-11-18 01:01:41'),(27,3,3,'Lập trình Web PHP & MySQL','PM03',4,'Ca 2 (Tiết 4-6)',4,6,'2026-11-01','2027-01-31','Lớp 13DHTH05','2026-11-18 01:01:41','2026-11-18 01:01:41'),(28,4,4,'Cơ sở dữ liệu NoSQL & Redis','D203',3,'Ca 5 (Tiết 13-15)',13,15,'2026-11-01','2027-01-31','Lớp 13DHTH06','2026-11-18 01:01:41','2026-11-18 01:01:41'),(29,5,4,'Học máy (Machine Learning)','D102',7,'Ca 3 (Tiết 7-9)',7,9,'2026-11-01','2027-01-31','Lớp 13DHTH07','2026-11-18 01:01:41','2026-11-18 01:01:41'),(30,6,1,'Công nghệ phần mềm nâng cao','B205',2,'Ca 4 (Tiết 10-12)',10,12,'2026-11-01','2027-01-31','Lớp 13DHTH08','2026-11-18 01:01:41','2026-11-18 01:01:41'),(31,7,3,'DevOps, Docker & CI/CD','PM01',2,'Ca 4 (Tiết 10-12)',10,12,'2026-11-01','2027-01-31','Lớp 13DHTH09','2026-11-18 01:01:41','2026-11-18 01:01:41'),(32,8,2,'Phát triển ứng dụng TypeScript','C204',3,'Ca 2 (Tiết 4-6)',4,6,'2026-11-01','2027-01-31','Lớp 13DHTH10','2026-11-18 01:01:41','2026-11-18 01:01:41'),(33,9,2,'Lập trình Web PHP & MySQL','C204',3,'Ca 1 (Tiết 1-3)',1,3,'2026-11-01','2027-01-31','Lớp 13DHTH11','2026-11-18 01:01:41','2026-11-18 01:01:41'),(34,10,4,'Kho dữ liệu và Big Data','D203',3,'Ca 3 (Tiết 7-9)',7,9,'2026-11-01','2027-01-31','Lớp 13DHTH12','2026-11-18 01:01:41','2026-11-18 01:01:41'),(35,11,1,'Khoa học dữ liệu với Python','A402',4,'Ca 1 (Tiết 1-3)',1,3,'2026-11-01','2027-01-31','Lớp 13DHTH01','2026-11-18 01:01:41','2026-11-18 01:01:41'),(36,12,1,'Công nghệ phần mềm nâng cao','B404',6,'Ca 3 (Tiết 7-9)',7,9,'2026-11-01','2027-01-31','Lớp 13DHTH02','2026-11-18 01:01:41','2026-11-18 01:01:41'),(37,14,4,'Lập trình Frontend với ReactJS','D301',3,'Ca 1 (Tiết 1-3)',1,3,'2026-11-01','2027-01-31','Lớp 13DHTH04','2026-11-18 01:01:41','2026-11-18 01:01:41'),(38,15,4,'Lập trình Web PHP & MySQL','D102',4,'Ca 3 (Tiết 7-9)',7,9,'2026-11-01','2027-01-31','Lớp 13DHTH05','2026-11-18 01:01:41','2026-11-18 01:01:41'),(39,16,1,'Hệ quản trị Cơ sở dữ liệu MySQL','A402',7,'Ca 4 (Tiết 10-12)',10,12,'2026-11-01','2027-01-31','Lớp 13DHTH06','2026-11-18 01:01:41','2026-11-18 01:01:41'),(40,2,1,'Lập trình Web PHP & MySQL','B301',4,'Ca 5 (Tiết 13-15)',13,15,'2026-11-01','2027-01-31','Lớp 13DHTH07','2026-11-18 01:01:41','2026-11-18 01:01:41'),(41,3,1,'Xây dựng RESTful API với Laravel','A202',7,'Ca 4 (Tiết 10-12)',10,12,'2026-11-01','2027-01-31','Lớp 13DHTH08','2026-11-18 01:01:41','2026-11-18 01:01:41'),(42,5,4,'Trí tuệ nhân tạo ứng dụng','D301',5,'Ca 5 (Tiết 13-15)',13,15,'2026-11-01','2027-01-31','Lớp 13DHTH10','2026-11-18 01:01:41','2026-11-18 01:01:41'),(43,7,4,'Mạng máy tính căn bản','D201',2,'Ca 3 (Tiết 7-9)',7,9,'2026-11-01','2027-01-31','Lớp 13DHTH12','2026-11-18 01:01:41','2026-11-18 01:01:41'),(44,8,2,'Lập trình Frontend với ReactJS','C204',5,'Ca 4 (Tiết 10-12)',10,12,'2026-11-01','2027-01-31','Lớp 13DHTH01','2026-11-18 01:01:41','2026-11-18 01:01:41'),(45,9,2,'Lập trình Web PHP & MySQL','C202',5,'Ca 2 (Tiết 4-6)',4,6,'2026-11-01','2027-01-31','Lớp 13DHTH02','2026-11-18 01:01:41','2026-11-18 01:01:41'),(46,10,1,'Hệ quản trị Cơ sở dữ liệu MySQL','B101',5,'Ca 2 (Tiết 4-6)',4,6,'2026-11-01','2027-01-31','Lớp 13DHTH03','2026-11-18 01:01:41','2026-11-18 01:01:41');
/*!40000 ALTER TABLE `schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_availabilities`
--

DROP TABLE IF EXISTS `teacher_availabilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teacher_availabilities` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` int unsigned NOT NULL,
  `day_of_week` tinyint unsigned NOT NULL,
  `shift_or_periods` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_period` tinyint unsigned NOT NULL,
  `end_period` tinyint unsigned NOT NULL,
  `preferred_branch_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_avail` (`teacher_id`,`day_of_week`,`start_period`,`end_period`),
  KEY `fk_avail_branch` (`preferred_branch_id`),
  CONSTRAINT `fk_avail_branch` FOREIGN KEY (`preferred_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_avail_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_avail_day` CHECK ((`day_of_week` between 2 and 8)),
  CONSTRAINT `chk_avail_periods` CHECK (((`start_period` between 1 and 15) and (`end_period` between 1 and 15) and (`end_period` >= `start_period`)))
) ENGINE=InnoDB AUTO_INCREMENT=153 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_availabilities`
--

LOCK TABLES `teacher_availabilities` WRITE;
/*!40000 ALTER TABLE `teacher_availabilities` DISABLE KEYS */;
INSERT INTO `teacher_availabilities` VALUES (1,2,2,'Ca 1 (Tiết 1-3)',1,3,1,'2026-11-18 01:01:40'),(2,2,2,'Ca 2 (Tiết 4-6)',4,6,1,'2026-11-18 01:01:40'),(3,2,2,'Ca 3 (Tiết 7-9)',7,9,1,'2026-11-18 01:01:40'),(4,2,4,'Ca 1 (Tiết 1-3)',1,3,1,'2026-11-18 01:01:40'),(5,2,4,'Ca 2 (Tiết 4-6)',4,6,1,'2026-11-18 01:01:40'),(6,2,5,'Ca 1 (Tiết 1-3)',1,3,1,'2026-11-18 01:01:40'),(7,2,5,'Ca 2 (Tiết 4-6)',4,6,1,'2026-11-18 01:01:40'),(8,2,6,'Ca 1 (Tiết 1-3)',1,3,1,'2026-11-18 01:01:40'),(9,2,6,'Ca 3 (Tiết 7-9)',7,9,1,'2026-11-18 01:01:40'),(10,2,7,'Ca 1 (Tiết 1-3)',1,3,1,'2026-11-18 01:01:40'),(11,3,4,'Ca 4 (Tiết 10-12)',10,12,NULL,'2026-11-18 01:01:40'),(12,3,5,'Ca 2 (Tiết 4-6)',4,6,2,'2026-11-18 01:01:40'),(13,3,3,'Ca 1 (Tiết 1-3)',1,3,2,'2026-11-18 01:01:40'),(14,3,4,'Ca 1 (Tiết 1-3)',1,3,NULL,'2026-11-18 01:01:40'),(15,3,2,'Ca 5 (Tiết 13-15)',13,15,2,'2026-11-18 01:01:40'),(16,3,6,'Ca 3 (Tiết 7-9)',7,9,2,'2026-11-18 01:01:40'),(17,3,4,'Ca 3 (Tiết 7-9)',7,9,NULL,'2026-11-18 01:01:40'),(18,3,2,'Ca 1 (Tiết 1-3)',1,3,2,'2026-11-18 01:01:40'),(19,3,4,'Ca 5 (Tiết 13-15)',13,15,2,'2026-11-18 01:01:40'),(20,4,3,'Ca 3 (Tiết 7-9)',7,9,NULL,'2026-11-18 01:01:40'),(21,4,7,'Ca 3 (Tiết 7-9)',7,9,3,'2026-11-18 01:01:40'),(22,4,6,'Ca 4 (Tiết 10-12)',10,12,3,'2026-11-18 01:01:40'),(23,4,2,'Ca 4 (Tiết 10-12)',10,12,NULL,'2026-11-18 01:01:40'),(24,4,7,'Ca 1 (Tiết 1-3)',1,3,3,'2026-11-18 01:01:40'),(25,4,4,'Ca 4 (Tiết 10-12)',10,12,3,'2026-11-18 01:01:40'),(26,4,6,'Ca 1 (Tiết 1-3)',1,3,NULL,'2026-11-18 01:01:40'),(27,4,4,'Ca 3 (Tiết 7-9)',7,9,3,'2026-11-18 01:01:40'),(28,4,4,'Ca 2 (Tiết 4-6)',4,6,3,'2026-11-18 01:01:40'),(29,4,5,'Ca 4 (Tiết 10-12)',10,12,NULL,'2026-11-18 01:01:40'),(30,5,6,'Ca 1 (Tiết 1-3)',1,3,NULL,'2026-11-18 01:01:40'),(31,5,7,'Ca 4 (Tiết 10-12)',10,12,4,'2026-11-18 01:01:40'),(32,5,5,'Ca 1 (Tiết 1-3)',1,3,4,'2026-11-18 01:01:40'),(33,5,4,'Ca 4 (Tiết 10-12)',10,12,NULL,'2026-11-18 01:01:40'),(34,5,7,'Ca 2 (Tiết 4-6)',4,6,4,'2026-11-18 01:01:40'),(35,5,7,'Ca 1 (Tiết 1-3)',1,3,4,'2026-11-18 01:01:40'),(36,5,2,'Ca 4 (Tiết 10-12)',10,12,NULL,'2026-11-18 01:01:40'),(37,5,6,'Ca 2 (Tiết 4-6)',4,6,4,'2026-11-18 01:01:40'),(38,5,7,'Ca 3 (Tiết 7-9)',7,9,4,'2026-11-18 01:01:40'),(39,5,7,'Ca 5 (Tiết 13-15)',13,15,NULL,'2026-11-18 01:01:40'),(40,5,3,'Ca 4 (Tiết 10-12)',10,12,4,'2026-11-18 01:01:40'),(41,6,6,'Ca 5 (Tiết 13-15)',13,15,NULL,'2026-11-18 01:01:40'),(42,6,3,'Ca 5 (Tiết 13-15)',13,15,1,'2026-11-18 01:01:40'),(43,6,6,'Ca 1 (Tiết 1-3)',1,3,1,'2026-11-18 01:01:40'),(44,6,6,'Ca 3 (Tiết 7-9)',7,9,NULL,'2026-11-18 01:01:40'),(45,6,7,'Ca 1 (Tiết 1-3)',1,3,1,'2026-11-18 01:01:40'),(46,6,3,'Ca 1 (Tiết 1-3)',1,3,1,'2026-11-18 01:01:40'),(47,6,7,'Ca 2 (Tiết 4-6)',4,6,NULL,'2026-11-18 01:01:40'),(48,6,7,'Ca 3 (Tiết 7-9)',7,9,1,'2026-11-18 01:01:40'),(49,6,5,'Ca 3 (Tiết 7-9)',7,9,1,'2026-11-18 01:01:40'),(50,6,4,'Ca 5 (Tiết 13-15)',13,15,NULL,'2026-11-18 01:01:40'),(51,6,2,'Ca 3 (Tiết 7-9)',7,9,1,'2026-11-18 01:01:40'),(52,6,3,'Ca 3 (Tiết 7-9)',7,9,1,'2026-11-18 01:01:40'),(53,7,7,'Ca 5 (Tiết 13-15)',13,15,NULL,'2026-11-18 01:01:40'),(54,7,3,'Ca 4 (Tiết 10-12)',10,12,2,'2026-11-18 01:01:40'),(55,7,6,'Ca 5 (Tiết 13-15)',13,15,2,'2026-11-18 01:01:40'),(56,7,2,'Ca 1 (Tiết 1-3)',1,3,NULL,'2026-11-18 01:01:40'),(57,7,4,'Ca 5 (Tiết 13-15)',13,15,2,'2026-11-18 01:01:40'),(58,7,6,'Ca 2 (Tiết 4-6)',4,6,2,'2026-11-18 01:01:40'),(59,7,3,'Ca 1 (Tiết 1-3)',1,3,NULL,'2026-11-18 01:01:40'),(60,7,3,'Ca 2 (Tiết 4-6)',4,6,2,'2026-11-18 01:01:40'),(61,8,4,'Ca 4 (Tiết 10-12)',10,12,NULL,'2026-11-18 01:01:40'),(62,8,3,'Ca 2 (Tiết 4-6)',4,6,3,'2026-11-18 01:01:40'),(63,8,4,'Ca 1 (Tiết 1-3)',1,3,3,'2026-11-18 01:01:40'),(64,8,3,'Ca 4 (Tiết 10-12)',10,12,NULL,'2026-11-18 01:01:40'),(65,8,5,'Ca 4 (Tiết 10-12)',10,12,3,'2026-11-18 01:01:40'),(66,8,6,'Ca 1 (Tiết 1-3)',1,3,3,'2026-11-18 01:01:40'),(67,8,3,'Ca 3 (Tiết 7-9)',7,9,NULL,'2026-11-18 01:01:40'),(68,8,5,'Ca 1 (Tiết 1-3)',1,3,3,'2026-11-18 01:01:40'),(69,8,7,'Ca 3 (Tiết 7-9)',7,9,3,'2026-11-18 01:01:40'),(70,9,5,'Ca 3 (Tiết 7-9)',7,9,NULL,'2026-11-18 01:01:40'),(71,9,2,'Ca 2 (Tiết 4-6)',4,6,4,'2026-11-18 01:01:40'),(72,9,3,'Ca 1 (Tiết 1-3)',1,3,4,'2026-11-18 01:01:40'),(73,9,7,'Ca 3 (Tiết 7-9)',7,9,NULL,'2026-11-18 01:01:40'),(74,9,4,'Ca 3 (Tiết 7-9)',7,9,4,'2026-11-18 01:01:40'),(75,9,4,'Ca 4 (Tiết 10-12)',10,12,4,'2026-11-18 01:01:40'),(76,9,2,'Ca 5 (Tiết 13-15)',13,15,NULL,'2026-11-18 01:01:40'),(77,9,6,'Ca 2 (Tiết 4-6)',4,6,4,'2026-11-18 01:01:40'),(78,9,6,'Ca 5 (Tiết 13-15)',13,15,4,'2026-11-18 01:01:40'),(79,9,4,'Ca 2 (Tiết 4-6)',4,6,NULL,'2026-11-18 01:01:40'),(80,10,4,'Ca 4 (Tiết 10-12)',10,12,NULL,'2026-11-18 01:01:40'),(81,10,5,'Ca 1 (Tiết 1-3)',1,3,1,'2026-11-18 01:01:40'),(82,10,6,'Ca 2 (Tiết 4-6)',4,6,1,'2026-11-18 01:01:40'),(83,10,2,'Ca 2 (Tiết 4-6)',4,6,NULL,'2026-11-18 01:01:40'),(84,10,5,'Ca 4 (Tiết 10-12)',10,12,1,'2026-11-18 01:01:40'),(85,10,7,'Ca 1 (Tiết 1-3)',1,3,1,'2026-11-18 01:01:40'),(86,10,4,'Ca 3 (Tiết 7-9)',7,9,NULL,'2026-11-18 01:01:40'),(87,10,3,'Ca 2 (Tiết 4-6)',4,6,1,'2026-11-18 01:01:40'),(88,10,2,'Ca 4 (Tiết 10-12)',10,12,1,'2026-11-18 01:01:40'),(89,10,2,'Ca 1 (Tiết 1-3)',1,3,NULL,'2026-11-18 01:01:40'),(90,10,5,'Ca 2 (Tiết 4-6)',4,6,1,'2026-11-18 01:01:40'),(91,11,5,'Ca 5 (Tiết 13-15)',13,15,NULL,'2026-11-18 01:01:40'),(92,11,5,'Ca 1 (Tiết 1-3)',1,3,2,'2026-11-18 01:01:40'),(93,11,2,'Ca 1 (Tiết 1-3)',1,3,2,'2026-11-18 01:01:40'),(94,11,6,'Ca 2 (Tiết 4-6)',4,6,NULL,'2026-11-18 01:01:40'),(95,11,4,'Ca 3 (Tiết 7-9)',7,9,2,'2026-11-18 01:01:40'),(96,11,4,'Ca 4 (Tiết 10-12)',10,12,2,'2026-11-18 01:01:40'),(97,11,5,'Ca 4 (Tiết 10-12)',10,12,NULL,'2026-11-18 01:01:40'),(98,11,2,'Ca 5 (Tiết 13-15)',13,15,2,'2026-11-18 01:01:40'),(99,11,6,'Ca 5 (Tiết 13-15)',13,15,2,'2026-11-18 01:01:40'),(100,11,5,'Ca 3 (Tiết 7-9)',7,9,NULL,'2026-11-18 01:01:40'),(101,11,3,'Ca 3 (Tiết 7-9)',7,9,2,'2026-11-18 01:01:40'),(102,11,7,'Ca 4 (Tiết 10-12)',10,12,2,'2026-11-18 01:01:40'),(103,12,2,'Ca 2 (Tiết 4-6)',4,6,NULL,'2026-11-18 01:01:40'),(104,12,6,'Ca 4 (Tiết 10-12)',10,12,3,'2026-11-18 01:01:40'),(105,12,2,'Ca 4 (Tiết 10-12)',10,12,3,'2026-11-18 01:01:40'),(106,12,5,'Ca 4 (Tiết 10-12)',10,12,NULL,'2026-11-18 01:01:40'),(107,12,4,'Ca 1 (Tiết 1-3)',1,3,3,'2026-11-18 01:01:40'),(108,12,4,'Ca 2 (Tiết 4-6)',4,6,3,'2026-11-18 01:01:40'),(109,12,7,'Ca 3 (Tiết 7-9)',7,9,NULL,'2026-11-18 01:01:40'),(110,12,5,'Ca 2 (Tiết 4-6)',4,6,3,'2026-11-18 01:01:40'),(111,13,5,'Ca 1 (Tiết 1-3)',1,3,NULL,'2026-11-18 01:01:40'),(112,13,4,'Ca 3 (Tiết 7-9)',7,9,4,'2026-11-18 01:01:40'),(113,13,5,'Ca 4 (Tiết 10-12)',10,12,4,'2026-11-18 01:01:40'),(114,13,7,'Ca 5 (Tiết 13-15)',13,15,NULL,'2026-11-18 01:01:40'),(115,13,3,'Ca 2 (Tiết 4-6)',4,6,4,'2026-11-18 01:01:40'),(116,13,3,'Ca 1 (Tiết 1-3)',1,3,4,'2026-11-18 01:01:40'),(117,13,4,'Ca 1 (Tiết 1-3)',1,3,NULL,'2026-11-18 01:01:40'),(118,13,2,'Ca 5 (Tiết 13-15)',13,15,4,'2026-11-18 01:01:40'),(119,13,6,'Ca 5 (Tiết 13-15)',13,15,4,'2026-11-18 01:01:40'),(120,14,2,'Ca 1 (Tiết 1-3)',1,3,NULL,'2026-11-18 01:01:41'),(121,14,7,'Ca 3 (Tiết 7-9)',7,9,1,'2026-11-18 01:01:41'),(122,14,6,'Ca 4 (Tiết 10-12)',10,12,1,'2026-11-18 01:01:41'),(123,14,5,'Ca 5 (Tiết 13-15)',13,15,NULL,'2026-11-18 01:01:41'),(124,14,4,'Ca 1 (Tiết 1-3)',1,3,1,'2026-11-18 01:01:41'),(125,14,7,'Ca 2 (Tiết 4-6)',4,6,1,'2026-11-18 01:01:41'),(126,14,3,'Ca 5 (Tiết 13-15)',13,15,NULL,'2026-11-18 01:01:41'),(127,14,4,'Ca 5 (Tiết 13-15)',13,15,1,'2026-11-18 01:01:41'),(128,14,3,'Ca 1 (Tiết 1-3)',1,3,1,'2026-11-18 01:01:41'),(129,14,5,'Ca 2 (Tiết 4-6)',4,6,NULL,'2026-11-18 01:01:41'),(130,15,5,'Ca 1 (Tiết 1-3)',1,3,NULL,'2026-11-18 01:01:41'),(131,15,3,'Ca 4 (Tiết 10-12)',10,12,2,'2026-11-18 01:01:41'),(132,15,3,'Ca 3 (Tiết 7-9)',7,9,2,'2026-11-18 01:01:41'),(133,15,4,'Ca 3 (Tiết 7-9)',7,9,NULL,'2026-11-18 01:01:41'),(134,15,2,'Ca 4 (Tiết 10-12)',10,12,2,'2026-11-18 01:01:41'),(135,15,6,'Ca 5 (Tiết 13-15)',13,15,2,'2026-11-18 01:01:41'),(136,15,4,'Ca 2 (Tiết 4-6)',4,6,NULL,'2026-11-18 01:01:41'),(137,15,7,'Ca 1 (Tiết 1-3)',1,3,2,'2026-11-18 01:01:41'),(138,15,2,'Ca 1 (Tiết 1-3)',1,3,2,'2026-11-18 01:01:41'),(139,15,4,'Ca 1 (Tiết 1-3)',1,3,NULL,'2026-11-18 01:01:41'),(140,15,5,'Ca 2 (Tiết 4-6)',4,6,2,'2026-11-18 01:01:41'),(141,16,4,'Ca 1 (Tiết 1-3)',1,3,NULL,'2026-11-18 01:01:41'),(142,16,5,'Ca 5 (Tiết 13-15)',13,15,3,'2026-11-18 01:01:41'),(143,16,2,'Ca 3 (Tiết 7-9)',7,9,3,'2026-11-18 01:01:41'),(144,16,7,'Ca 5 (Tiết 13-15)',13,15,NULL,'2026-11-18 01:01:41'),(145,16,4,'Ca 4 (Tiết 10-12)',10,12,3,'2026-11-18 01:01:41'),(146,16,5,'Ca 1 (Tiết 1-3)',1,3,3,'2026-11-18 01:01:41'),(147,16,3,'Ca 2 (Tiết 4-6)',4,6,NULL,'2026-11-18 01:01:41'),(148,16,4,'Ca 5 (Tiết 13-15)',13,15,3,'2026-11-18 01:01:41'),(149,16,6,'Ca 2 (Tiết 4-6)',4,6,3,'2026-11-18 01:01:41'),(150,16,2,'Ca 2 (Tiết 4-6)',4,6,NULL,'2026-11-18 01:01:41'),(151,16,6,'Ca 5 (Tiết 13-15)',13,15,3,'2026-11-18 01:01:41'),(152,16,3,'Ca 4 (Tiết 10-12)',10,12,3,'2026-11-18 01:01:41');
/*!40000 ALTER TABLE `teacher_availabilities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teachers`
--

DROP TABLE IF EXISTS `teachers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teachers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expertise` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','teacher') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'teacher',
  `portal_token` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `max_periods_per_week` tinyint unsigned NOT NULL DEFAULT '18',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teachers_email` (`email`),
  UNIQUE KEY `uq_teachers_portal_token` (`portal_token`),
  KEY `idx_teachers_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teachers`
--

LOCK TABLES `teachers` WRITE;
/*!40000 ALTER TABLE `teachers` DISABLE KEYS */;
INSERT INTO `teachers` VALUES (1,'TS. Trần Quốc Bảo (Phòng Đào tạo)','admin@huit.edu.vn','0903140140','Quản trị hệ thống đào tạo, Công nghệ phần mềm','$2y$10$Z7viy6UPN/sD8SzzYhOVmerd51EIDmJ/UQn5x2wyIst2L1Rk1GJRu','admin','0b6fc515cb0507ea56499118fe3176a06284503dd49fbb4b85651136d6e01e73',12,'2026-11-18 01:01:40'),(2,'ThS. Nguyễn Minh Quân','giangvien@huit.edu.vn','0908123456','Phát triển phần mềm mã nguồn mở, PHP, Web, MySQL, Laravel','$2y$10$0xaWsl6QXYVPSAHYYb/R0.0DrDwcjPE0kni0VZP7om4mbzow.QVCq','teacher','fdb414c31ad4ae40955a09077d27e2f6ddfbb28c3194e84df29367ca6dfd4c29',18,'2026-11-18 01:01:40'),(3,'TS. Bạc Thúy Châu','gv01.xly@huit.edu.vn','0999951366','Phát triển phần mềm mã nguồn mở, PHP, Laravel, Git, REST API','$2y$10$0xaWsl6QXYVPSAHYYb/R0.0DrDwcjPE0kni0VZP7om4mbzow.QVCq','teacher','5645177792ff122dfd21808b1ec576e65768ff13ec4c6ab5322d13ff5b3b4ff8',15,'2026-11-18 01:01:40'),(4,'ThS. Khâu Khai Lễ','gv02.luhop@huit.edu.vn','0913020754','Cơ sở dữ liệu, MySQL, PostgreSQL, NoSQL, MongoDB, Redis, Big Data','$2y$10$0xaWsl6QXYVPSAHYYb/R0.0DrDwcjPE0kni0VZP7om4mbzow.QVCq','teacher','5347ec2eda9c7a91ab3692afad2d3b2efd1f5b2791b9839caa68c4cdf1a61b2c',18,'2026-11-18 01:01:40'),(5,'ThS. Dư Thông Duyên','gv03.duc65@huit.edu.vn','0935808421','Trí tuệ nhân tạo, Machine Learning, Deep Learning, Python, Data Science','$2y$10$0xaWsl6QXYVPSAHYYb/R0.0DrDwcjPE0kni0VZP7om4mbzow.QVCq','teacher','1ec3d28edd20e3d7ba0aa32f0a4e32fc6128023969f753f25b01754e1a054b80',18,'2026-11-18 01:01:40'),(6,'GVC. ThS. Khu Trầm Hà','gv04.thanphong@huit.edu.vn','0924630321','Công nghệ phần mềm, OOP, Java, Spring Boot, C#, Kiểm thử phần mềm','$2y$10$0xaWsl6QXYVPSAHYYb/R0.0DrDwcjPE0kni0VZP7om4mbzow.QVCq','teacher','0b0c9e4f8070e292c0a39976fccc42871e3f405e7be29e8a9f055cbbcbfd1260',15,'2026-11-18 01:01:40'),(7,'TS. Chu Giác Từ','gv05.manghiep@huit.edu.vn','0999290681','Mạng máy tính, An toàn thông tin, Linux, DevOps, Docker, Cloud AWS','$2y$10$0xaWsl6QXYVPSAHYYb/R0.0DrDwcjPE0kni0VZP7om4mbzow.QVCq','teacher','12380d11ca7916fc054ccccf57d0326cac02f81e0926d0d33ec0d3ad7a621f2a',18,'2026-11-18 01:01:40'),(8,'TS. Lương Bổng Phúc','gv06.truongtran@huit.edu.vn','0913984594','Frontend, ReactJS, VueJS, TypeScript, Thiết kế giao diện UI/UX, Figma','$2y$10$0xaWsl6QXYVPSAHYYb/R0.0DrDwcjPE0kni0VZP7om4mbzow.QVCq','teacher','1e201ce70faf3be2b9a3cbba6b390e26dcc97fcdc9dec8375b71ec9bfc89d32e',18,'2026-11-18 01:01:40'),(9,'ThS. Lưu Thực San','gv07.vi33@huit.edu.vn','0940808678','Phát triển phần mềm mã nguồn mở, PHP, Laravel, Git, REST API','$2y$10$0xaWsl6QXYVPSAHYYb/R0.0DrDwcjPE0kni0VZP7om4mbzow.QVCq','teacher','57a00499bf763dff5a352d7fa2041e5bdb720009ce23d34f6e36f2607891ccf1',15,'2026-11-18 01:01:40'),(10,'ThS. Nông Tuyến Hiên','gv08.duong89@huit.edu.vn','0957178494','Cơ sở dữ liệu, MySQL, PostgreSQL, NoSQL, MongoDB, Redis, Big Data','$2y$10$0xaWsl6QXYVPSAHYYb/R0.0DrDwcjPE0kni0VZP7om4mbzow.QVCq','teacher','b96cafd9b7b0940b4369b6f2a188ff19a2113c24e5432a86203bb642ce583793',18,'2026-11-18 01:01:40'),(11,'GVC. ThS. Mộc Án Khôi','gv09.caikhanh@huit.edu.vn','0960701310','Trí tuệ nhân tạo, Machine Learning, Deep Learning, Python, Data Science','$2y$10$0xaWsl6QXYVPSAHYYb/R0.0DrDwcjPE0kni0VZP7om4mbzow.QVCq','teacher','f380942d9e5c62c7bb87756e4442ea47a61724e3796e38f04cd0fff22a72abe9',18,'2026-11-18 01:01:40'),(12,'TS. Đái Hải Thy','gv10.tungngo@huit.edu.vn','0986050770','Công nghệ phần mềm, OOP, Java, Spring Boot, C#, Kiểm thử phần mềm','$2y$10$0xaWsl6QXYVPSAHYYb/R0.0DrDwcjPE0kni0VZP7om4mbzow.QVCq','teacher','f70df587f0bf5a80cefd5db23eb7963251dbccd71e5597f3cb37c12d5f5e16b0',15,'2026-11-18 01:01:40'),(13,'TS. Chế Thúy Thảo','gv11.jcu@huit.edu.vn','0959783540','Mạng máy tính, An toàn thông tin, Linux, DevOps, Docker, Cloud AWS','$2y$10$0xaWsl6QXYVPSAHYYb/R0.0DrDwcjPE0kni0VZP7om4mbzow.QVCq','teacher','abfc577a516251ab45bde133ea5d6e9b6716965bcc459ff599b7ddbbed313a30',18,'2026-11-18 01:01:40'),(14,'ThS. Liễu Trân Trung','gv12.khang56@huit.edu.vn','0942410053','Frontend, ReactJS, VueJS, TypeScript, Thiết kế giao diện UI/UX, Figma','$2y$10$0xaWsl6QXYVPSAHYYb/R0.0DrDwcjPE0kni0VZP7om4mbzow.QVCq','teacher','2094ab6890b14c2ed89f410d5cae7683b5c566c9a18e8a67789f9f2686be9e98',18,'2026-11-18 01:01:40'),(15,'ThS. Trình Hiệp Bình','gv13.thu30@huit.edu.vn','0915280757','Phát triển phần mềm mã nguồn mở, PHP, Laravel, Git, REST API','$2y$10$0xaWsl6QXYVPSAHYYb/R0.0DrDwcjPE0kni0VZP7om4mbzow.QVCq','teacher','8f6f2520f20ba6143e724f223b21a7d788a739e889cd0e975e3b4765786fd7ec',15,'2026-11-18 01:01:40'),(16,'GVC. ThS. Thập Quyên Như','gv14.dan07@huit.edu.vn','0959751422','Cơ sở dữ liệu, MySQL, PostgreSQL, NoSQL, MongoDB, Redis, Big Data','$2y$10$0xaWsl6QXYVPSAHYYb/R0.0DrDwcjPE0kni0VZP7om4mbzow.QVCq','teacher','6348845fb2256523c85ceaa0b3e0dc0da9ecba2fb4c439a7f73a31d26059f40a',18,'2026-11-18 01:01:40');
/*!40000 ALTER TABLE `teachers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'huit_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-18  8:01:41
