<?php
/**
 *
 * @package    ISIGestSyncAPI
 * @subpackage Core
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Core;

use ISIGestSyncAPI\Common\BaseConfig;

class UpgradeHelper extends BaseConfig {
	public function __construct() {
		parent::__construct();
	}

	public function performUpgrade() {
		// Leggiamo la versione corrente del database
		$current = ConfigHelper::getDbVersion();
		// Impostiamo la versione attuale del plugin
		$new = ISIGESTSYNCAPI_VERSION;

		if ($current != $new) {
			$upgrade_path = ISIGESTSYNCAPI_PLUGIN_DIR . 'upgrade/';
			$files = scandir($upgrade_path);

			$versions = [];

			foreach ($files as $file) {
				if (strpos($file, 'upgrade-') === 0) {
					$version_file = str_replace(['upgrade-', '.php'], '', $file);
					if (version_compare($version_file, $current, '>')) {
						$versions[] = $version_file;
					}
				}
			}

			// Ordiniamo le versioni usando version_compare
			usort($versions, 'version_compare');

			// Ora creiamo ed eseguiamo i metodi nell'ordine corretto
			foreach ($versions as $version) {
				require_once $upgrade_path . 'upgrade-' . $version . '.php';
				$method = 'isigestsyncapi_upgrade_' . str_replace('.', '_', $version);

				if (function_exists($method)) {
					call_user_func($method);
				}

				// Aggiorniamo la versione del database dopo gli upgrade
				ConfigHelper::setDbVersion($new);
			}

			// Aggiorniamo ora all'ulitma versione, quella del plugin
			ConfigHelper::setDbVersionCurrent();
		}
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
