# Bill.com Email Integration - Add client email field for emailing PDF invoices
ALTER TABLE `ip_clients`
    ADD COLUMN `client_billcom_email` VARCHAR(255) DEFAULT NULL AFTER `client_billcom_customer_id`;