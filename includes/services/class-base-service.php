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
}
