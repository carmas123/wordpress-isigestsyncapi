<?php
/**
 * Gestione dello stock dei prodotti
 *
 * @package    ISIGestSyncAPI
 * @subpackage Services
 * @author     Massimo Caroccia & Claude
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Services;

use ISIGestSyncAPI\Core\Utilities;
use ISIGestSyncAPI\Core\ConfigHelper;
use ISIGestSyncAPI\Core\ISIGestSyncApiException;
use ISIGestSyncAPI\Core\ISIGestSyncApiBadRequestException;

/**
 * Classe StockService per la gestione dello stock dei prodotti.
 *
 * @since 1.0.0
 */
class StockService {
	/**
	 * Configurazione del plugin.
	 *
	 * @var ConfigHelper
	 */
	private $config;

	/**
	 * Handler dello status dei prodotti.
	 *
	 * @var ProductStatusHandler
	 */
	private $status_handler;

	/**
	 * Costruttore.
	 */
	public function __construct() {
		$this->config = ConfigHelper::getInstance();
		$this->status_handler = new ProductStatusHandler();
	}

	/**
	 * Aggiorna lo stock di un prodotto.
	 *
	 * @param array   $data           I dati dello stock.
	 * @param boolean $reference_mode Se usare la modalità reference.
	 * @return array
	 * @throws ISIGestSyncApiException Se si verifica un errore.
	 */
	public function updateStock($data, $reference_mode = false) {
		global $wpdb;

		try {
			$wpdb->query('START TRANSACTION');

			// Verifichiamo se cercare per reference o per sku
			if ($reference_mode) {
				$product_data = $this->findProductByReference($data['reference']);
			} else {
				$product_data = $this->findProductBySku($data['sku']);
			}

			if (!$product_data) {
				throw new ISIGestSyncApiBadRequestException('Prodotto non trovato');
			}

			$product_id = $product_data['post_id'];
			$variation_id = $product_data['variation_id'];

			// Aggiorniamo i dati per la gestione della seconda unità di misura
			$this->applyUnitConversion($data);

			// Gestiamo il magazzino multiplo se abilitato
			if ($this->config->get('PRODUCTS_MULTI_WAREHOUSES')) {
				$this->handleMultiWarehouse($product_id, $variation_id, $data);
			} else {
				$this->handleSingleWarehouse($product_id, $variation_id, $data);
			}

			// Storicizziamo lo stock
			$this->historyProductStock(
				$product_id,
				$variation_id,
				$data['sku'],
				$data['warehouse'] ?? '@@',
				$this->getStockQuantity($data),
			);

			// Verifichiamo lo stato del prodotto
			if (!$reference_mode) {
				$this->status_handler->checkAndUpdateProductStatus($product_id);
			}

			$wpdb->query('COMMIT');

			return [
				'post_id' => $product_id,
				'variation_id' => $variation_id,
				'new_quantity' => $this->getStockQuantity($data),
			];
		} catch (\Exception $e) {
			$wpdb->query('ROLLBACK');
			throw new ISIGestSyncApiException(
				'Errore durante l\'aggiornamento dello stock: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Gestisce l'aggiornamento stock per magazzino singolo.
	 *
	 * @param integer $product_id   ID del prodotto.
	 * @param integer $variation_id ID della variante.
	 * @param array   $data        Dati dello stock.
	 * @return void
	 */
	private function handleSingleWarehouse($product_id, $variation_id, $data) {
		$new_quantity = $this->getStockQuantity($data);

		// Se è una variante, aggiorniamo quella
		if ($variation_id) {
			$variation = wc_get_product($variation_id);
			if ($variation) {
				$variation->set_stock_quantity($new_quantity);
				$variation->set_stock_status($new_quantity > 0 ? 'instock' : 'outofstock');
				$variation->save();

				// Aggiorniamo il totale del prodotto padre
				$this->updateParentProductStock($product_id);
			}
		} else {
			$product = wc_get_product($product_id);
			if ($product) {
				$product->set_stock_quantity($new_quantity);
				$product->set_stock_status($new_quantity > 0 ? 'instock' : 'outofstock');
				$product->save();
			}
		}

		// Aggiorna la cache di WooCommerce
		wc_delete_product_transients($product_id);
		if ($variation_id) {
			wc_delete_product_transients($variation_id);
		}
	}

	/**
	 * Gestisce l'aggiornamento stock per magazzino multiplo.
	 *
	 * @param integer $product_id   ID del prodotto.
	 * @param integer $variation_id ID della variante.
	 * @param array   $data        Dati dello stock.
	 * @return void
	 */
	private function handleMultiWarehouse($product_id, $variation_id, $data) {
		global $wpdb;

		$warehouse = $data['warehouse'] ?? '@@';
		$new_quantity = $this->getStockQuantity($data);

		// Aggiorniamo lo stock per il magazzino specifico
		$wpdb->replace($wpdb->prefix . 'isi_api_warehouse', [
			'post_id' => $product_id,
			'variation_id' => $variation_id,
			'warehouse_code' => $warehouse,
			'stock_quantity' => $new_quantity,
			'stock_status' => $new_quantity > 0 ? 'instock' : 'outofstock',
			'updated_at' => current_time('mysql'),
		]);

		// Calcoliamo il totale di tutti i magazzini
		$total_quantity = $this->calculateTotalWarehouseQuantity($product_id, $variation_id);

		// Aggiorniamo lo stock totale
		$this->handleSingleWarehouse($product_id, $variation_id, ['quantity' => $total_quantity]);
	}

	/**
	 * Calcola il totale dello stock da tutti i magazzini.
	 *
	 * @param integer $product_id   ID del prodotto.
	 * @param integer $variation_id ID della variante.
	 * @return integer
	 */
	private function calculateTotalWarehouseQuantity($product_id, $variation_id) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(stock_quantity) 
            FROM {$wpdb->prefix}isi_api_warehouse 
            WHERE post_id = %d AND variation_id = %d",
				$product_id,
				$variation_id,
			),
		);
	}

