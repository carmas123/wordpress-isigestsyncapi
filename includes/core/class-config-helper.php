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
		return self::getInstance()->get('products_use_stock_qty') ? 'stock_quantity' : 'quantity';
	}

	/**
	 * Restituisce true se è attiva la gestione Taglie&Colori
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function productUseTC(): string {
		return self::getInstance()->get('products_use_stock_qty') ? 'stock_quantity' : 'quantity';
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
	 * Ritorna la chiave meta per la gestione della marca
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function getBrandMetaKey(): string {
		return '_brand';
	}
}
