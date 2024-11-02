<?php

use ISIGestSyncAPI\Core\DbHelper;

function isigestsyncapi_upgrade_1_0_50(): bool {
	global $wpdb;
	$p = $wpdb->prefix . 'isi_api_';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = [];

	// Tabella per l'export dei clienti
	$sql[] = "CREATE TABLE IF NOT EXISTS `{$p}export_customer` (
		`customer_id` int(10) NOT NULL,
		`is_exported` tinyint(1) NOT NULL DEFAULT 0,
		`exported_at` datetime DEFAULT NOW(),
		`has_error` tinyint(1) NOT NULL DEFAULT 0,
		`message` varchar(255) NULL,
		PRIMARY KEY (`customer_id`)
	) $charset_collate;";

	// Aggiungiamo le nuove colonne alla tabella di export degli ordini
	$sql[] = "ALTER TABLE `{$p}export_order`
		ADD COLUMN `has_error` tinyint NOT NULL DEFAULT 0,
		ADD COLUMN `message` varchar(255) NULL;";

	return DbHelper::execSQLsInTransaction($sql);
}