	/**
	 * Aggiorna lo stock totale del prodotto padre basato sulle varianti.
	 *
	 * @param integer $product_id ID del prodotto.
	 * @return void
	 */
	private function updateParentProductStock($product_id) {
		$product = wc_get_product($product_id);
		if (!$product || !$product->is_type('variable')) {
			return;
		}

		$total_stock = 0;
		$variations = $product->get_children();

		foreach ($variations as $variation_id) {
			$variation = wc_get_product($variation_id);
			if ($variation) {
				$total_stock += $variation->get_stock_quantity();
			}
		}

		$product->set_stock_quantity($total_stock);
		$product->set_stock_status($total_stock > 0 ? 'instock' : 'outofstock');
		$product->save();

		wc_delete_product_transients($product_id);
	}

	/**
	 * Storicizza lo stock di un prodotto.
	 *
	 * @param integer $product_id   ID del prodotto.
	 * @param integer $variation_id ID della variante.
	 * @param string  $sku         SKU del prodotto.
	 * @param string  $warehouse   Codice del magazzino.
	 * @param integer $quantity    Quantità.
	 * @return void
	 */
	private function historyProductStock($product_id, $variation_id, $sku, $warehouse, $quantity) {
		global $wpdb;

		$wpdb->replace($wpdb->prefix . 'isi_api_stock', [
			'post_id' => $product_id,
			'variation_id' => $variation_id,
			'sku' => $sku,
			'warehouse' => $warehouse,
			'stock_quantity' => $quantity,
			'stock_status' => $quantity > 0 ? 'instock' : 'outofstock',
			'updated_at' => current_time('mysql'),
		]);
	}

	/**
	 * Applica la conversione dell'unità di misura.
	 *
	 * @param array $data Dati da convertire.
	 * @return void
	 */
	private function applyUnitConversion(&$data) {
		if (!isset($data['unit_conversion']) || (float) $data['unit_conversion'] === 1.0) {
			return;
		}

		$conversion = (float) $data['unit_conversion'];
		$quantity_field = $this->getQuantityField();

		if (isset($data[$quantity_field])) {
			$data[$quantity_field] = (int) ((float) $data[$quantity_field] * $conversion);
		}
	}

	/**
	 * Ottiene il campo da utilizzare per la quantità.
	 *
	 * @return string
	 */
	private function getQuantityField() {
		return $this->config->get('PRODUCTS_USE_STOCK_AS_QTY')
			? 'stock_quantity'
			: 'salable_quantity';
	}

	/**
	 * Ottiene la quantità di stock dai dati.
	 *
	 * @param array $data Dati dello stock.
	 * @return integer
	 */
	private function getStockQuantity($data) {
		$quantity_field = $this->getQuantityField();
		$quantity = isset($data[$quantity_field]) ? (int) $data[$quantity_field] : 0;
		return max(0, $quantity);
	}

	/**
	 * Cerca un prodotto tramite SKU.
	 *
	 * @param string $sku SKU da cercare.
	 * @return array|null
	 */
	private function findProductBySku($sku) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT post_id, variation_id 
                FROM {$wpdb->prefix}isi_api_product 
                WHERE sku = %s",
				$sku,
			),
			ARRAY_A,
		);
	}

	/**
	 * Cerca un prodotto tramite reference.
	 *
	 * @param string $reference Reference da cercare.
	 * @return array|null
	 */
	private function findProductByReference($reference) {
		// Prima cerchiamo nelle varianti
		$variation = $this->findVariationByReference($reference);
		if ($variation) {
			return [
				'post_id' => $variation->get_parent_id(),
				'variation_id' => $variation->get_id(),
			];
		}

		// Poi nei prodotti semplici
		$product = $this->findSimpleProductByReference($reference);
		if ($product) {
			return [
				'post_id' => $product->get_id(),
				'variation_id' => 0,
			];
		}

		return null;
	}

	/**
	 * Cerca una variante tramite reference.
	 *
	 * @param string $reference Reference da cercare.
	 * @return \WC_Product_Variation|null
	 */
	private function findVariationByReference($reference) {
		global $wpdb;

		$variation_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_sku' 
            AND meta_value = %s 
            AND post_id IN (
                SELECT ID 
                FROM {$wpdb->posts} 
                WHERE post_type = 'product_variation'
            )",
				$reference,
			),
		);

		return $variation_id ? wc_get_product($variation_id) : null;
	}

	/**
	 * Cerca un prodotto semplice tramite reference.
	 *
	 * @param string $reference Reference da cercare.
	 * @return \WC_Product|null
	 */
	private function findSimpleProductByReference($reference) {
		global $wpdb;

		$product_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_sku' 
            AND meta_value = %s 
            AND post_id IN (
                SELECT ID 
                FROM {$wpdb->posts} 
                WHERE post_type = 'product'
            )",
				$reference,
			),
		);

		return $product_id ? wc_get_product($product_id) : null;
	}
}
