CREATE TABLE `tbl_User_Settings` (
   `Tab` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
   `Cell` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
   `Type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
   `Item` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
   `Value` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
   `User_ID` int(11) NOT NULL,
   PRIMARY KEY (`Tab`,`Cell`,`Type`,`Item`,`User_ID`)
);