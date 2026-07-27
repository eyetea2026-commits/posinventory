
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
DROP TABLE IF EXISTS `ActivityLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ActivityLog` (
  `ActivityLogID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` bigint(20) unsigned DEFAULT NULL,
  `Action` varchar(50) NOT NULL,
  `Description` varchar(255) NOT NULL,
  `DateRecorded` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ActivityLogID`),
  KEY `activitylog_userid_foreign` (`UserID`),
  CONSTRAINT `activitylog_userid_foreign` FOREIGN KEY (`UserID`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `ActivityLog` WRITE;
/*!40000 ALTER TABLE `ActivityLog` DISABLE KEYS */;
INSERT INTO `ActivityLog` VALUES
(1,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 07:44:30'),
(2,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 08:07:24'),
(3,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 08:08:55'),
(4,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 08:10:50'),
(5,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 08:12:26'),
(6,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 08:13:42'),
(7,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 08:15:23'),
(8,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 08:17:38'),
(9,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 08:19:14'),
(10,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 08:20:01'),
(11,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 08:21:08'),
(12,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 08:26:45'),
(13,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 08:28:59'),
(14,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 10:18:03'),
(15,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 10:19:07'),
(16,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 10:20:34'),
(17,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 10:22:20'),
(18,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 10:22:57'),
(19,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 10:23:23'),
(20,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 10:25:02'),
(21,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 10:28:16'),
(22,NULL,'discount.created','Created discount policy \"10.92%\"','2026-07-07 10:28:26'),
(23,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 10:29:04'),
(24,NULL,'product.created','Added product \"QA Product 1783391345280\"','2026-07-07 10:29:08'),
(25,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 10:30:28'),
(26,NULL,'product.created','Added product \"QA Product 1783391429325\"','2026-07-07 10:30:32'),
(27,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 10:31:02'),
(28,NULL,'product.created','Added product \"QA Debug Product 1783391463244\"','2026-07-07 10:31:03'),
(29,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 10:33:27'),
(30,NULL,'product.created','Added product \"QA Product 1783391607885\"','2026-07-07 10:33:31'),
(31,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 10:34:09'),
(32,NULL,'discount.created','Created discount policy \"87.58%\"','2026-07-07 10:34:13'),
(33,NULL,'product.created','Added product \"QA Standalone Prod 1783391650680\"','2026-07-07 10:34:16'),
(34,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 10:52:09'),
(35,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 10:53:01'),
(36,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 10:56:02'),
(37,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 11:01:01'),
(38,NULL,'auth.login','\"__layout_qa_tmp_cashier\" logged in (Cashier)','2026-07-07 11:08:31'),
(39,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 11:30:25'),
(40,NULL,'product.created','Added product \"QA Barcode Product 1783395026687\"','2026-07-07 11:30:30'),
(41,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 11:31:13'),
(42,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 11:31:57'),
(43,NULL,'product.created','Added product \"QA Standalone Barcode 1783395118339\"','2026-07-07 11:31:59'),
(44,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 11:33:26'),
(45,NULL,'auth.login','\"__layout_qa_tmp\" logged in (Admin)','2026-07-07 11:48:05'),
(46,3,'discount.created','Created discount policy \"20%\"','2026-07-09 07:29:25'),
(47,3,'product.created','Added product \"Bullet Type\"','2026-07-10 19:45:38'),
(48,3,'supplier.created','Added supplier \"Valadua\"','2026-07-10 19:46:35'),
(49,3,'supplier.updated','Updated supplier \"Valadua\"','2026-07-10 19:46:40'),
(50,3,'stock.received','Received 50 x \"Bullet Type\" from \"Valadua\"','2026-07-10 19:47:25'),
(51,3,'product.created','Added product \"DVR\"','2026-07-13 12:33:21'),
(52,3,'stock.received','Received 50 x \"DVR\" from \"Valadua\"','2026-07-13 12:36:04'),
(53,3,'stock.received','Received 100 x \"DVR\" from \"Valadua\"','2026-07-13 12:37:05'),
(54,8,'return.requested','Requested refund #1 for 1 x \"DVR\" (Txn #5)','2026-07-20 07:25:59'),
(55,3,'damage.created_from_return','Created damage record #1 for 1 x \"DVR\" from return #1 (Damaged Product)','2026-07-20 07:26:15'),
(56,3,'return.approved','Approved refund #1 for 1 x \"DVR\" (Txn #5)','2026-07-20 07:26:15'),
(57,8,'return.refund_processed','Processed refund #1 — ₱1,250.00 via cash (Txn #5)','2026-07-20 07:26:32'),
(58,3,'product.created','Added product \"Camera\"','2026-07-20 12:27:59'),
(59,3,'product.updated','Updated product \"SATELITE\"','2026-07-20 12:29:01'),
(60,3,'discount.created','Created discount policy \"50%\"','2026-07-20 12:31:28'),
(61,3,'stock.received','Received 50 x \"SATELITE\" from \"Valadua\"','2026-07-20 12:33:49'),
(62,10,'return.requested','Requested refund #2 for 1 x \"Bullet Type\" (Txn #6)','2026-07-20 12:45:07'),
(63,3,'damage.created_from_return','Created damage record #2 for 1 x \"Bullet Type\" from return #2 (Factory Defect)','2026-07-20 12:45:23'),
(64,3,'return.approved','Approved refund #2 for 1 x \"Bullet Type\" (Txn #6)','2026-07-20 12:45:23'),
(65,3,'supplier.created','Added supplier \"Satur\"','2026-07-20 13:00:48'),
(66,NULL,'auth.login_failed','Failed login attempt for username \"Administrator\" from 203.0.113.10','2026-07-27 09:14:30');
/*!40000 ALTER TABLE `ActivityLog` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `Billing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Billing` (
  `BillingID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `CustomerName` varchar(100) NOT NULL,
  `VatApplied` varchar(20) NOT NULL,
  `BillingAmount` decimal(10,2) NOT NULL,
  `BillingDate` date NOT NULL,
  `DiscountID` int(10) unsigned DEFAULT NULL,
  `SalesTransactionID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`BillingID`),
  KEY `billing_salestransactionid_foreign` (`SalesTransactionID`),
  KEY `billing_discountid_foreign` (`DiscountID`),
  CONSTRAINT `billing_discountid_foreign` FOREIGN KEY (`DiscountID`) REFERENCES `Discount` (`DiscountID`) ON DELETE SET NULL,
  CONSTRAINT `billing_salestransactionid_foreign` FOREIGN KEY (`SalesTransactionID`) REFERENCES `SalesTransaction` (`SalesTransactionID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `Billing` WRITE;
/*!40000 ALTER TABLE `Billing` DISABLE KEYS */;
INSERT INTO `Billing` VALUES
(1,'Walk-in Customer','12%',6720.00,'2026-07-10',NULL,1),
(2,'Walk-in Customer','12%',10080.00,'2026-07-10',NULL,2),
(3,'Walk-in Customer','12%',15400.00,'2026-07-13',NULL,3),
(4,'Walk-in Customer','12%',196000.00,'2026-07-13',NULL,4),
(5,'Walk-in Customer','12%',1400.00,'2026-07-20',NULL,5),
(6,'Walk-in Customer','12%',1344.00,'2026-07-20',909,6),
(7,'Walk-in Customer','12%',1400.00,'2026-07-25',NULL,7);
/*!40000 ALTER TABLE `Billing` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `Brand`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Brand` (
  `BrandID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `BrandName` varchar(100) NOT NULL,
  PRIMARY KEY (`BrandID`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `Brand` WRITE;
/*!40000 ALTER TABLE `Brand` DISABLE KEYS */;
INSERT INTO `Brand` VALUES
(1,'Hikvision');
/*!40000 ALTER TABLE `Brand` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `Category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Category` (
  `CategoryID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `CategoryName` varchar(100) NOT NULL,
  `Description` text DEFAULT NULL,
  PRIMARY KEY (`CategoryID`),
  UNIQUE KEY `category_categoryname_unique` (`CategoryName`)
) ENGINE=InnoDB AUTO_INCREMENT=944 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `Category` WRITE;
/*!40000 ALTER TABLE `Category` DISABLE KEYS */;
INSERT INTO `Category` VALUES
(1,'CCTV','Camera'),
(2,'DVR','Black'),
(932,'CCTV Cameras','Security surveillance cameras.'),
(933,'PTZ Cameras','Cameras with pan, tilt, and zoom.'),
(934,'ColorVu Cameras','Full-color day and night cameras.'),
(936,'NVR','Records video from IP cameras.'),
(937,'HDD','Storage for video recordings.'),
(938,'SSD','Fast and reliable data storage.'),
(939,'PoE Switch','Provides power and network through one cable.'),
(940,'Power Supply','Supplies power to devices.'),
(941,'UPS','Backup power during outages.'),
(942,'Networking Tools','Tools for network installation and maintenance.'),
(943,'CVR','Camera');
/*!40000 ALTER TABLE `Category` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `Customer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Customer` (
  `CustomerID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `CustomerName` varchar(150) NOT NULL,
  `ContactNumber` varchar(50) DEFAULT NULL,
  `Email` varchar(150) DEFAULT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`CustomerID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `Customer` WRITE;
/*!40000 ALTER TABLE `Customer` DISABLE KEYS */;
/*!40000 ALTER TABLE `Customer` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `DamagedProduct`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `DamagedProduct` (
  `DamageID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Quantity` int(11) NOT NULL,
  `Description` varchar(500) NOT NULL,
  `Status` varchar(25) NOT NULL DEFAULT 'pending',
  `DamageType` varchar(50) DEFAULT NULL,
  `InspectionNotes` text DEFAULT NULL,
  `WarehouseLocation` varchar(100) DEFAULT NULL,
  `Remarks` text DEFAULT NULL,
  `ResolvedBy` bigint(20) unsigned DEFAULT NULL,
  `ResolvedDate` date DEFAULT NULL,
  `DateRecorded` date NOT NULL,
  `ProductID` int(10) unsigned NOT NULL,
  `SalesReturnID` int(10) unsigned DEFAULT NULL,
  `SupplierID` int(10) unsigned DEFAULT NULL,
  `PurchaseOrderID` int(10) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`DamageID`),
  KEY `damagedproduct_productid_foreign` (`ProductID`),
  KEY `damagedproduct_supplierid_foreign` (`SupplierID`),
  KEY `damagedproduct_purchaseorderid_foreign` (`PurchaseOrderID`),
  KEY `damagedproduct_resolvedby_foreign` (`ResolvedBy`),
  KEY `damagedproduct_status_index` (`Status`),
  KEY `damagedproduct_daterecorded_index` (`DateRecorded`),
  KEY `damagedproduct_deleted_at_index` (`deleted_at`),
  KEY `damagedproduct_salesreturnid_foreign` (`SalesReturnID`),
  CONSTRAINT `damagedproduct_productid_foreign` FOREIGN KEY (`ProductID`) REFERENCES `Product` (`ProductID`) ON DELETE CASCADE,
  CONSTRAINT `damagedproduct_purchaseorderid_foreign` FOREIGN KEY (`PurchaseOrderID`) REFERENCES `PurchaseOrder` (`PurchaseOrderID`) ON DELETE SET NULL,
  CONSTRAINT `damagedproduct_resolvedby_foreign` FOREIGN KEY (`ResolvedBy`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `damagedproduct_salesreturnid_foreign` FOREIGN KEY (`SalesReturnID`) REFERENCES `SalesReturn` (`SalesReturnID`) ON DELETE SET NULL,
  CONSTRAINT `damagedproduct_supplierid_foreign` FOREIGN KEY (`SupplierID`) REFERENCES `Supplier` (`SupplierID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `DamagedProduct` WRITE;
/*!40000 ALTER TABLE `DamagedProduct` DISABLE KEYS */;
INSERT INTO `DamagedProduct` VALUES
(1,1,'Customer return — Damaged Product (Return #1, Receipt RCT-000005)','for_supplier_return','damaged_product',NULL,NULL,NULL,NULL,NULL,'2026-07-20',2,1,NULL,NULL,NULL),
(2,1,'Customer return — Factory Defect (Return #2, Receipt RCT-000006)','for_supplier_return','factory_defect',NULL,NULL,NULL,NULL,NULL,'2026-07-20',1,2,NULL,NULL,NULL);
/*!40000 ALTER TABLE `DamagedProduct` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `Discount`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Discount` (
  `DiscountID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `DiscountRate` varchar(20) NOT NULL,
  PRIMARY KEY (`DiscountID`),
  UNIQUE KEY `discount_discountrate_unique` (`DiscountRate`)
) ENGINE=InnoDB AUTO_INCREMENT=912 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `Discount` WRITE;
/*!40000 ALTER TABLE `Discount` DISABLE KEYS */;
INSERT INTO `Discount` VALUES
(909,'20'),
(911,'50');
/*!40000 ALTER TABLE `Discount` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `Inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Inventory` (
  `InventoryID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Quantity` int(11) NOT NULL,
  `ReorderThreshold` int(11) DEFAULT 50,
  `Status` varchar(20) NOT NULL,
  `ProductID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`InventoryID`),
  KEY `inventory_productid_foreign` (`ProductID`),
  CONSTRAINT `inventory_productid_foreign` FOREIGN KEY (`ProductID`) REFERENCES `Product` (`ProductID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=954 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `Inventory` WRITE;
/*!40000 ALTER TABLE `Inventory` DISABLE KEYS */;
INSERT INTO `Inventory` VALUES
(1,34,10,'Available',1),
(2,3,10,'Low Stock',2),
(927,49,10,'Available',938),
(928,0,10,'Out of Stock',939),
(929,0,10,'Out of Stock',940),
(930,0,10,'Out of Stock',941),
(931,0,10,'Out of Stock',942),
(932,0,10,'Out of Stock',943),
(933,0,10,'Out of Stock',944),
(934,0,10,'Out of Stock',945),
(935,0,10,'Out of Stock',946),
(936,0,10,'Out of Stock',947),
(937,0,10,'Out of Stock',948),
(938,0,10,'Out of Stock',949),
(939,0,10,'Out of Stock',950),
(940,0,10,'Out of Stock',951),
(941,0,10,'Out of Stock',952),
(942,0,10,'Out of Stock',953),
(943,0,10,'Out of Stock',954),
(944,0,10,'Out of Stock',955),
(945,0,10,'Out of Stock',956),
(946,0,10,'Out of Stock',957),
(947,0,10,'Out of Stock',958),
(948,0,10,'Out of Stock',959),
(949,0,10,'Out of Stock',960),
(950,0,10,'Out of Stock',961),
(951,0,10,'Out of Stock',962),
(952,0,10,'Out of Stock',963),
(953,50,10,'Available',964);
/*!40000 ALTER TABLE `Inventory` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `Payment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Payment` (
  `PaymentID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `PaymentAmount` decimal(10,2) NOT NULL,
  `PaymentMethod` varchar(50) NOT NULL,
  `ReceiptNumber` varchar(50) NOT NULL,
  `BillingID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`PaymentID`),
  UNIQUE KEY `payment_receiptnumber_unique` (`ReceiptNumber`),
  KEY `payment_billingid_foreign` (`BillingID`),
  CONSTRAINT `payment_billingid_foreign` FOREIGN KEY (`BillingID`) REFERENCES `Billing` (`BillingID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `Payment` WRITE;
/*!40000 ALTER TABLE `Payment` DISABLE KEYS */;
INSERT INTO `Payment` VALUES
(1,10000.00,'cash','RCT-000001',1),
(2,20000.00,'cash','RCT-000002',2),
(3,15500.00,'cash','RCT-000003',3),
(4,200000.00,'cash','RCT-000004',4),
(5,1500.00,'cash','RCT-000005',5),
(6,1500.00,'cash','RCT-000006',6),
(7,1500.00,'cash','RCT-000007',7);
/*!40000 ALTER TABLE `Payment` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `Product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Product` (
  `ProductID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ProductName` varchar(100) NOT NULL,
  `Model` varchar(100) NOT NULL,
  `Description` text DEFAULT NULL,
  `Price` decimal(10,2) NOT NULL,
  `CostPrice` decimal(10,2) DEFAULT 0.00,
  `BrandID` int(10) unsigned DEFAULT NULL,
  `CategoryID` int(10) unsigned NOT NULL,
  `SKU` varchar(100) DEFAULT NULL,
  `Barcode` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`ProductID`),
  UNIQUE KEY `product_sku_unique` (`SKU`),
  UNIQUE KEY `product_barcode_unique` (`Barcode`),
  KEY `product_brandid_foreign` (`BrandID`),
  KEY `product_categoryid_foreign` (`CategoryID`),
  CONSTRAINT `product_brandid_foreign` FOREIGN KEY (`BrandID`) REFERENCES `Brand` (`BrandID`) ON DELETE CASCADE,
  CONSTRAINT `product_categoryid_foreign` FOREIGN KEY (`CategoryID`) REFERENCES `Category` (`CategoryID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=965 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `Product` WRITE;
/*!40000 ALTER TABLE `Product` DISABLE KEYS */;
INSERT INTO `Product` VALUES
(1,'Bullet Type','48104023904','Dome 360 Camera',1500.00,1200.00,NULL,1,NULL,'894852925'),
(2,'DVR','943278924','Camera',1250.00,1200.00,NULL,1,NULL,'0987656782'),
(938,'Speed Dome PTZ','DS-2DE5225IW-AE',NULL,26505.45,14578.00,NULL,933,NULL,'3674673563635'),
(939,'Mini PT Dome Camera','DS-2DE3A404IW-DE(S6)','3-inch 4 MP 4x Zoom IR Mini PT Dome Net Cam',9636.36,5300.00,1,933,NULL,'701241009049'),
(940,'NVR 4CHA','DS-7604NI-K1','4-ch 1U 4K NVR',5383.64,2961.00,1,936,NULL,'297112731238'),
(941,'NVR 8CHA','DS-7608NI-K2','8-ch 1U 4K NVR 2 SATA',9736.36,5355.00,1,936,NULL,'981022680140'),
(942,'NVR 8CHA','DS-7608NI-Q2','8-ch 1U 4K NVR',6974.55,3836.00,1,936,NULL,'470120289343'),
(943,'NVR 16CH','DS-7616NI-K2','16-ch 1U K Series AcuSense 4K NVR',10047.27,5526.00,1,936,NULL,'206404235013'),
(944,'NVR 32CH','DS-7732NI-K4','32-ch 1.5U 4K NVR',27500.00,15125.00,1,936,NULL,'376599536931'),
(945,'DVR 4CHA','DS-7204HGHI-K1','4-ch 1080p Lite 1U H.265 DVR',2790.91,1535.00,1,2,NULL,'241323612011'),
(946,'DVR 8CHA','DS-7208HGHI-K1(S)','8-ch 1080p Lite 1U H.265 DVR',3909.09,2150.00,1,2,NULL,'774368778474'),
(947,'DVR 16CHA','DS-7216HGHI-K1','16-ch 1080p Lite 1U H.265 DVR',6690.91,3680.00,1,2,NULL,'174656757762'),
(948,'DVR 8CHA AcuSense','IDS-7208HQHI-M1/S','8-ch 1080p 1U H.265 AcuSense DVR',6454.55,3550.00,1,2,NULL,'771030822532'),
(949,'DVR 16CHA AcuSense','IDS-7216HQHI-M1/S','16-ch 1080p 1U H.265 AcuSense DVR',11909.09,6550.00,1,2,NULL,'420753750668'),
(950,'DVR 32CHA AcuSense','IDS-7232HQHI-M2/S','32-ch 1080p 1U H.265 AcuSense DVR',26000.00,14300.00,1,2,NULL,'179800231653'),
(951,'Bullet 2MP','DS-2CE16D0T-ITPFS','2 MP Audio Fixed Mini Bullet Camera',1481.82,815.00,NULL,932,NULL,'690088687919'),
(952,'Dome 2MP','DS-2CE76D0T-ITPFS','2 MP Audio Indoor Fixed Turret Camera',1427.27,785.00,1,932,NULL,'293212242752'),
(953,'Bullet 2MP','DS-2CE16D0T-IRPF','2 MP Fixed Mini Bullet Camera',1118.18,615.00,1,932,NULL,'167157369847'),
(954,'Dome 2MP','DS-2CE56D0T-IRPF','2MP Indoor Fixed Turret Camera',1009.09,555.00,1,932,NULL,'197661944104'),
(955,'Dome 2MP','DS-2CE57D3T-VPITF','2 MP Ultra Low Light Vandal Fixed Dome Camera',2378.18,1308.00,1,932,NULL,'749564500289'),
(956,'Bullet 2MP','DS-2CE16D0T-IPF','2 MP Fixed Mini Bullet Camera',1036.36,570.00,1,932,NULL,'573424304020'),
(957,'Dome 2MP','DS-2CE56D0T-IPF','2 MP Indoor Fixed Turret Camera',925.45,509.00,1,932,NULL,'182486501888'),
(958,'Dome 5MP ColorVu','DS-2CE72HFT-F','5 MP ColorVu Fixed Turret Camera',3572.73,1965.00,1,934,NULL,'176904748531'),
(959,'Bullet 5MP ColorVu','DS-2CE10HFT-F','5 MP ColorVu Fixed Mini Bullet Camera',3363.64,1850.00,1,934,NULL,'490556097894'),
(960,'Bullet 2MP ColorVu','DS-2CE12DF3T-PIRXOS','2 MP ColorVu PIR Siren Audio Fixed Bullet Camera',5090.91,2800.00,1,934,NULL,'336149279660'),
(961,'Bullet 3K ColorVu','DS-2CE10KF0T-FS','3K ColorVu Audio Fixed Mini Bullet Camera',3090.91,1700.00,1,934,NULL,'844714785321'),
(962,'Bullet 2MP ColorVu','DS-2CE10DF3T-PF','2 MP ColorVu Fixed Mini Bullet Camera',2254.55,1240.00,1,934,NULL,'921663885789'),
(963,'Dome 2MP ColorVu','DS-2CE70DF3T-PFS','2 MP ColorVu Indoor Audio Fixed Turret Camera',2218.18,1220.00,1,934,NULL,'396343306807'),
(964,'SATELITE','9876543','cctv',2181.82,1200.00,NULL,934,NULL,'98765432');
/*!40000 ALTER TABLE `Product` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `PurchaseOrder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `PurchaseOrder` (
  `PurchaseOrderID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `PurchaseDate` date NOT NULL,
  `ExpectedDeliveryDate` date DEFAULT NULL,
  `Status` varchar(20) NOT NULL,
  `SupplierID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`PurchaseOrderID`),
  KEY `purchaseorder_supplierid_foreign` (`SupplierID`),
  CONSTRAINT `purchaseorder_supplierid_foreign` FOREIGN KEY (`SupplierID`) REFERENCES `Supplier` (`SupplierID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `PurchaseOrder` WRITE;
/*!40000 ALTER TABLE `PurchaseOrder` DISABLE KEYS */;
/*!40000 ALTER TABLE `PurchaseOrder` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `PurchaseOrderItem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `PurchaseOrderItem` (
  `PurchaseOrderItemID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Quantity` int(11) NOT NULL,
  `PurchaseOrderID` int(10) unsigned NOT NULL,
  `ProductID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`PurchaseOrderItemID`),
  KEY `purchaseorderitem_purchaseorderid_foreign` (`PurchaseOrderID`),
  KEY `purchaseorderitem_productid_foreign` (`ProductID`),
  CONSTRAINT `purchaseorderitem_productid_foreign` FOREIGN KEY (`ProductID`) REFERENCES `Product` (`ProductID`) ON DELETE CASCADE,
  CONSTRAINT `purchaseorderitem_purchaseorderid_foreign` FOREIGN KEY (`PurchaseOrderID`) REFERENCES `PurchaseOrder` (`PurchaseOrderID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `PurchaseOrderItem` WRITE;
/*!40000 ALTER TABLE `PurchaseOrderItem` DISABLE KEYS */;
/*!40000 ALTER TABLE `PurchaseOrderItem` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `Replacement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Replacement` (
  `ReplacementID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `SalesReturnID` int(10) unsigned NOT NULL,
  `ReplacementProductID` int(10) unsigned NOT NULL,
  `Quantity` int(11) NOT NULL,
  `ProcessedBy` bigint(20) unsigned DEFAULT NULL,
  `ReplacementDate` date NOT NULL,
  `SlipNumber` varchar(50) DEFAULT NULL,
  `Notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ReplacementID`),
  UNIQUE KEY `replacement_salesreturnid_unique` (`SalesReturnID`),
  UNIQUE KEY `replacement_slipnumber_unique` (`SlipNumber`),
  KEY `replacement_replacementproductid_foreign` (`ReplacementProductID`),
  KEY `replacement_processedby_foreign` (`ProcessedBy`),
  CONSTRAINT `replacement_processedby_foreign` FOREIGN KEY (`ProcessedBy`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `replacement_replacementproductid_foreign` FOREIGN KEY (`ReplacementProductID`) REFERENCES `Product` (`ProductID`),
  CONSTRAINT `replacement_salesreturnid_foreign` FOREIGN KEY (`SalesReturnID`) REFERENCES `SalesReturn` (`SalesReturnID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `Replacement` WRITE;
/*!40000 ALTER TABLE `Replacement` DISABLE KEYS */;
/*!40000 ALTER TABLE `Replacement` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `Role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Role` (
  `RoleID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `RoleName` varchar(50) NOT NULL,
  PRIMARY KEY (`RoleID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `Role` WRITE;
/*!40000 ALTER TABLE `Role` DISABLE KEYS */;
/*!40000 ALTER TABLE `Role` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `SalesItem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `SalesItem` (
  `SalesItemID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Quantity` int(11) NOT NULL,
  `UnitPrice` decimal(10,2) NOT NULL,
  `ProductID` int(10) unsigned NOT NULL,
  `SalesTransactionID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`SalesItemID`),
  KEY `salesitem_productid_foreign` (`ProductID`),
  KEY `salesitem_salestransactionid_foreign` (`SalesTransactionID`),
  CONSTRAINT `salesitem_productid_foreign` FOREIGN KEY (`ProductID`) REFERENCES `Product` (`ProductID`) ON DELETE CASCADE,
  CONSTRAINT `salesitem_salestransactionid_foreign` FOREIGN KEY (`SalesTransactionID`) REFERENCES `SalesTransaction` (`SalesTransactionID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `SalesItem` WRITE;
/*!40000 ALTER TABLE `SalesItem` DISABLE KEYS */;
INSERT INTO `SalesItem` VALUES
(1,4,1500.00,1,1),
(2,6,1500.00,1,2),
(3,5,1250.00,2,3),
(4,5,1500.00,1,3),
(5,140,1250.00,2,4),
(6,1,1250.00,2,5),
(7,1,1500.00,1,6),
(8,1,1250.00,2,7);
/*!40000 ALTER TABLE `SalesItem` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `SalesReturn`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `SalesReturn` (
  `SalesReturnID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `SalesTransactionID` int(10) unsigned NOT NULL,
  `ProductID` int(10) unsigned NOT NULL,
  `Quantity` int(11) NOT NULL,
  `Reason` varchar(255) NOT NULL,
  `Remarks` varchar(500) DEFAULT NULL,
  `ReturnType` varchar(20) NOT NULL DEFAULT 'refund',
  `Status` varchar(20) NOT NULL DEFAULT 'pending',
  `DeclineReason` varchar(255) DEFAULT NULL,
  `ReturnDate` date NOT NULL,
  `ApprovedBy` bigint(20) unsigned DEFAULT NULL,
  `ProcessedBy` bigint(20) unsigned DEFAULT NULL,
  `StaffID` int(10) unsigned DEFAULT NULL,
  `CustomerName` varchar(100) DEFAULT NULL,
  `RefundMethod` varchar(20) DEFAULT NULL,
  `RefundAmount` decimal(10,2) DEFAULT NULL,
  `RefundAccountNumber` varchar(50) DEFAULT NULL,
  `RefundDate` date DEFAULT NULL,
  PRIMARY KEY (`SalesReturnID`),
  KEY `salesreturn_salestransactionid_foreign` (`SalesTransactionID`),
  KEY `salesreturn_productid_foreign` (`ProductID`),
  KEY `salesreturn_approvedby_foreign` (`ApprovedBy`),
  KEY `salesreturn_processedby_foreign` (`ProcessedBy`),
  KEY `salesreturn_staffid_foreign` (`StaffID`),
  KEY `salesreturn_status_index` (`Status`),
  CONSTRAINT `salesreturn_approvedby_foreign` FOREIGN KEY (`ApprovedBy`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salesreturn_processedby_foreign` FOREIGN KEY (`ProcessedBy`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salesreturn_productid_foreign` FOREIGN KEY (`ProductID`) REFERENCES `Product` (`ProductID`) ON DELETE CASCADE,
  CONSTRAINT `salesreturn_salestransactionid_foreign` FOREIGN KEY (`SalesTransactionID`) REFERENCES `SalesTransaction` (`SalesTransactionID`) ON DELETE CASCADE,
  CONSTRAINT `salesreturn_staffid_foreign` FOREIGN KEY (`StaffID`) REFERENCES `Staff` (`StaffID`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `SalesReturn` WRITE;
/*!40000 ALTER TABLE `SalesReturn` DISABLE KEYS */;
INSERT INTO `SalesReturn` VALUES
(1,5,2,1,'Damaged Product',NULL,'refund','processed',NULL,'2026-07-20',3,8,2,'Walk-in Customer','cash',1250.00,NULL,'2026-07-20'),
(2,6,1,1,'Factory Defect',NULL,'refund','approved',NULL,'2026-07-20',3,NULL,3,'Walk-in Customer',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `SalesReturn` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `SalesTransaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `SalesTransaction` (
  `SalesTransactionID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `CustomerName` varchar(100) NOT NULL,
  `SalesTransactionDate` datetime NOT NULL,
  `StaffID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`SalesTransactionID`),
  KEY `salestransaction_staffid_foreign` (`StaffID`),
  CONSTRAINT `salestransaction_staffid_foreign` FOREIGN KEY (`StaffID`) REFERENCES `Staff` (`StaffID`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `SalesTransaction` WRITE;
/*!40000 ALTER TABLE `SalesTransaction` DISABLE KEYS */;
INSERT INTO `SalesTransaction` VALUES
(1,'Walk-in Customer','2026-07-10 19:48:36',1),
(2,'Walk-in Customer','2026-07-10 20:51:19',1),
(3,'Walk-in Customer','2026-07-13 12:41:05',1),
(4,'Walk-in Customer','2026-07-13 12:44:14',1),
(5,'Walk-in Customer','2026-07-20 07:25:36',2),
(6,'Walk-in Customer','2026-07-20 12:42:45',3),
(7,'Walk-in Customer','2026-07-25 07:37:06',2);
/*!40000 ALTER TABLE `SalesTransaction` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `Staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Staff` (
  `StaffID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `FirstName` varchar(50) NOT NULL,
  `MiddleName` varchar(50) NOT NULL,
  `LastName` varchar(50) NOT NULL,
  `ContactNumber` varchar(20) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Age` varchar(10) NOT NULL,
  `Gender` varchar(20) NOT NULL,
  `UserID` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`StaffID`),
  KEY `staff_userid_foreign` (`UserID`),
  CONSTRAINT `staff_userid_foreign` FOREIGN KEY (`UserID`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `Staff` WRITE;
/*!40000 ALTER TABLE `Staff` DISABLE KEYS */;
INSERT INTO `Staff` VALUES
(1,'Staff','One','User','09000000001','cashier3@example.com','24','Male',9),
(2,'Staff','Two','User','09000000002','cashier2@example.com','21','Male',8),
(3,'Staff','Three','User','09000000003','cashier5@example.com','21','Male',10);
/*!40000 ALTER TABLE `Staff` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `StockAdjustment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `StockAdjustment` (
  `AdjustmentID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `QuantityAdjust` int(11) NOT NULL,
  `Reason` varchar(255) NOT NULL,
  `Date` date NOT NULL,
  `ProductID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`AdjustmentID`),
  KEY `stockadjustment_productid_foreign` (`ProductID`),
  CONSTRAINT `stockadjustment_productid_foreign` FOREIGN KEY (`ProductID`) REFERENCES `Product` (`ProductID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `StockAdjustment` WRITE;
/*!40000 ALTER TABLE `StockAdjustment` DISABLE KEYS */;
/*!40000 ALTER TABLE `StockAdjustment` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `StockReceiving`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `StockReceiving` (
  `ReceivingID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Quantity` int(11) NOT NULL,
  `DateReceived` date NOT NULL,
  `ReceiptNumber` varchar(50) NOT NULL,
  `ProductID` int(10) unsigned NOT NULL,
  `SupplierID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`ReceivingID`),
  UNIQUE KEY `stockreceiving_receiptnumber_unique` (`ReceiptNumber`),
  KEY `stockreceiving_productid_foreign` (`ProductID`),
  KEY `stockreceiving_supplierid_foreign` (`SupplierID`),
  CONSTRAINT `stockreceiving_productid_foreign` FOREIGN KEY (`ProductID`) REFERENCES `Product` (`ProductID`) ON DELETE CASCADE,
  CONSTRAINT `stockreceiving_supplierid_foreign` FOREIGN KEY (`SupplierID`) REFERENCES `Supplier` (`SupplierID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `StockReceiving` WRITE;
/*!40000 ALTER TABLE `StockReceiving` DISABLE KEYS */;
INSERT INTO `StockReceiving` VALUES
(1,50,'2026-07-10','SUP-2026-1542',1,1),
(2,50,'2026-07-13','SUP 208546',2,1),
(3,100,'2026-07-13','SUP 208592',2,1),
(4,50,'2026-07-20','SUP 2032',964,1);
/*!40000 ALTER TABLE `StockReceiving` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `Supplier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Supplier` (
  `SupplierID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `SupplierName` varchar(100) NOT NULL,
  `ContactNumber` varchar(20) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Address` varchar(255) NOT NULL,
  PRIMARY KEY (`SupplierID`),
  UNIQUE KEY `supplier_suppliername_unique` (`SupplierName`),
  UNIQUE KEY `supplier_email_unique` (`Email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `Supplier` WRITE;
/*!40000 ALTER TABLE `Supplier` DISABLE KEYS */;
INSERT INTO `Supplier` VALUES
(1,'Valadua','09000000000','cashier3@example.com','Sample Address, Tacurong City'),
(2,'Satur','097865434','jbl@gmail.com','prk adas');
/*!40000 ALTER TABLE `Supplier` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES
(1,'2026_06_07_000001_create_cctv_express_schema',1),
(2,'2026_06_07_000002_create_sessions_table',1),
(3,'2026_06_07_000003_create_roles_and_users',1),
(4,'2026_06_07_000004_create_sales_tables',1),
(5,'2026_06_08_000001_add_active_to_users_table',1),
(6,'2026_06_09_000001_create_sales_returns_table',1),
(7,'2026_06_25_000001_create_damaged_products_table',1),
(8,'2026_06_29_000001_add_sku_barcode_to_products_table',1),
(9,'2026_06_29_000002_create_customers_table',1),
(10,'2026_06_29_000003_add_details_to_users_table',1),
(11,'2026_07_01_000001_add_product_details_columns',1),
(12,'2026_07_01_000002_make_brand_id_nullable',1),
(13,'2026_07_03_000001_add_refund_processing_fields',1),
(14,'2026_07_03_000002_add_staff_customer_to_sales_returns',1),
(15,'2026_07_04_000001_fix_sales_transaction_date_and_staff_fk',1),
(16,'2026_07_04_000002_create_password_reset_tokens_table',1),
(17,'2026_07_06_094436_add_unique_constraint_to_payment_receipt_number',2),
(18,'2026_07_06_181227_add_unique_constraints_for_duplicate_prevention',2),
(19,'2026_07_07_000001_create_activity_logs_table',2),
(20,'2026_07_11_000001_add_return_type_and_decline_fields_to_sales_returns',3),
(21,'2026_07_11_000002_add_foreign_keys_to_sales_returns',3),
(22,'2026_07_11_000003_create_replacements_table',3),
(23,'2026_07_11_000004_add_damage_workflow_fields_to_damaged_products',3),
(24,'2026_07_11_000005_drop_unit_price_from_purchase_order_items',3),
(25,'2026_07_11_000006_add_expected_delivery_date_to_purchase_orders',3),
(26,'2026_07_15_000001_add_soft_deletes_to_damaged_products',3),
(27,'2026_07_15_000002_seed_zero_percent_discount',3),
(28,'2026_07_15_000003_add_missing_indexes_and_replacement_unique_constraint',3),
(29,'2026_07_15_185657_create_jobs_table',3),
(30,'2026_07_15_185658_create_notifications_table',3),
(31,'2026_07_19_000001_add_remarks_to_sales_returns',3),
(32,'2026_07_20_000001_add_sales_return_link_to_damaged_products',3),
(33,'2026_07_20_000002_make_billing_discount_nullable',3);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES
('4a74164c-4aff-4dd6-8778-5e5b64704f2d','App\\Notifications\\ProductReceived','App\\Models\\User',3,'{\"title\":\"Product Received\",\"description\":\"Received 50 x \\\"SATELITE\\\" from \\\"Valadua\\\".\",\"url\":\"https:\\/\\/cctvexpresstacurong.com\\/admin\\/stock-receivings\",\"icon\":\"clipboard-check\",\"color\":\"success\"}',NULL,'2026-07-20 12:33:49','2026-07-20 12:33:49'),
('62fc3438-db54-4667-9bf3-0ffcef748226','App\\Notifications\\ReturnRequestApproved','App\\Models\\User',10,'{\"title\":\"Return Request Approved\",\"description\":\"Your Return Request RR-00002 has been Approved.\",\"url\":\"https:\\/\\/cctvexpresstacurong.com\\/cashier\\/refunds\",\"icon\":\"clipboard-check\",\"color\":\"success\"}',NULL,'2026-07-20 12:45:23','2026-07-20 12:45:23'),
('6ecd1481-29cb-4f65-9d13-20946201af5f','App\\Notifications\\ReturnRequestApproved','App\\Models\\User',8,'{\"title\":\"Return Request Approved\",\"description\":\"Your Return Request RR-00001 has been Approved.\",\"url\":\"https:\\/\\/cctvexpresstacurong.com\\/cashier\\/refunds\",\"icon\":\"clipboard-check\",\"color\":\"success\"}','2026-07-20 07:26:28','2026-07-20 07:26:15','2026-07-20 07:26:28'),
('cd094007-896e-437b-a74c-e6178ca520ef','App\\Notifications\\NewRefundRequest','App\\Models\\User',3,'{\"title\":\"New Refund Request\",\"description\":\"1 x \\\"Bullet Type\\\" \\u2014 refund request from Walk-in Customer.\",\"url\":\"https:\\/\\/cctvexpresstacurong.com\\/admin\\/sales-returns\",\"icon\":\"rotate-ccw\",\"color\":\"info\"}',NULL,'2026-07-20 12:45:07','2026-07-20 12:45:07'),
('fd11b256-7ef6-4bab-a854-79e4b5948567','App\\Notifications\\NewRefundRequest','App\\Models\\User',3,'{\"title\":\"New Refund Request\",\"description\":\"1 x \\\"DVR\\\" \\u2014 refund request from Walk-in Customer.\",\"url\":\"https:\\/\\/cctvexpresstacurong.com\\/admin\\/sales-returns\",\"icon\":\"rotate-ccw\",\"color\":\"info\"}','2026-07-20 07:26:11','2026-07-20 07:25:59','2026-07-20 07:26:11');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES
(1,'admin','2026-07-06 01:13:46','2026-07-06 01:13:46'),
(2,'cashier','2026-07-06 01:13:46','2026-07-06 01:13:46');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` text NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES
('2leVYcXkoSdtrjkGkN4twIy5kIONhtDBew7nuSto',NULL,'209.50.165.248','Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiNWp5TWExZFhnNFRxOHlybXJzYVVjUjYxWU40YmZaeExHVHBEUm9jVCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzc6Imh0dHBzOi8vY2N0dmV4cHJlc3N0YWN1cm9uZy5jb20vbG9naW4iO3M6NToicm91dGUiO3M6NToibG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1785077592),
('5zBRUz5z73I4WCVXRwDxW5HjbCHiDW7Y15ylCuj4',NULL,'203.0.113.10','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiMkNKSklzUm5QWmxXTkszempvR21qWE1sQXdmQnZxRVpkZjV5RERRVyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzc6Imh0dHBzOi8vY2N0dmV4cHJlc3N0YWN1cm9uZy5jb20vbG9naW4iO3M6NToicm91dGUiO3M6NToibG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1785102684),
('CRnwR0yRgnrR9di35pcQwzxtKIeeONpFp0TN4pVn',NULL,'34.118.45.20','Mozilla/5.0 (iPhone13,2; U; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/602.1.50 (KHTML, like Gecko) Version/10.0 Mobile/15E148 Safari/602.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoiY2Nlc3YzVU9tOWVOTzZjUjVYOWNoZWZGWTVSRHExaGVwbU9kWXZmQyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzc6Imh0dHBzOi8vY2N0dmV4cHJlc3N0YWN1cm9uZy5jb20vbG9naW4iO3M6NToicm91dGUiO3M6NToibG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1785082919),
('DdjEFABFqfRrBM8ZnuxPS0aAGywpHHJEn34dMNY6',NULL,'158.222.127.107','Mozilla/5.0 (X11; Linux i686; rv:109.0) Gecko/20100101 Firefox/120.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiRDlhR0NhMktRWUc1bThiM0lqd1p0MDdsRkNkZ3cycWlsYmVaNjV2ZyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHBzOi8vY2N0dmV4cHJlc3N0YWN1cm9uZy5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1785065567),
('FeES8vbjjNbQnQORtfwsi3xqmcw5FO40grUWiO5r',NULL,'2a02:4780:6:c0de::8','Go-http-client/2.0','YToyOntzOjY6Il90b2tlbiI7czo0MDoiQWpUVjFlR0xHVmc5UFJGU2UyU3NGWFhGVWU0UWJwdTdqSzhiY0k5YiI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1785113080),
('Fs64GYAgzFuXBl3P1DOup1X2tx9i7PYgLnPtB7gn',NULL,'203.0.113.10','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoid21qckhMRjVmMmV4b0VPVWdSSXIyY2dQZWs5MGdNMzJkM3NKdE5JMSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzc6Imh0dHBzOi8vY2N0dmV4cHJlc3N0YWN1cm9uZy5jb20vbG9naW4iO3M6NToicm91dGUiO3M6NToibG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1785077109),
('hREprMrpYsc3BBIx9iVuu59f6R53be0oQ6SFPM2G',NULL,'203.0.113.10','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiQU9VbVBvNkdPQ2wwanN6OUFUOUswTkhMQndRMjcxNVF6WTRLMUtMeCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NTE6Imh0dHBzOi8vc2xhdGVibHVlLXBoZWFzYW50LTQzMzYxNS5ob3N0aW5nZXJzaXRlLmNvbSI7czo1OiJyb3V0ZSI7czo3OiJ3ZWxjb21lIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1785114870),
('jiay61JvvPtDsM8EWlnNUoCNshZrg1PZGlkuvUqC',NULL,'172.238.176.14','Mozilla/5.0 (compatible; SaaSBrowserBot/1.0; +https://saasbrowser.com/bot)','YTozOntzOjY6Il90b2tlbiI7czo0MDoiT0R3WjcyWUVubW1Ya1ZWSlFXQUJrSjA3TTdHdXFnVGV0SG5lNlNJaSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHBzOi8vY2N0dmV4cHJlc3N0YWN1cm9uZy5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1785065884),
('JL41Vyah6pzWGTZ6p6MDIrjrdIBPegSmugzgIrI1',NULL,'92.222.104.207','Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)','YTozOntzOjY6Il90b2tlbiI7czo0MDoiNkEzT2FlTk81MHgzOTlVOHh6TU9WNWZvbURicUQ5QUo2TEdNalZ5eiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHBzOi8vY2N0dmV4cHJlc3N0YWN1cm9uZy5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1785096569),
('kh4GStU277gmiwllBQqKMjDnMgluhiZV4E2hVZDN',NULL,'162.216.148.0','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiTDNraDJtZkJGT1VnWGpCMXgwTlJCdHA1VEdaWXVmV1ZUdlV4WmQyRiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHBzOi8vY2N0dmV4cHJlc3N0YWN1cm9uZy5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1785054787),
('N1m1CuJ5cmBPimYt6CtEGAtgTpAAKVC5ivePdvja',NULL,'180.153.236.91','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0; 360Spider','YTozOntzOjY6Il90b2tlbiI7czo0MDoialZEMGxZWTk0c3JTRmVTWVY3ZnI4ZlQ4SGY0dEIyNlVMV1l5c2twQyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzc6Imh0dHBzOi8vY2N0dmV4cHJlc3N0YWN1cm9uZy5jb20vbG9naW4iO3M6NToicm91dGUiO3M6NToibG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1785050955),
('p06gDbSRz3kx7zltx3XCSKrTe7WIRYlci0eyNKQM',NULL,'66.249.66.67','Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)','YTozOntzOjY6Il90b2tlbiI7czo0MDoiNDhNS3dvN24wejdaYU01UlE5NnJOWDh2NzVwVzZCSWdRbTRhdVpIWSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzc6Imh0dHBzOi8vY2N0dmV4cHJlc3N0YWN1cm9uZy5jb20vbG9naW4iO3M6NToicm91dGUiO3M6NToibG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1785095339),
('qQNHSCpZeu1grZFUFFf55AcwbTGSF3EcxFfinaPk',NULL,'45.74.159.42','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiY2J2a2ltSzk5WDJoeG1YVGcxdmpGM3Nid00zUFJhWUZTUU1WSzJubiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHBzOi8vY2N0dmV4cHJlc3N0YWN1cm9uZy5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1785103032),
('rWaIEDQZzBygQxnhflqlSok6Dni8kooJQrkuJuQv',NULL,'66.249.66.192','Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.7871.128 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)','YTozOntzOjY6Il90b2tlbiI7czo0MDoiMHRLUnhCaFJUQTVHVkExdjVuY01aSGpSZjJ3NXFUT21HSnZkaUN5eiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDE6Imh0dHBzOi8vd3d3LmNjdHZleHByZXNzdGFjdXJvbmcuY29tL2xvZ2luIjtzOjU6InJvdXRlIjtzOjU6ImxvZ2luIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1785040843),
('TJOqit6J9RHtz7FDWL757zmayDkrtyQhKRIORrrU',NULL,'2a02:4780:a:c0de::fac8','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/145.0.7632.6 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiME5PNXBocjVLOTZLdGxLeHJiN0hId3FUV2VYdzUzeUUxeDZzM1VRSiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6OTQ6Imh0dHBzOi8vc2xhdGVibHVlLXBoZWFzYW50LTQzMzYxNS5ob3N0aW5nZXJzaXRlLmNvbS8/TFNDV1BfQ1RSTD1iZWZvcmVfb3B0bSZub2NhY2hlPTE3ODUxMTM0NzciO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1785113478),
('UqtoSfqHSYBCnTrjJs7NZISyyU0OcLiv8AAUJsB1',NULL,'180.153.236.250','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0; 360Spider','YTozOntzOjY6Il90b2tlbiI7czo0MDoiTUMxdWdrT1ZLdFp0MTZtMlExZXVLRlFIU1N6Nm80NFpBZU9tajZXaSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDE6Imh0dHBzOi8vd3d3LmNjdHZleHByZXNzdGFjdXJvbmcuY29tL2xvZ2luIjtzOjU6InJvdXRlIjtzOjU6ImxvZ2luIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1785063835),
('VhESxLhmDZTWg4nM7cWFbPi4LwiOg7ZhS00guBJk',NULL,'216.167.81.223','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiejFIUDFHNkFjaHFjcVN6dTB2aHQ3WjZHTnNLSkZkMnR5ZDJKV3BpYyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHBzOi8vY2N0dmV4cHJlc3N0YWN1cm9uZy5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1785108526),
('WDpkeclatWBNkkFG0DKR6Wnd0lxQT7a8u0I7Fwns',NULL,'3.81.209.104','Gaisbot/3.0 (robot@gais.cs.ccu.edu.tw; http://gais.cs.ccu.edu.tw/robot.php)','YTozOntzOjY6Il90b2tlbiI7czo0MDoiZmRPVFR1Njh0eFFtS1hqNXBBbWZPREVjRjBJQ1k0dk4xWHo2clNYdSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHBzOi8vY2N0dmV4cHJlc3N0YWN1cm9uZy5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1785084970),
('WDZyIXMrqjb78xfgWCRpEbV3ppzoSthJIpxhCSpu',NULL,'45.74.159.42','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiY29Sb29zSjFWZEVSZXpKV1dWWkVsamlRVlpMdTVBcktDdDQ5RXhaOSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHBzOi8vY2N0dmV4cHJlc3N0YWN1cm9uZy5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1785047581),
('Wr3s161ZTks2aKzJu451AsNHpwJkGfY0XYf7fsH9',NULL,'162.216.148.0','Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiWnJFQjNHNnROeWFtUnZ2ODV0Tk1ZTHhpdlROUEVMYlhTT201ZmxxcSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHBzOi8vY2N0dmV4cHJlc3N0YWN1cm9uZy5jb20iO3M6NToicm91dGUiO3M6Nzoid2VsY29tZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1785054790);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_name_unique` (`name`),
  UNIQUE KEY `users_contact_number_unique` (`contact_number`),
  KEY `users_role_id_index` (`role_id`),
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(3,'Administrator',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'cctvexpresstacurong@gmail.com',NULL,'$2y$12$REDACTEDREDACTEDREDACTEDREDACTEDREDACTEDREDACTEDREDA',NULL,1,1,'2026-07-06 01:32:33','2026-07-06 01:32:33'),
(8,'Cashier2','Staff','Two','User',21,'Sample Address, Tacurong City','09000000002','Male','cashier@gmail.com',NULL,'$2y$12$REDACTEDREDACTEDREDACTEDREDACTEDREDACTEDREDACTEDREDA',NULL,1,2,'2026-07-06 10:39:26','2026-07-06 10:39:26'),
(9,'Cashier3','Staff','One','User',24,'Sample Address, Tacurong City','09000000001','Male','cashier3@example.com',NULL,'$2y$12$REDACTEDREDACTEDREDACTEDREDACTEDREDACTEDREDACTEDREDA',NULL,1,2,'2026-07-10 19:44:13','2026-07-10 19:44:13'),
(10,'Cashier5','Staff','Three','User',21,'Sample Address, Tacurong City','09000000003','Male','cashier5@example.com',NULL,'$2y$12$REDACTEDREDACTEDREDACTEDREDACTEDREDACTEDREDACTEDREDA',NULL,1,2,'2026-07-20 12:18:10','2026-07-20 12:21:17');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

