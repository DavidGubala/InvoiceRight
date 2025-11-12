# Bill.com Integration - Add tracking fields to invoices table and client toggle
ALTER TABLE `ip_invoices`
    ADD COLUMN `invoice_billcom_id` VARCHAR(50) DEFAULT NULL AFTER `invoice_url_key`,
    ADD COLUMN `invoice_billcom_sent_date` DATETIME DEFAULT NULL AFTER `invoice_billcom_id`;

# Bill.com Integration - Add per-client enable toggle
ALTER TABLE `ip_clients`
    ADD COLUMN `client_billcom_enabled` TINYINT(1) NOT NULL DEFAULT '0' AFTER `client_active`,
    ADD COLUMN `client_billcom_customer_id` VARCHAR(50) DEFAULT NULL AFTER `client_billcom_enabled`;

