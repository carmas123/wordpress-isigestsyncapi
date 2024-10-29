<?php
/**
 *
 * @package    ISIGestSyncAPI
 * @subpackage common
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Common;

use ISIGestSyncAPI\Core\Utilities;

abstract class TaxHelper {
	/**
	 * Verifica l'esistenza di una classe fiscale.
	 *
	 * Questa funzione controlla se una determinata classe fiscale esiste
	 * tra le classi fiscali configurate in WooCommerce.
	 *
	 * @param string $class_name Il nome della classe fiscale da verificare.
	 * @return bool Restituisce true se la classe fiscale esiste, false altrimenti.
	 */
	private static function classExists($class_name) {
		$tax_classes = \WC_Tax::get_tax_classes();
		return in_array($class_name, $tax_classes);
	}

	/**
	 * Verifica l'esistenza di un'aliquota fiscale.
	 *
	 * Questa funzione controlla se esiste un'aliquota fiscale specifica nel database di WooCommerce.
	 *
	 * @param float  $rate      L'aliquota fiscale da verificare.
	 * @param string $country   Il codice del paese per l'aliquota fiscale.
	 * @param string $tax_class La classe fiscale (opzionale, default è stringa vuota).
	 * @return int|false        Restituisce l'ID dell'aliquota se esiste, altrimenti false.
	 */
	private static function rateExists($rate, $country, $tax_class = '') {
		global $wpdb;

		$existing_rate = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT tax_rate_id 
	             FROM {$wpdb->prefix}woocommerce_tax_rates 
	             WHERE tax_rate = %f 
	             AND tax_rate_country = %s 
	             AND tax_rate_class = %s",
				$rate,
				$country,
				$tax_class,
			),
		);

		return $existing_rate ? (int) $existing_rate->tax_rate_id : false;
	}

	// Crea una classe IVA se non esiste
	private static function createClass($class_name) {
		if (!self::classExists($class_name)) {
			\WC_Tax::create_tax_class($class_name);
		}

		// Recupera tutte le classi IVA per trovare quella appena creata
		$tax_classes = \WC_Tax::get_tax_class_slugs();

		// Cerca lo slug corretto confrontando i nomi originali
		foreach ($tax_classes as $slug => $name) {
			if (strtolower($name) === strtolower($class_name)) {
				return $slug;
			}
		}

		return false; // Ritorna false se non trova la classe
	}

	/**
	 * Crea e assegna un'aliquota IVA.
	 *
	 * Questa funzione crea una nuova classe IVA (se non esiste già) e associa ad essa
	 * una nuova aliquota IVA. Se l'aliquota esiste già, restituisce i dati esistenti.
	 *
	 * @param int    $rate       L'aliquota IVA come percentuale intera (es. 22 per 22%).
	 * @param string $name       Il nome dell'aliquota IVA.
	 * @param string $country    Il codice del paese per l'aliquota IVA (default 'IT' per Italia).
	 * @param string|null $class_name Il nome della classe IVA (opzionale).
	 *
	 * @return array Un array contenente:
	 *               - 'tax_rate_id': L'ID dell'aliquota IVA (nuovo o esistente).
	 *               - 'tax_class_slug': Lo slug della classe IVA.
	 *               - 'status': Lo stato dell'operazione ('created' per nuova aliquota, 'existing' per aliquota esistente).
	 */
	public static function createAndAssignRate($rate, $name, $country = 'IT', $class_name = null) {
		global $wpdb;

		// Crea la classe IVA e ottieni lo slug
		$tax_class_slug = self::createClass(Utilities::ifBlank($class_name, "$country IVA $rate"));

		// Verifica se l'aliquota esiste già
		$existing_rate_id = self::rateExists($rate, $country, $tax_class_slug);

		if ($existing_rate_id) {
			return [
				'tax_rate_id' => $existing_rate_id,
				'tax_class_slug' => $tax_class_slug,
				'status' => 'existing',
			];
		}

		// Crea l'aliquota IVA solo se non esiste
		$tax_rate_data = [
			'tax_rate_country' => $country,
			'tax_rate' => $rate,
			'tax_rate_name' => $name,
			'tax_rate_priority' => 1,
			'tax_rate_compound' => 0,
			'tax_rate_shipping' => 1,
			'tax_rate_order' => 0,
			'tax_rate_class' => $tax_class_slug,
		];

		$wpdb->insert($wpdb->prefix . 'woocommerce_tax_rates', $tax_rate_data);

		// Puliamo la cache delle aliquote IVA
		\WC_Cache_Helper::invalidate_cache_group('taxes');

		return [
			'tax_rate_id' => $wpdb->insert_id,
			'tax_class_slug' => $tax_class_slug,
			'status' => 'created',
		];
	}
}
