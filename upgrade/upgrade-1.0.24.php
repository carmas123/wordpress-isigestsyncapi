<?php

use ISIGestSyncAPI\Core\DbHelper;

function isigestsyncapi_upgrade_1_0_22(): bool {
	global $wpdb;
	$p = $wpdb->prefix;

	$sql = [];

	// Tabella per la storicizzazione dei prodotti
	$sql[] = "ALTER TABLE `{$p}isi_api_product` 
                MODIFY COLUMN `sku` varchar(30) NOT NULL,
                MODIFY COLUMN `marca` char(3) NULL,
                MODIFY COLUMN `stagione` char(3) NULL;
            ";

	$sql[] = "ALTER TABLE `{$p}isi_api_stock` 
                MODIFY COLUMN `post_id` int NOT NULL FIRST,
                MODIFY COLUMN `variation_id` int NOT NULL DEFAULT 0,
                MODIFY COLUMN `sku` varchar(30) NOT NULL,
                MODIFY COLUMN `warehouse` char(2) NOT NULL;
                ";

	$sql[] = "ALTER TABLE `{$p}isi_api_stock` 
                DROP COLUMN `stock_status`,
                CHANGE COLUMN `stock_quantity` `quantity` int NOT NULL DEFAULT 0;
                ";

	$sql[] = "ALTER TABLE `{$p}isi_api_export_product` 
                MODIFY COLUMN `post_id` int NOT NULL;
            ";

	$sql[] = "DROP TABLE IF EXISTS `{$p}isi_api_warehouse` ";

	return DbHelper::execSQLsInTransaction($sql);
}
