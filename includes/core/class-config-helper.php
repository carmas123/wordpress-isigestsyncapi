<?php
/**
 * Gestione della configurazione del plugin
 *
 * @package    ISIGestSyncAPI
 * @subpackage Core
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Core;

/**
 * Classe ConfigHelper per la gestione della configurazione.
 *
 * @since 1.0.0
 */
class ConfigHelper extends ConfigBaseHelper {
	/**
	 * Istanza singleton della classe.
	 *
	 * @var ConfigHelper
	 */
	private static $instance = null;

	/**
	 * Ottiene l'istanza della classe.
	 *
	 * @return ConfigHelper
	 */
	public static function getInstance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Restituisce il campo da utilizzare per la quantità del prodotto.
	 *
	 * Questo metodo determina quale campo utilizzare per la quantità del prodotto
	 * basandosi sulla configurazione del plugin. Se 'products_use_stock_qty'
	 * è impostato su true, verrà utilizzato 'stock_quantity', altrimenti 'quantity'.
	 *
	 * @since 1.0.0
	 *
	 */
	public static function getQuantityField(): string {
		return self::getInstance()->get('products_use_stock_qty')
			? 'stock_quantity'
			: 'salable_quantity';
	}

	/**
	 * Restituisce true se è attiva la gestione IVA inclusa
	 *
	 * @since 1.0.0
	 *
	 */
	public static function getPricesWithTax(): bool {
		return self::getInstance()->get('products_price_withtax', true);
	}

	/**
	 * Ritorna la chiave meta per la gestione del codice a barre
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function getBarcodeMetaKey(): string {
		return '_global_unique_id';
	}

	/**
	 * Ritorna la chiave meta per la gestione del flag "In Evidenza"
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function getInEvidenzaMetaKey(): string {
		return Utilities::ifBlank(
			ConfigHelper::getInstance()->get('products_featured_key'),
			wc_attribute_taxonomy_name('in-evidenza'),
		);
	}

	/**
	 * Ritorna la chiave meta per la gestione gestione del reference
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function getReferenceMetaKey(): string {
		return Utilities::ifBlank(
			ConfigHelper::getInstance()->get('products_reference_key'),
			wc_attribute_taxonomy_name('reference'),
		);
	}

	/**
	 * Ritorna la chiave meta per la gestione della marca
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function getBrandMetaKey(): string {
		return Utilities::ifBlank(
			ConfigHelper::getInstance()->get('products_brand_key'),
			wc_attribute_taxonomy_name('marca'),
		);
	}

	/**
	 * Ritorna la chiave meta per la gestione della taglia
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function getSizeAndColorSizeKey(): string {
		return Utilities::ifBlank(
			ConfigHelper::getInstance()->get('sizeandcolor_size_key'),
			wc_attribute_taxonomy_name('taglia'),
		);
	}

	/**
	 * Ritorna la chiave meta per la gestione del colore
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function getSizeAndColorColorKey(): string {
		return Utilities::ifBlank(
			ConfigHelper::getInstance()->get('sizeandcolor_color_key'),
			wc_attribute_taxonomy_name('colore'),
		);
	}

	/**
	 * Ritorna la versione del database del plugin
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function getDbVersion(): string {
		return Utilities::ifBlank(ConfigHelper::getInstance()->get('db_version'), '1.0.0');
	}

	/**
	 * Ritorna la versione del database del plugin
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function setDbVersion($version): bool {
		return ConfigHelper::getInstance()->set('db_version', $version);
	}

	/**
	 * Ritorna la versione del database del plugin
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function setDbVersionCurrent(): bool {
		return self::setDbVersion(ISIGESTSYNCAPI_VERSION);
	}

	public static function getDbNeedToBeUpgrade(): bool {
		return version_compare(self::getDbVersion(), ISIGESTSYNCAPI_VERSION) < 0;
	}

	public static function clearCacheStatic() {
		self::getInstance()->clearCache();
	}

	/**
	 * Retrieves the list of exportable order statuses.
	 *
	 * This function determines which order statuses are set to be exportable
	 * based on the plugin configuration. It iterates through all available
	 * WooCommerce order statuses and checks if each status is marked for export.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array of order status keys that are set to be exportable.
	 *               Each key is prefixed with 'wc-' as per WooCommerce convention.
	 */
	public static function getExportableOrderStatuses(): array {
		$exportable_statuses = [];
		$available_statuses = wc_get_order_statuses();
		$instance = self::getInstance();

		foreach ($available_statuses as $status_key => $status_label) {
			$key = str_replace('wc-', '', $status_key);
			if ($key === 'shop_order_refund' || $key === 'refunded') {
				// Lo stato "Ordine rimborsato" non viene esportato MAI
				continue;
			}
			$option_name = 'orders_export_status_' . $key;

			// Se l'opzione esiste ed è true
			if ($instance->get($option_name)) {
				$exportable_statuses[] = $status_key;
			}
		}

		return $exportable_statuses;
	}
}
