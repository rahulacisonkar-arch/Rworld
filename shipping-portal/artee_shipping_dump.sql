/*!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.18-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: artee_shipping
-- ------------------------------------------------------
-- Server version	10.6.18-MariaDB

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
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_logs_user` (`user_id`),
  KEY `fk_logs_request` (`request_id`),
  CONSTRAINT `fk_logs_request` FOREIGN KEY (`request_id`) REFERENCES `label_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=248 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (68,2,'Status of request AR-1013 updated to Completed',13,NULL,'2026-06-16 15:53:29'),(72,2,'Status of request AR-1008 updated to Completed',8,NULL,'2026-06-16 15:54:19'),(74,2,'Status of request AR-1010 updated to Completed',10,NULL,'2026-06-16 15:54:34'),(75,2,'Status of request AR-1011 updated to Completed',11,NULL,'2026-06-16 15:54:41'),(77,2,'Sent label and updated status of request AR-1013 to Completed',13,NULL,'2026-06-16 16:14:34'),(78,2,'Created Vendor label request directly AR-1014 (Tracking: 873152247906)',14,NULL,'2026-06-16 16:23:04'),(79,2,'Sent label and updated status of request AR-1014 to Completed',14,NULL,'2026-06-16 16:25:04'),(80,2,'Sent label and updated status of request AR-1014 to Completed',14,NULL,'2026-06-16 16:40:36'),(81,2,'Sent label and updated status of request AR-1014 to Completed',14,NULL,'2026-06-16 18:30:08'),(84,2,'Created Vendor label request directly AR-1015 (Tracking: 873161192359)',15,NULL,'2026-06-16 18:32:33'),(85,2,'Created Vendor label request directly AR-1016 (Tracking: 873161414477)',16,NULL,'2026-06-16 18:35:44'),(86,2,'Sent label and updated status of request AR-1015 to Completed',15,NULL,'2026-06-16 18:37:13'),(87,2,'Sent label and updated status of request AR-1016 to Completed',16,NULL,'2026-06-16 18:37:30'),(90,2,'Sent label and updated status of request AR-1013 to Completed',13,NULL,'2026-06-16 19:14:47'),(91,2,'Created Vendor label request directly AR-1017 (Tracking: 1Z0K43R30318107939)',17,NULL,'2026-06-16 19:40:58'),(92,2,'Sent label and updated status of request AR-1017 to Label Created',17,NULL,'2026-06-16 21:11:06'),(93,2,'Sent label and updated status of request AR-1017 to Completed',17,NULL,'2026-06-16 21:11:13'),(94,2,'Sent label and updated status of request AR-1017 to Label Created',17,NULL,'2026-06-16 21:11:43'),(95,2,'Sent label and updated status of request AR-1017 to Completed',17,NULL,'2026-06-16 21:17:36'),(96,2,'User logged out successfully',NULL,NULL,'2026-06-16 21:17:42'),(97,10,'User logged in successfully',NULL,NULL,'2026-06-16 21:17:46'),(98,10,'User logged out successfully',NULL,NULL,'2026-06-16 21:28:29'),(99,2,'User logged in successfully',NULL,NULL,'2026-06-16 21:28:33'),(100,2,'Sent label and updated status of request AR-1017 to Label Sent',17,NULL,'2026-06-16 21:28:45'),(101,2,'User logged out successfully',NULL,NULL,'2026-06-16 21:28:50'),(102,10,'User logged in successfully',NULL,NULL,'2026-06-16 21:28:53'),(103,10,'User logged out successfully',NULL,NULL,'2026-06-16 21:29:25'),(104,2,'User logged in successfully',NULL,NULL,'2026-06-16 21:29:28'),(105,2,'Sent label and updated status of request AR-1017 to Completed',17,NULL,'2026-06-16 21:29:38'),(106,2,'User logged out successfully',NULL,NULL,'2026-06-16 21:29:41'),(107,10,'User logged in successfully',NULL,NULL,'2026-06-16 21:29:44'),(108,10,'User logged out successfully',NULL,NULL,'2026-06-17 11:32:46'),(109,10,'User logged in successfully',NULL,NULL,'2026-06-17 11:32:48'),(110,10,'User logged out successfully',NULL,NULL,'2026-06-17 11:32:53'),(111,2,'User logged in successfully',NULL,NULL,'2026-06-17 11:33:20'),(112,2,'Sent label and updated status of request AR-1017 to Processing',17,NULL,'2026-06-17 11:33:32'),(113,2,'User logged out successfully',NULL,NULL,'2026-06-17 11:33:43'),(114,10,'User logged in successfully',NULL,NULL,'2026-06-17 11:33:47'),(115,10,'User logged out successfully',NULL,NULL,'2026-06-17 11:33:56'),(116,2,'User logged in successfully',NULL,NULL,'2026-06-17 11:34:00'),(117,2,'Sent label and updated status of request AR-1017 to Completed',17,NULL,'2026-06-17 11:40:30'),(118,2,'User logged out successfully',NULL,NULL,'2026-06-17 11:40:33'),(119,10,'User logged in successfully',NULL,NULL,'2026-06-17 11:40:36'),(120,10,'User logged out successfully',NULL,NULL,'2026-06-17 11:40:42'),(121,2,'User logged in successfully',NULL,NULL,'2026-06-17 11:40:45'),(122,2,'User logged out successfully',NULL,NULL,'2026-06-17 11:42:57'),(123,1,'User logged in successfully',NULL,NULL,'2026-06-17 11:43:00'),(124,1,'User logged out successfully',NULL,NULL,'2026-06-17 11:44:44'),(125,2,'User logged in successfully',NULL,NULL,'2026-06-17 11:44:52'),(126,2,'Sent label and updated status of request AR-1017 to Label Created',17,NULL,'2026-06-17 11:45:04'),(127,2,'User logged out successfully',NULL,NULL,'2026-06-17 11:53:47'),(128,10,'User logged in successfully',NULL,NULL,'2026-06-17 11:53:50'),(129,10,'User logged out successfully',NULL,NULL,'2026-06-17 11:54:01'),(130,2,'User logged in successfully',NULL,NULL,'2026-06-17 11:54:04'),(131,2,'Sent label and updated status of request AR-1017 to Completed',17,NULL,'2026-06-17 11:54:11'),(132,2,'User logged out successfully',NULL,NULL,'2026-06-17 11:55:17'),(133,5,'User logged in successfully',NULL,NULL,'2026-06-17 11:55:40'),(134,5,'User logged out successfully',NULL,NULL,'2026-06-17 12:02:02'),(135,1,'User logged in successfully',NULL,NULL,'2026-06-17 12:02:06'),(136,1,'Sent label and updated status of request AR-1014 to Label Created',14,NULL,'2026-06-17 12:03:54'),(137,1,'User logged out successfully',NULL,NULL,'2026-06-17 12:03:57'),(138,7,'User logged in successfully',NULL,NULL,'2026-06-17 12:04:04'),(139,7,'User logged out successfully',NULL,NULL,'2026-06-17 12:04:23'),(140,2,'User logged in successfully',NULL,NULL,'2026-06-17 12:04:26'),(141,2,'Sent label and updated status of request AR-1014 to Completed',14,NULL,'2026-06-17 12:04:36'),(142,2,'User logged out successfully',NULL,NULL,'2026-06-17 12:04:38'),(143,7,'User logged in successfully',NULL,NULL,'2026-06-17 12:04:43'),(144,7,'User logged out successfully',NULL,NULL,'2026-06-17 12:04:48'),(145,8,'User logged in successfully',NULL,NULL,'2026-06-17 12:04:56'),(146,8,'User logged out successfully',NULL,NULL,'2026-06-17 12:05:17'),(147,7,'User logged in successfully',NULL,NULL,'2026-06-17 12:06:44'),(148,7,'Marked all notifications as read',NULL,NULL,'2026-06-17 12:08:15'),(149,7,'User logged out successfully',NULL,NULL,'2026-06-17 12:08:56'),(150,2,'User logged in successfully',NULL,NULL,'2026-06-17 12:08:59'),(151,2,'User logged out successfully',NULL,NULL,'2026-06-17 12:09:25'),(152,2,'User logged in successfully',NULL,NULL,'2026-06-17 12:09:27'),(153,2,'User logged out successfully',NULL,NULL,'2026-06-17 12:12:10'),(154,2,'User logged in successfully',NULL,NULL,'2026-06-17 12:12:11'),(155,2,'User logged out successfully',NULL,NULL,'2026-06-17 12:15:02'),(156,10,'User logged in successfully',NULL,NULL,'2026-06-17 12:15:06'),(157,10,'User logged out successfully',NULL,NULL,'2026-06-17 12:16:37'),(158,2,'User logged in successfully',NULL,NULL,'2026-06-17 12:16:41'),(159,2,'User logged out successfully',NULL,NULL,'2026-06-17 12:22:38'),(160,10,'User logged in successfully',NULL,NULL,'2026-06-17 12:22:40'),(161,10,'User logged out successfully',NULL,NULL,'2026-06-17 12:22:46'),(162,10,'User logged in successfully',NULL,NULL,'2026-06-17 12:22:49'),(163,10,'User logged out successfully',NULL,NULL,'2026-06-17 12:24:27'),(164,7,'User logged in successfully',NULL,NULL,'2026-06-17 12:24:30'),(165,7,'User logged out successfully',NULL,NULL,'2026-06-17 12:24:47'),(166,7,'User logged in successfully',NULL,NULL,'2026-06-17 12:25:00'),(167,7,'User logged out successfully',NULL,NULL,'2026-06-17 12:30:24'),(168,10,'User logged in successfully',NULL,NULL,'2026-06-17 12:30:28'),(169,10,'User logged out successfully',NULL,NULL,'2026-06-17 12:31:52'),(170,1,'User logged in successfully',NULL,NULL,'2026-06-17 12:31:56'),(171,1,'User logged out successfully',NULL,NULL,'2026-06-17 12:32:07'),(172,5,'User logged in successfully',NULL,NULL,'2026-06-17 12:32:15'),(173,5,'User logged out successfully',NULL,NULL,'2026-06-17 12:33:59'),(174,1,'User logged in successfully',NULL,NULL,'2026-06-17 12:34:01'),(175,1,'User logged out successfully',NULL,NULL,'2026-06-17 12:36:35'),(176,10,'User logged in successfully',NULL,NULL,'2026-06-17 12:36:38'),(177,10,'User logged out successfully',NULL,NULL,'2026-06-17 12:52:18'),(178,2,'User logged in successfully',NULL,NULL,'2026-06-17 12:52:21'),(179,2,'User logged out successfully',NULL,NULL,'2026-06-17 12:53:04'),(180,7,'User logged in successfully',NULL,NULL,'2026-06-17 12:53:09'),(181,7,'User logged out successfully',NULL,NULL,'2026-06-17 12:53:56'),(182,7,'User logged in successfully',NULL,NULL,'2026-06-17 12:53:58'),(183,7,'User logged out successfully',NULL,NULL,'2026-06-17 12:54:02'),(184,1,'User logged in successfully',NULL,NULL,'2026-06-17 12:54:04'),(185,1,'User logged out successfully',NULL,NULL,'2026-06-17 13:16:56'),(186,10,'User logged in successfully',NULL,NULL,'2026-06-17 13:16:58'),(187,10,'User logged out successfully',NULL,NULL,'2026-06-17 13:17:34'),(188,1,'User logged in successfully',NULL,NULL,'2026-06-17 13:17:37'),(189,1,'User logged out successfully',NULL,NULL,'2026-06-17 13:22:08'),(190,7,'User logged in successfully',NULL,NULL,'2026-06-17 13:22:11'),(191,7,'User logged out successfully',NULL,NULL,'2026-06-17 13:22:24'),(192,1,'User logged in successfully',NULL,NULL,'2026-06-17 13:22:54'),(193,1,'User logged out successfully',NULL,NULL,'2026-06-17 13:24:21'),(194,7,'User logged in successfully',NULL,NULL,'2026-06-17 13:24:23'),(195,7,'User logged out successfully',NULL,NULL,'2026-06-17 13:24:32'),(196,1,'User logged in successfully',NULL,NULL,'2026-06-17 13:24:37'),(197,1,'User logged out successfully',NULL,NULL,'2026-06-17 13:27:18'),(198,2,'User logged in successfully',NULL,NULL,'2026-06-17 13:27:24'),(199,2,'Created Vendor label request directly AR-1018 (Tracking: 1Z0K43R30302874943)',18,NULL,'2026-06-17 13:30:33'),(200,2,'Sent label and updated status of request AR-1017 to Label Created',17,NULL,'2026-06-17 13:31:54'),(201,2,'User logged out successfully',NULL,NULL,'2026-06-17 13:32:05'),(202,10,'User logged in successfully',NULL,NULL,'2026-06-17 13:32:09'),(203,10,'User logged out successfully',NULL,NULL,'2026-06-17 13:33:34'),(204,2,'User logged in successfully',NULL,NULL,'2026-06-17 13:33:37'),(205,2,'Sent label and updated status of request AR-1017 to Completed',17,NULL,'2026-06-17 13:33:52'),(206,2,'User logged out successfully',NULL,NULL,'2026-06-17 13:33:59'),(207,10,'User logged in successfully',NULL,NULL,'2026-06-17 13:34:12'),(208,10,'User logged out successfully',NULL,NULL,'2026-06-17 13:35:12'),(209,8,'User logged in successfully',NULL,NULL,'2026-06-17 13:35:20'),(210,8,'Marked all notifications as read',NULL,NULL,'2026-06-17 13:36:07'),(211,8,'User logged out successfully',NULL,NULL,'2026-06-17 13:54:27'),(212,1,'User logged in successfully',NULL,NULL,'2026-06-17 13:54:30'),(213,1,'User logged out successfully',NULL,NULL,'2026-06-17 13:54:53'),(214,10,'User logged in successfully',NULL,NULL,'2026-06-17 13:54:56'),(215,10,'User logged out successfully',NULL,NULL,'2026-06-17 13:59:24'),(216,1,'User logged in successfully',NULL,NULL,'2026-06-17 13:59:27'),(217,1,'Sent label and updated status of request AR-1018 to Pending',18,NULL,'2026-06-17 14:12:12'),(218,1,'Sent label and updated status of request AR-1018 to Completed',18,NULL,'2026-06-17 14:12:43'),(219,1,'User logged out successfully',NULL,NULL,'2026-06-17 18:13:15'),(220,1,'User logged in successfully',NULL,NULL,'2026-06-17 18:13:16'),(221,1,'Created Vendor label request directly AR-1019 (Tracking: 873215983603)',19,NULL,'2026-06-17 18:24:55'),(222,1,'User logged out successfully',NULL,NULL,'2026-06-17 18:26:12'),(223,8,'User logged in successfully',NULL,NULL,'2026-06-17 18:26:23'),(224,8,'User logged out successfully',NULL,NULL,'2026-06-17 18:36:22'),(225,2,'User logged in successfully',NULL,NULL,'2026-06-17 18:36:25'),(226,2,'Created Vendor label request directly AR-1020 (Tracking: 1Z0K43R30307847955)',20,NULL,'2026-06-17 19:07:30'),(227,2,'Created Vendor label request directly AR-1021 (Tracking: 873218842985)',21,NULL,'2026-06-17 19:09:56'),(228,2,'User logged out successfully',NULL,NULL,'2026-06-17 19:10:32'),(229,6,'User logged in successfully',NULL,NULL,'2026-06-17 19:12:22'),(231,6,'User logged out successfully',NULL,NULL,'2026-06-17 19:14:10'),(232,2,'User logged in successfully',NULL,NULL,'2026-06-17 19:14:12'),(234,2,'User logged out successfully',NULL,NULL,'2026-06-17 19:18:03'),(235,6,'User logged in successfully',NULL,NULL,'2026-06-17 19:18:13'),(236,6,'User logged out successfully',NULL,NULL,'2026-06-17 19:18:49'),(237,3,'User logged in successfully',NULL,NULL,'2026-06-17 19:19:00'),(238,3,'User logged out successfully',NULL,NULL,'2026-06-17 19:19:19'),(239,2,'User logged in successfully',NULL,NULL,'2026-06-17 19:19:23'),(240,2,'User logged out successfully',NULL,NULL,'2026-06-17 19:19:52'),(241,8,'User logged in successfully',NULL,NULL,'2026-06-17 19:19:58'),(242,8,'Marked all notifications as read',NULL,NULL,'2026-06-17 19:21:03'),(243,8,'User logged out successfully',NULL,NULL,'2026-06-17 19:21:46'),(244,2,'User logged in successfully',NULL,NULL,'2026-06-17 21:25:13'),(245,2,'Marked all notifications as read',NULL,NULL,'2026-06-18 11:23:30'),(246,2,'User logged out successfully',NULL,NULL,'2026-06-18 11:23:42'),(247,2,'User logged in successfully',NULL,NULL,'2026-06-18 11:23:55');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `label_requests`
--

DROP TABLE IF EXISTS `label_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `label_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_number` varchar(50) NOT NULL,
  `store_id` int(11) NOT NULL,
  `ship_from_name` varchar(100) NOT NULL,
  `ship_from_company` varchar(100) NOT NULL,
  `ship_from_address1` varchar(255) NOT NULL,
  `ship_from_address2` varchar(255) DEFAULT NULL,
  `ship_from_city` varchar(100) NOT NULL,
  `ship_from_state` varchar(50) NOT NULL,
  `ship_from_zip` varchar(20) NOT NULL,
  `ship_from_phone` varchar(100) NOT NULL,
  `ship_from_email` varchar(255) DEFAULT NULL,
  `ship_to_name` varchar(100) NOT NULL,
  `ship_to_company` varchar(100) NOT NULL,
  `ship_to_address1` varchar(255) NOT NULL,
  `ship_to_address2` varchar(255) DEFAULT NULL,
  `ship_to_city` varchar(100) NOT NULL,
  `ship_to_state` varchar(50) NOT NULL,
  `ship_to_zip` varchar(20) NOT NULL,
  `ship_to_phone` varchar(100) NOT NULL,
  `ship_to_email` varchar(255) DEFAULT NULL,
  `sales_order_number` varchar(50) NOT NULL,
  `request_reference` varchar(50) DEFAULT NULL,
  `length` decimal(10,2) NOT NULL,
  `width` decimal(10,2) NOT NULL,
  `height` decimal(10,2) NOT NULL,
  `weight_lbs` decimal(10,2) NOT NULL,
  `shipping_method` varchar(50) NOT NULL,
  `customer_freight_charge` decimal(10,2) NOT NULL,
  `special_instructions` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `status` enum('Pending','Processing','Label Created','Label Sent','Completed','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_number` (`request_number`),
  KEY `fk_requests_store` (`store_id`),
  CONSTRAINT `fk_requests_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `label_requests`
--

LOCK TABLES `label_requests` WRITE;
/*!40000 ALTER TABLE `label_requests` DISABLE KEYS */;
INSERT INTO `label_requests` VALUES (8,'AR-1008',10,'Hamilton Fabrics','Hamilton Fabrics','629 Southwest Street',NULL,'High Point','NC','27261','3368867388','hamfab@northstate.net','Susannah','ARTI FABRIC (RAGS & RICHES)','3762 Shelburne Rd Shelburne,',NULL,'Shelburne','VT','05482','8028623288','ragsandriches@comcast.net','PO/638',NULL,14.00,12.00,2.00,3.00,'FedEx 2Day®',11.75,NULL,NULL,'Completed','2026-06-16 13:06:12','2026-06-16 15:54:19'),(10,'AR-1010',10,'Hamilton Fabrics','Hamilton Fabrics','629 Southwest Street',NULL,'High Point','NC','27261','3368867388','hamfab@northstate.net','Susannah','RAGS & RICHES','3762 SHELBURNE ROAD',NULL,'SHELBURNE','VT','05482','802-862-3288','ragsandriches@comcast.net','PO/638',NULL,14.00,12.00,2.00,3.00,'UPS® Ground Saver',11.75,NULL,NULL,'Completed','2026-06-16 14:08:28','2026-06-16 15:54:34'),(11,'AR-1011',10,'Hamilton Fabrics','Hamilton Fabrics','629 Southwest Street',NULL,'High Point','NC','27261','3368867388','hamfab@northstate.net','Susannah','RAGS & RICHES','3762 SHELBURNE ROAD',NULL,'SHELBURNE','VT','05482','802-862-3288','ragsandriches@comcast.net','PO/638',NULL,14.00,12.00,2.00,3.00,'FedEx 2Day®',11.75,NULL,NULL,'Completed','2026-06-16 14:26:04','2026-06-16 15:54:41'),(13,'AR-1013',10,'Christy Hall','MAGNOLIA FABRICS','1727 MCCULLOUGH BLVD',NULL,'Tupelo','MS','38801','6628412000','customerservice@magfabrics.com','PAM','ARTEE FABRICS & HOME','7016 B Market St Wilmington',NULL,'Wilmington','NC','28411','9106862950','wilmington@arteefabricsandhome.com','PO/672',NULL,59.00,7.00,7.00,28.00,'FedEx 2Day®',17.93,NULL,NULL,'Completed','2026-06-16 15:49:58','2026-06-16 19:14:47'),(14,'AR-1014',10,'Nichole Trahey','Crypton LLC','38500 Woodward Ave, Suite 201',NULL,'Bloomfield Hills','MI','48304','7042595074','catherine@crypton.com','Beth','GOOD GOODS','859 POST ROAD',NULL,'DARIEN','CT','06820','203-655-8100','goodgoodsgirls@gmail.com','PO/583',NULL,62.00,8.00,8.00,42.00,'FedEx 2Day®',17.93,NULL,NULL,'Completed','2026-06-16 16:22:42','2026-06-17 12:04:36'),(15,'AR-1015',10,'Melody','PRINTER\'S ALLEY','5910-111 DURALEIGH ROAD',NULL,'RALEIGH','NC','27612','919-781-1777','printersalleyraleigh@gmail.com','Pam','ARTEE FABRICS & HOME','7016 B MARKET STREET',NULL,'WILMINGTON','NC','28411','910-686-2950','wilmington.aci@gmail.com','Lining',NULL,55.00,7.00,7.00,50.00,'FedEx Standard Overnight®',17.93,NULL,NULL,'Completed','2026-06-16 18:32:21','2026-06-16 18:37:13'),(16,'AR-1016',10,'Regal Fabrics','Regal Fabrics','177 North Main Street, Building 400',NULL,'Middleton','MA','01949','9787776868','JannetG@regalfabrics.com','Pam','ARTEE FABRICS & HOME','7016 B MARKET STREET',NULL,'WILMINGTON','NC','28411','910-686-2950','wilmington.aci@gmail.com','PO #571',NULL,59.00,6.00,6.00,20.00,'FedEx 2Day®',17.93,NULL,NULL,'Completed','2026-06-16 18:35:32','2026-06-16 18:37:30'),(17,'AR-1017',10,'Mark Garber','Europatex Inc.','125 Henderson Street',NULL,'High Point','NC','27263','9738090680','inquiries@europatex.com','TERRI','ARTEE FABRICS & HOME','9543 FIELDS ERTEL ROAD LOVELAND, OH',NULL,'Loveland','OH','45140','5136835400','cincinnati@arteefabricsandhome.com','PO/667',NULL,16.00,11.00,3.00,3.00,'UPS® Ground',7.81,NULL,NULL,'Completed','2026-06-16 19:40:48','2026-06-17 13:33:52'),(18,'AR-1018',6,'Brandy Timothy','JB Martin Co','321 S East Ave',NULL,'Leesville','SC','29070','8035321625','shipping@jbmartin.com','PATTI TOMENY','PATTI TOMENY','158 WORCESTER MOUNTAIN VIEW',NULL,'WATERBURY CENTER','VT','05677','8028623288','ragsandriches@comcast.net','PO# 642',NULL,59.00,6.00,6.00,11.00,'UPS® Ground',17.78,NULL,NULL,'Completed','2026-06-17 13:30:23','2026-06-17 14:12:43'),(19,'AR-1019',6,'Mark Garber','Europatex Inc.','301 Summit Avenue',NULL,'Jersey City','NJ','07306','9738090680','inquiries@europatex.com','QUEYEN TRONG','QUEYEN TRONG','184 Forest Ln.',NULL,'Cheshire','CT','06410','8028623288','ragsandriches@comcast.net','Po/678',NULL,60.00,3.00,3.00,6.00,'FedEx Standard Overnight®',22.05,NULL,NULL,'Completed','2026-06-17 18:24:42','2026-06-17 18:24:55'),(20,'AR-1020',6,'JERRY','ARTEE FABRICS & HOME','600 HIGH ST',NULL,'PORTSMOUTH','VA','23704','757-966-1808','jfreeman.aci@gmail.com','LORI','RAGS & RICHES','3762 SHELBURNE ROAD',NULL,'SHELBURNE','VT','05482','802-862-3288','ragsandriches@comcast.net','SO/37,48,45,83,71',NULL,16.00,11.00,3.00,3.00,'UPS® Ground',50.00,NULL,NULL,'Completed','2026-06-17 19:07:20','2026-06-17 19:07:30'),(21,'AR-1021',3,'Store Staff','ARTEE FABRICS & HOME','7016 B MARKET STREET',NULL,'WILMINGTON','NC','28411','910-686-2950','wilmington.aci@gmail.com','Artistic Quilting','Artistic Quilting','108 LANE AVE, HIGH POINT',NULL,'High Point','NC','27260','910-686-2950','Lauren@ArtisticQuiltingInc.com','SO#33',NULL,54.00,4.00,4.00,2.00,'FedEx 2Day®',40.00,NULL,NULL,'Completed','2026-06-17 19:09:34','2026-06-17 19:09:56');
/*!40000 ALTER TABLE `label_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_notifications_user` (`user_id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (17,12,'Your shipping labels are ready - AR-1013','Your shipping labels (1 cartons) are ready. SO#: PO/672. Tracking: 873149963592 (FedEx 2Day®)',0,'2026-06-16 19:14:47'),(18,12,'Your shipping labels are ready - AR-1017','Your shipping labels (1 cartons) are ready. SO#: PO/667. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-16 21:11:06'),(19,12,'Your shipping labels are ready - AR-1017','Your shipping labels (1 cartons) are ready. SO#: PO/667. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-16 21:11:13'),(20,12,'Your shipping labels are ready - AR-1017','Your shipping labels (1 cartons) are ready. SO#: PO/667. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-16 21:11:43'),(21,12,'Your shipping labels are ready - AR-1017','Your shipping labels (1 cartons) are ready. SO#: PO/667. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-16 21:17:36'),(22,12,'Your shipping labels are ready - AR-1017','Your shipping labels (1 cartons) are ready. SO#: PO/667. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-16 21:28:45'),(23,12,'Your shipping labels are ready - AR-1017','Your shipping labels (1 cartons) are ready. SO#: PO/667. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-16 21:29:38'),(24,12,'Your shipping labels are ready - AR-1017','Your shipping labels (1 cartons) are ready. SO#: PO/667. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-17 11:33:32'),(25,12,'Your shipping labels are ready - AR-1017','Your shipping labels (1 cartons) are ready. SO#: PO/667. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-17 11:40:30'),(26,12,'Your shipping labels are ready - AR-1017','Your shipping labels (1 cartons) are ready. SO#: PO/667. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-17 11:45:04'),(27,12,'Your shipping labels are ready - AR-1017','Your shipping labels (1 cartons) are ready. SO#: PO/667. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-17 11:54:11'),(28,10,'Incoming shipment labels are ready - AR-1017','Your shipping labels (1 cartons) are ready. SO#: PO/667. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-17 11:54:11'),(31,12,'Shipment Status Updated - AR-1017','Shipment status for AR-1017 (SO#: PO/667) has been updated to: Completed. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-17 11:59:08'),(32,10,'Incoming Shipment Status Updated - AR-1017','Shipment status for AR-1017 (SO#: PO/667) has been updated to: Completed. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-17 11:59:08'),(33,12,'Your shipping labels are ready - AR-1014','Your shipping labels (1 cartons) are ready. SO#: PO/583. Tracking: 873152247906 (FedEx 2Day®)',0,'2026-06-17 12:03:54'),(34,7,'Incoming shipment labels are ready - AR-1014','Your shipping labels (1 cartons) are ready. SO#: PO/583. Tracking: 873152247906 (FedEx 2Day®)',1,'2026-06-17 12:03:54'),(35,12,'Shipment Status Updated - AR-1014','Shipment status for AR-1014 (SO#: PO/583) has been updated to: Completed. Tracking: 873152247906 (FedEx 2Day®)',0,'2026-06-17 12:04:36'),(36,7,'Incoming Shipment Status Updated - AR-1014','Shipment status for AR-1014 (SO#: PO/583) has been updated to: Completed. Tracking: 873152247906 (FedEx 2Day®)',1,'2026-06-17 12:04:36'),(37,8,'Vendor shipping label created - AR-1018','A new vendor shipping label has been created for your store. SO#: PO# 642. Tracking: 1Z0K43R30302874943 (UPS® Ground)',1,'2026-06-17 13:30:33'),(38,12,'Your shipping labels are ready - AR-1017','Your shipping labels (1 cartons) are ready. SO#: PO/667. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-17 13:31:54'),(39,10,'Incoming shipment labels are ready - AR-1017','Your shipping labels (1 cartons) are ready. SO#: PO/667. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-17 13:31:54'),(40,12,'Shipment Status Updated - AR-1017','Shipment status for AR-1017 (SO#: PO/667) has been updated to: Completed. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-17 13:33:52'),(41,10,'Incoming Shipment Status Updated - AR-1017','Shipment status for AR-1017 (SO#: PO/667) has been updated to: Completed. Tracking: 1Z0K43R30318107939 (UPS® Ground)',0,'2026-06-17 13:33:52'),(42,8,'Shipment Status Updated - AR-1018','Shipment status for AR-1018 (SO#: PO# 642) has been updated to: Pending',1,'2026-06-17 14:12:12'),(43,8,'Shipment Status Updated - AR-1018','Shipment status for AR-1018 (SO#: PO# 642) has been updated to: Completed. Tracking: 1Z0K43R30302874943 (UPS® Ground)',1,'2026-06-17 14:12:43'),(44,8,'Vendor shipping label created - AR-1019','A new vendor shipping label has been created for your store. SO#: Po/678. Tracking: 873215983603 (FedEx Standard Overnight®)',1,'2026-06-17 18:24:55'),(45,8,'Vendor shipping label created - AR-1020','A new vendor shipping label has been created for your store. SO#: SO/37,48,45,83,71. Tracking: 1Z0K43R30307847955 (UPS® Ground)',1,'2026-06-17 19:07:30'),(46,5,'Vendor shipping label created - AR-1021','A new vendor shipping label has been created for your store. SO#: SO#33. Tracking: 873218842985 (FedEx 2Day®)',0,'2026-06-17 19:09:56'),(47,1,'New Request submitted: AR-1022','Store ARTEE FABRICS & HOME has submitted a new label request. SO#: PO/638',0,'2026-06-17 19:13:56'),(48,2,'New Request submitted: AR-1022','Store ARTEE FABRICS & HOME has submitted a new label request. SO#: PO/638',1,'2026-06-17 19:13:56'),(49,6,'Shipment Status Updated - AR-1022','Shipment status for AR-1022 (SO#: PO/638) has been updated to: Cancelled',0,'2026-06-17 19:17:56'),(50,3,'Incoming Shipment Status Updated - AR-1022','Shipment status for AR-1022 (SO#: PO/638) has been updated to: Cancelled',0,'2026-06-17 19:17:56');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `request_labels`
--

DROP TABLE IF EXISTS `request_labels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `request_labels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `label_file` varchar(255) NOT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `carrier` varchar(50) DEFAULT NULL,
  `estimated_delivery_date` date DEFAULT NULL,
  `actual_shipping_cost` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `easyship_shipment_id` varchar(100) DEFAULT NULL,
  `tracking_status` varchar(100) DEFAULT 'Label Created',
  `tracking_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_labels_request` (`request_id`),
  CONSTRAINT `fk_labels_request` FOREIGN KEY (`request_id`) REFERENCES `label_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `request_labels`
--

LOCK TABLES `request_labels` WRITE;
/*!40000 ALTER TABLE `request_labels` DISABLE KEYS */;
INSERT INTO `request_labels` VALUES (7,11,'label_11_1fe4b9a6611b2bc9.pdf','873144036559','FedEx 2Day®','2026-06-18',11.75,'2026-06-16 14:35:18','ESUS339028908','Out for Delivery','2026-06-18 12:48:35'),(8,10,'label_10_43227f03b39cbc8b.pdf','1ZB8R405YW04424680','UPS® Ground Saver','2026-06-18',11.75,'2026-06-16 14:35:20','ESUS339024820','Label Ready','2026-06-17 12:07:40'),(9,8,'label_8_34320dd5e134526b.pdf','873142382071','FedEx 2Day®','2026-06-18',17.93,'2026-06-16 14:35:25','ESUS339022836','Label Ready','2026-06-18 12:48:35'),(11,14,'label_14_72cfc59e4994aa52.pdf','873152247906','FedEx 2Day®','2026-06-18',17.93,'2026-06-16 16:23:04','ESUS339058556','Label Printed','2026-06-18 12:48:35'),(12,15,'label_15_d61e65d78463340e.pdf','873161192359','FedEx Standard Overnight®','2026-06-17',17.93,'2026-06-16 18:32:33','ESUS339089397','Delivered','2026-06-17 18:13:17'),(13,16,'label_16_cc49e3886e299d91.pdf','873161414477','FedEx 2Day®','2026-06-18',17.93,'2026-06-16 18:35:44','ESUS339090300','Label Printed','2026-06-18 12:48:35'),(14,13,'label_13_d9abe739946bfc41.pdf','873149963592','FedEx 2Day®','2026-06-18',17.93,'2026-06-16 18:59:16','ESUS339050060','In Transit to Customer','2026-06-18 12:48:35'),(15,17,'label_17_bc210a2c67493a8f.pdf','1Z0K43R30318107939','UPS® Ground','2026-06-18',7.81,'2026-06-16 19:40:58','ESUS339105720','In Transit to Customer','2026-06-18 12:48:35'),(16,18,'label_18_a2a35e05fb599834.pdf','1Z0K43R30302874943','UPS® Ground','2026-06-20',17.78,'2026-06-17 13:30:33','ESUS339252835','In Transit to Customer','2026-06-18 12:48:35'),(17,19,'label_19_b030c709da180610.pdf','873215983603','FedEx Standard Overnight®','2026-06-18',22.05,'2026-06-17 18:24:55','ESUS339322178','In Transit to Customer','2026-06-18 12:48:35'),(18,20,'label_20_07dce12ea3be6a3e.pdf','1Z0K43R30307847955','UPS® Ground','2026-06-19',8.29,'2026-06-17 19:07:30','ESUS339330534','In Transit to Customer','2026-06-18 12:48:35'),(19,21,'label_21_d245937fdbe36d37.pdf','873218842985','FedEx 2Day®','2026-06-19',30.51,'2026-06-17 19:09:56','ESUS339331103','Label Ready','2026-06-18 12:48:35');
/*!40000 ALTER TABLE `request_labels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saved_addresses`
--

DROP TABLE IF EXISTS `saved_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saved_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL,
  `address_type` enum('from','to') NOT NULL,
  `name` varchar(100) NOT NULL,
  `company` varchar(100) NOT NULL,
  `address1` varchar(255) NOT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(50) NOT NULL,
  `zip` varchar(20) NOT NULL,
  `phone` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_saved_addresses_store` (`store_id`),
  CONSTRAINT `fk_saved_addresses_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saved_addresses`
--

LOCK TABLES `saved_addresses` WRITE;
/*!40000 ALTER TABLE `saved_addresses` DISABLE KEYS */;
INSERT INTO `saved_addresses` VALUES (1,1,'from','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(2,1,'to','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(3,1,'from','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(4,1,'to','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(5,2,'from','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(6,2,'to','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(7,2,'from','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(8,2,'to','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(9,3,'from','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(10,3,'to','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(11,3,'from','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(12,3,'to','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(13,4,'from','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(14,4,'to','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(15,4,'from','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(16,4,'to','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(17,5,'from','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(18,5,'to','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(19,5,'from','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(20,5,'to','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(21,6,'from','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(22,6,'to','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(23,6,'from','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(24,6,'to','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(25,7,'from','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(26,7,'to','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(27,7,'from','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(28,7,'to','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(29,8,'from','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(30,8,'to','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(31,8,'from','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(32,8,'to','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(33,9,'from','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(34,9,'to','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(35,9,'from','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(36,9,'to','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(37,10,'from','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(38,10,'to','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(39,10,'from','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(40,10,'to','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(41,11,'from','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(42,11,'to','Staff','PARLOR UPHOLSTERY','201 DEXTER AVENUE',NULL,'WEST HARTFORD','CT','06110','','','2026-06-13 14:15:17'),(43,11,'from','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(44,11,'to','Staff','QUEYEN TRONG','184 FOREST LANE',NULL,'CHESHIRE','CT','06410','','','2026-06-13 14:15:17'),(45,10,'from','Christy Hall','MAGNOLIA FABRICS','1727 MCCULLOUGH BLVD',NULL,'Tupelo','MS','38801','6628412000','customerservice@magfabrics.com','2026-06-16 15:49:58'),(46,10,'from','Nichole Trahey','Crypton LLC','38500 Woodward Ave, Suite 201',NULL,'Bloomfield Hills','MI','48304','7042595074','catherine@crypton.com','2026-06-16 16:22:42'),(47,10,'from','Mark Garber','Europatex Inc.','125 Henderson Street',NULL,'High Point','NC','27263','9738090680','inquiries@europatex.com','2026-06-16 19:40:48'),(48,6,'to','PATTI TOMENY','PATTI TOMENY','158 WORCESTER MOUNTAIN VIEW',NULL,'WATERBURY CENTER','VT','05677','8028623288','ragsandriches@comcast.net','2026-06-17 13:30:23'),(49,6,'from','Mark Garber','Europatex Inc.','301 Summit Avenue',NULL,'Jersey City','NJ','07306','9738090680','inquiries@europatex.com','2026-06-17 18:24:42'),(50,3,'to','Artistic Quilting','Artistic Quilting','108 LANE AVE, HIGH POINT',NULL,'High Point','NC','27260','910-686-2950','Lauren@ArtisticQuiltingInc.com','2026-06-17 19:09:34');
/*!40000 ALTER TABLE `saved_addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stores`
--

DROP TABLE IF EXISTS `stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_code` varchar(20) NOT NULL,
  `store_name` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(50) NOT NULL,
  `zip` varchar(20) NOT NULL,
  `phone` varchar(100) NOT NULL,
  `notification_emails` text NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `store_code` (`store_code`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stores`
--

LOCK TABLES `stores` WRITE;
/*!40000 ALTER TABLE `stores` DISABLE KEYS */;
INSERT INTO `stores` VALUES (1,'78','ARTEE FABRICS & HOME','600 HIGH ST','PORTSMOUTH','VA','23704','757-966-1808','jfreeman.aci@gmail.com, portsmouth.afh@gmail.com','Active'),(2,'82','PRINTER\'S ALLEY','5910-111 DURALEIGH ROAD','RALEIGH','NC','27612','919-781-1777','printersalleyraleigh@gmail.com','Active'),(3,'63','ARTEE FABRICS & HOME','7016 B MARKET STREET','WILMINGTON','NC','28411','910-686-2950','wilmington.aci@gmail.com','Active'),(4,'64','ARTEE FABRICS & HOME','1776 LASKIN ROAD SUITE 106','VIRGINIA BEACH','VA','23454','757-963-7820','jfreeman.aci@gmail.com, arteevbeach@gmail.com','Active'),(5,'73','GOOD GOODS','859 POST ROAD','DARIEN','CT','06820','203-655-8100','goodgoodsgirls@gmail.com','Active'),(6,'71','RAGS & RICHES','3762 SHELBURNE ROAD','SHELBURNE','VT','05482','802-862-3288','ragsandriches@comcast.net','Active'),(7,'62','ARTEE FABRICS & HOME','8045 WEST BROAD STREET','HENRICO','VA','23294','804-285-9591','richmond@arteefabricsandhome.com','Active'),(8,'70','ARTEE FABRICS & HOME','9543 FIELDS ERTEL ROAD','LOVELAND','OH','45140','513-683-5400','cincinnati@arteefabricsandhome.com','Active'),(9,'67','ARTEE FABRICS & HOME','1801 AIRLINE DRIVE SUITE A','METAIRIE','LA','70001','504-302-2160','metairiearteefabrics@gmail.com','Active'),(10,'02','ARTEE FABRICS AND HOME','7 DUNNELL LANE EAST','PAWTUCKET','RI','02860','978-212-2683','Arti.mehta@gmail.com','Active'),(11,'03','PRINTER\'S ALLEY','736 S MAIN STREET','BURLINGTON','NC','27215','336-270-4812','burlingtonwarehouse@arteefabricsandhome.com, gran4me@gmail.com','Active');
/*!40000 ALTER TABLE `stores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'minimum_freight_charge','15.00');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Super Admin','Logistics Admin','Store User') NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `remember_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_users_store` (`store_id`),
  CONSTRAINT `fk_users_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,NULL,'Super Admin','admin@arteefabrics.com','555-0100','admin','$2y$10$7Aw.QmmwPPB.nQe2IjFOa.CpVUADH3fI4IasGywFh7bg.h309Lg9.','Super Admin','Active',NULL,'2026-06-13 14:15:16','2026-06-13 14:15:16'),(2,NULL,'Logistics Admin','logistics@arteefabrics.com','555-0200','logistics','$2y$10$KY5JHIgruv4zgJHV/iNibu.fgb9ywvvsMM3MBpBvcW4eE94Ae78wy','Logistics Admin','Active',NULL,'2026-06-13 14:15:16','2026-06-13 14:15:16'),(3,1,'ARTEE FABRICS & HOME Store User','jfreeman.aci@gmail.com','757-966-1808','store_78','$2y$10$1UvoWDdMAnPfQy6Bucylj.NifE/ppJjEzWZjIRoikPKIXbUYljDjW','Store User','Active',NULL,'2026-06-13 14:15:16','2026-06-13 14:15:16'),(4,2,'PRINTER\'S ALLEY Store User','printersalleyraleigh@gmail.com','919-781-1777','store_82','$2y$10$lcSXiA103n1OuPf8RBLfu./1EV9sab1KWdbOiDKznhW5X9kEXaUL.','Store User','Active',NULL,'2026-06-13 14:15:16','2026-06-13 14:15:16'),(5,3,'ARTEE FABRICS & HOME Store User','wilmington.aci@gmail.com','910-686-2950','store_63','$2y$10$VPYs9YkPNmJxRGGiITz9U.Zdc.XbA0c6OJCcDOmGhUYFnZmMnRm42','Store User','Active',NULL,'2026-06-13 14:15:16','2026-06-13 14:15:16'),(6,4,'ARTEE FABRICS & HOME Store User','jfreeman.aci@gmail.com','757-963-7820','store_64','$2y$10$2.vCCo7P89Kn6nAcJ0q5buB3GJEjFqFcuKalOwA3Vjf6g61d5VYd6','Store User','Active',NULL,'2026-06-13 14:15:16','2026-06-13 14:15:16'),(7,5,'GOOD GOODS Store User','goodgoodsgirls@gmail.com','203-655-8100','store_73','$2y$10$I1eWIGiEU9bEA90jCF5fVOpmuVSTktTl1zkMM7.Pk2bN13Cm65N0m','Store User','Active',NULL,'2026-06-13 14:15:16','2026-06-13 14:15:16'),(8,6,'RAGS & RICHES Store User','ragsandriches@comcast.net','802-862-3288','store_71','$2y$10$7Onoi.vx9ouJvxOED7Z9Uesf.cyV6PqcvNGP1UYwG3rBtVCfddzr2','Store User','Active',NULL,'2026-06-13 14:15:16','2026-06-13 14:15:16'),(9,7,'ARTEE FABRICS & HOME Store User','richmond@arteefabricsandhome.com','804-285-9591','store_62','$2y$10$qCQ/qHLc9mRDaf7GHDjruuOYfKkIG4v5AU/2doZxrkxnRHz42SIBW','Store User','Active',NULL,'2026-06-13 14:15:16','2026-06-13 14:15:16'),(10,8,'ARTEE FABRICS & HOME Store User','cincinnati@arteefabricsandhome.com','513-683-5400','store_70','$2y$10$Ai3sO3vcOVBxtvCaJq363OVu.D67sQbPcmbT2mQz/Yg8VkUUjrQtW','Store User','Active',NULL,'2026-06-13 14:15:17','2026-06-13 14:15:17'),(11,9,'ARTEE FABRICS & HOME Store User','metairiearteefabrics@gmail.com','504-302-2160','store_67','$2y$10$hox.a8flnAZ0vmzSAcD9quImQP9HDaXDZfua8RKIdEtrJLOOnE.cC','Store User','Active',NULL,'2026-06-13 14:15:17','2026-06-13 14:15:17'),(12,10,'ARTEE FABRICS AND HOME Store User','Arti.mehta@gmail.com','978-212-2683','store_02','$2y$10$2Khh8a9qpJWKn5T23LZknOcXLBghInudplwdLqlHxXNodpzb6kO5C','Store User','Active',NULL,'2026-06-13 14:15:17','2026-06-13 14:15:17'),(13,11,'PRINTER\'S ALLEY Store User','burlingtonwarehouse@arteefabricsandhome.com','336-270-4812','store_03','$2y$10$BECPvxOaL8XI0qUYNwvvgeAl.9c99/o39AHO2VMZGYBgsQAnq967y','Store User','Active',NULL,'2026-06-13 14:15:17','2026-06-13 14:15:17');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-18 18:34:37
