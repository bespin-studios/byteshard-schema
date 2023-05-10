CREATE TABLE "tbl_user_settings" (
   "tab" varchar(64) NOT NULL DEFAULT '',
   "cell" varchar(64) NOT NULL DEFAULT '',
   "type" varchar(64) NOT NULL DEFAULT '',
   "item" varchar(64) NOT NULL DEFAULT '',
   "value" varchar(64) DEFAULT NULL,
   "user_id" int NOT NULL,
   PRIMARY KEY ("tab","cell","type","item","user_id")
);