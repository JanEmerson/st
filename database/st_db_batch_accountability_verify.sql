-- READ-ONLY verification. Select st_db in phpMyAdmin, open SQL, and run this file.
USE st_db;
SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('accountability_batches','accountability_batch_items') ORDER BY TABLE_NAME;
SELECT COUNT(*) AS saved_batch_documents FROM accountability_batches;
SELECT COUNT(*) AS saved_batch_items FROM accountability_batch_items;
