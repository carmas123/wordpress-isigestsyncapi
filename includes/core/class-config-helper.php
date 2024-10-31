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
			ConfigHelper::$instance->get('products_featured_key'),
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
			ConfigHelper::$instance->get('products_reference_key'),
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
			ConfigHelper::$instance->get('products_brand_key'),
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
			ConfigHelper::$instance->get('sizeandcolor_size_key'),
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
			ConfigHelper::$instance->get('sizeandcolor_color_key'),
			wc_attribute_taxonomy_name('colore'),
		);
	}

	public static function clearCacheStatic() {
		self::getInstance()->clearCache();
	}
}
