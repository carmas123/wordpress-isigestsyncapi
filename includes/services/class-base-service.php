<?php
/**
 *
 * @package    ISIGestSyncAPI
 * @subpackage Services
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Services;

use ISIGestSyncAPI\Common\BaseConfig;
use ISIGestSyncAPI\Core\Utilities;

class BaseService extends BaseConfig {
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Utility per trovare un prodotto tramite SKU.
	 *
	 * @param string $sku Lo SKU da cercare.
	 * @return integer|null
	 */
	protected function findProductBySku($sku) {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT CASE WHEN variation_id IS NOT NULL AND variation_id IS NOT NULL THEN variation_id ELSE post_id END AS product_id
                FROM {$wpdb->prefix}isi_api_product
                WHERE sku = %s",
				$sku,
			),
		);
		Utilities::logDbResultN($result);

		return $result ? (int) $result : null;
	}

	/**
	 * Controlla se un prodotto è archiviato come variante.
	 *
	 * @param string $sku Lo SKU da cercare.
	 * @return boolean
	 */
	protected function isArchivedAsVariant($sku) {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT variation_id
                FROM {$wpdb->prefix}isi_api_product
                WHERE sku = %s",
				$sku,
			),
		);
		Utilities::logDbResultN($result);

		return $result ? true : false;
	}

	/**
	 * Controlla se una variante esiste.
	 *
	 * @param int $variation_id L'ID della variante.
	 * @return boolean
	 */
	protected function existsVariation($variation_id) {
		$product = wc_get_product($variation_id);
		return $product && $product->is_type('variation');
	}

	/**
	 * Trova una variazione tramite SKU
	 *
	 * @param string $sku        SKU da cercare
	 * @return int|null
	 */
	protected function findVariationBySku($sku) {
		global $wpdb;

		$product_id = $wpdb->get_var(
			$wpdb->prepare(
				"
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key='_sku' AND meta_value=%s LIMIT 1",
				$sku,
			),
		);
		Utilities::logDbResultN($product_id);

		return $product_id ? (int) $product_id : null;
	}

	/**
	 * Utility per trovare un prodotto tramite SKU in WooCommerce.
	 *
	 * @param string $sku Lo SKU da cercare.
	 * @param boolean $include_variants Flag per includere le variazioni.
	 * @return integer|null
	 */
	protected function findProductByWCSku($sku, $include_variants = true) {
		// Prima cerca tra i prodotti normali
		$product_id = wc_get_product_id_by_sku($sku);

		// Se non trova nulla, cerca tra le varianti
		if ($include_variants && !$product_id) {
			$product_id = $this->findVariationBySku($sku);
		}

		return $product_id;
	}

	/**
	 * Recupera l'aliquota IVA del prodotto.
	 *
	 * @param \WC_Product $product
	 * @return float
	 */
	protected function getProductTaxRate($product) {
		// Ottieni la classe fiscale del prodotto
		$tax_class = $product->get_tax_class();

		// Prova a ottenere le aliquote direttamente dalla classe fiscale
		$tax_rates_objects = \WC_Tax::get_rates_for_tax_class($tax_class);

		// Se ci sono risultati, converti gli oggetti in array di aliquote
		if (!empty($tax_rates_objects)) {
			$tax_rates = [];
			foreach ($tax_rates_objects as $rate) {
				$tax_rates[$rate->tax_rate_id] = [
					'rate' => $rate->tax_rate,
				];
			}
		} else {
			// Se non ci sono aliquote per la classe fiscale specifica, prova con la classe standard
			if ($tax_class !== '') {
				$tax_rates_objects = \WC_Tax::get_rates_for_tax_class('');
				if (!empty($tax_rates_objects)) {
					$tax_rates = [];
					foreach ($tax_rates_objects as $rate) {
						$tax_rates[$rate->tax_rate_id] = [
							'rate' => $rate->tax_rate,
						];
					}
				} else {
					$tax_rates = [];
				}
			} else {
				$tax_rates = [];
			}
		}

		// Se ci sono aliquote, restituisci la prima
		if (!empty($tax_rates)) {
			$first_rate = reset($tax_rates);
			return (float) $first_rate['rate'];
		}

		// Se ancora non abbiamo trovato aliquote, proviamo a recuperare direttamente dal database
		global $wpdb;
		$default_rate = $wpdb->get_var(
			"SELECT tax_rate FROM {$wpdb->prefix}woocommerce_tax_rates
			 WHERE tax_rate_class = ''
			 ORDER BY tax_rate_priority ASC
			 LIMIT 1",
		);

		if ($default_rate) {
			return (float) $default_rate;
		}

		// Se non ci sono aliquote configurate, restituisci 0
		return 0.0;
	}
}
