ALTER TABLE `currency_invoice_ios` ADD `time_created` INT NULL DEFAULT NULL AFTER `extra_info`;
UPDATE currency_invoice_ios io JOIN currency_invoices i ON io.invoice_id=i.invoice_id SET io.time_created=i.time_created;
ALTER TABLE `pageviews` ADD `pageview_date` DATE NULL DEFAULT NULL AFTER `time`;
ALTER TABLE `pageviews` ADD INDEX (`pageview_date`);
