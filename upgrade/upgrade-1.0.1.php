<?php

use ISIGestSyncAPI\Core\DbHelper;

function isigestsyncapi_upgrade_1_0_1(): bool {
	// Versione Iniziale
	global $wpdb;
	$p = $wpdb->prefix;
	$charset_collate = $wpdb->get_charset_collate();

	$sql = [];

	// Tabella per la storicizzazione dei prodotti
	$sql[] = "CREATE TABLE IF NOT EXISTS `{$p}isi_api_product` (
			`post_id` int(10) NOT NULL,                    		
			`variation_id` int(10) NOT NULL DEFAULT 0,     	
			`sku` char(5) NOT NULL,                       	
			`is_tc` tinyint(1) NOT NULL DEFAULT 0,      	
			`fascia_sconto_art` char(3) DEFAULT NULL,    	   
			`gruppo_merc` char(3) DEFAULT NULL,      	 	
			`sottogruppo_merc` char(3) DEFAULT NULL,     		
			`marca` char(32) DEFAULT NULL,                		
			`stagione` char(32) DEFAULT NULL,                	
			`anno` int(4) DEFAULT NULL,                     	
			`unit` char(2) DEFAULT NULL,                  	
			`unit_conversion` decimal(15,6) DEFAULT 1.000000,
			`secondary_unit` char(2) DEFAULT NULL,        	
			`use_secondary_unit` tinyint(1) DEFAULT 0,      
			PRIMARY KEY (`sku`)
		) $charset_collate;";

	$sql[] =
		'ALTER TABLE `' .
		$p .
		'isi_api_product` ADD INDEX idx_isi_api_product_1 (`post_id`) USING BTREE;';
	$sql[] =
		'ALTER TABLE `' .
		$p .
		'isi_api_product` ADD UNIQUE INDEX idx_isi_api_product_2 (`post_id`, `variation_id`) USING BTREE;';

	// Tabella per lo storico dello stock
	$sql[] = "CREATE TABLE IF NOT EXISTS `{$p}isi_api_stock` (
			`post_id` bigint(20) NOT NULL,
			`variation_id` bigint(20) NOT NULL DEFAULT 0,
			`sku` varchar(30) NOT NULL,
			`warehouse` char(2) NOT NULL,
			`stock_quantity` int(11) NOT NULL DEFAULT 0,
			`stock_status` varchar(32) DEFAULT 'instock',     /* Stato stock WooCommerce */
			PRIMARY KEY (`post_id`,`variation_id`,`warehouse`),
			KEY `IDX_SKU` (`sku`)
		) $charset_collate;";

	// Tabella per l'export dei prodotti
	$sql[] = "CREATE TABLE IF NOT EXISTS `{$p}isi_api_export_product` (
			`post_id` bigint(20) NOT NULL,
			`is_exported` tinyint(1) NOT NULL DEFAULT 0,
			`exported_at` datetime DEFAULT NULL,
			PRIMARY KEY (`post_id`)
		) $charset_collate;";

	return DbHelper::execSQLsInTransaction($sql);
}
