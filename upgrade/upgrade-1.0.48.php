<?php

use ISIGestSyncAPI\Core\DbHelper;

function isigestsyncapi_upgrade_1_0_48(): bool {
	global $wpdb;
	$p = $wpdb->prefix . 'isi_api_';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = [];

	// Tabella per l'export degli ordini
	$sql[] = "CREATE TABLE IF NOT EXISTS `{$p}export_order` (
			`order_id` int(10) NOT NULL,
			`is_exported` tinyint(1) NOT NULL DEFAULT 0,
			`exported_at` datetime DEFAULT NOW(),
			PRIMARY KEY (`order_id`)
		) $charset_collate;";

	return DbHelper::execSQLsInTransaction($sql);
}
