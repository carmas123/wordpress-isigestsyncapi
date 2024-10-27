<?php
/**
 * Gestione dei prodotti
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
use ISIGestSyncAPI\Core\ISIGestSyncApiNotFoundException;

/**
 * Classe ProductService per la gestione dei prodotti.
 *
 * @since 1.0.0
 */
class ProductService {
	/**
	 * Configurazione del plugin.
	 *
	 * @var ConfigHelper
	 */
	private $config;

	/**
	 * Handler per lo status dei prodotti.
	 *
	 * @var ProductStatusHandler
	 */
	private $status_handler;

	/**
	 * Handler per le offerte.
	 *
	 * @var ProductOffersHandler
	 */
	private $offers_handler;

	/**
	 * Costruttore.
	 */
	public function __construct() {
		$this->config = ConfigHelper::getInstance();
		$this->status_handler = new ProductStatusHandler();
		$this->offers_handler = new ProductOffersHandler();
	}

	/**
	 * Crea o aggiorna un prodotto.
	 *
	 * @param array $data I dati del prodotto.
	 * @return array
	 * @throws ISIGestSyncApiException Se si verifica un errore.
	 */
	public function createOrUpdate($data) {
		global $wpdb;

		try {
			$wpdb->query('START TRANSACTION');

			// Verifica se il prodotto è a Taglie&Colori
			$is_tc = (bool) $data['isigest']['is_tc'];
			if ($is_tc && !$this->config->get('TC_ENABLED')) {
				throw new ISIGestSyncApiException(
					'Prodotto a Taglie&Colori non sincronizzabile: configurazione non abilitata',
				);
			}

			// Cerchiamo il prodotto per SKU
			$product_id = $this->findProductBySku($data['sku']);
			$product = $product_id ? wc_get_product($product_id) : null;
			$is_new = !$product;

			// Se il prodotto non esiste, lo creiamo
			if ($is_new) {
				$product = new \WC_Product_Simple();
			}

			// Aggiorniamo i dati base del prodotto
			$this->updateBasicProductData($product, $data);

			// Gestione varianti
			if (isset($data['attributes']) && is_array($data['attributes'])) {
				$this->handleVariants($product, $data);
			}

			// Salviamo il prodotto
			$product->save();

			// Storicizziamo il prodotto
			$this->historyProduct($product->get_id(), 0, $data['isigest']);

			// Aggiorniamo lo stock se non è un prodotto con varianti
			if (!$product->is_type('variable')) {
				$this->updateProductStock($product->get_id(), 0, $data);
			}

			// Verifichiamo lo stato del prodotto
			$force_disable = isset($data['active']) && !$data['active'];
			$this->status_handler->checkAndUpdateProductStatus($product->get_id(), $force_disable);

			$wpdb->query('COMMIT');

			// Ritorniamo i dati del prodotto
			return $this->get($product->get_id());
		} catch (\Exception $e) {
			$wpdb->query('ROLLBACK');
			throw new ISIGestSyncApiException(
				'Errore durante la gestione del prodotto: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Recupera i dati di un prodotto.
	 *
	 * @param integer $product_id ID del prodotto.
	 * @return array
	 * @throws ISIGestSyncApiNotFoundException Se il prodotto non viene trovato.
	 */
	public function get($product_id) {
		$product = wc_get_product($product_id);
		if (!$product) {
			throw new ISIGestSyncApiNotFoundException('Prodotto non trovato');
		}

		$data = [
			'id' => $product->get_id(),
			'sku' => $product->get_sku(),
			'name' => $product->get_name(),
			'description' => $product->get_description(),
			'description_short' => $product->get_short_description(),
			'active' => $product->get_status() === 'publish',
			'price' => $product->get_regular_price(),
			'sale_price' => $product->get_sale_price(),
			'quantity' => $product->get_stock_quantity(),
			'weight' => $product->get_weight(),
			'width' => $product->get_width(),
			'height' => $product->get_height(),
			'depth' => $product->get_length(),
			'categories' => $this->getProductCategories($product),
			'images' => $this->getProductImages($product),
		];

		// Aggiungiamo le varianti se il prodotto è variabile
		if ($product->is_type('variable')) {
			$data['variants'] = $this->getProductVariants($product);
		}

		return $data;
	}

	/**
	 * Aggiorna i dati base di un prodotto.
	 *
	 * @param \WC_Product $product Il prodotto da aggiornare.
	 * @param array       $data    I dati da aggiornare.
	 * @return void
	 */
	private function updateBasicProductData($product, $data) {
		// Dati base
		$product->set_name(Utilities::cleanProductName($data['name']));
		$product->set_status(isset($data['active']) && $data['active'] ? 'publish' : 'draft');
		$product->set_sku($data['sku']);

		// Descrizioni
		if (!$this->config->get('PRODUCTS_DONT_SYNC_DESCRIPTIONS')) {
			if (isset($data['description'])) {
				$product->set_description($data['description']);
			}
			if (isset($data['description_short'])) {
				$product->set_short_description($data['description_short']);
			}
		}

		// Prezzo
		if (!$this->config->get('PRODUCTS_DONT_SYNC_PRICES')) {
			if (isset($data['price'])) {
				$product->set_regular_price($data['price']);
			}
			if (isset($data['sale_price'])) {
				$product->set_sale_price($data['sale_price']);
			}
		}

		// Dimensioni e peso
		if (!$this->config->get('PRODUCTS_DONT_SYNC_DIMENSION_AND_WEIGHT')) {
			if (isset($data['weight'])) {
				$product->set_weight($data['weight']);
			}
			if (isset($data['width'])) {
				$product->set_width($data['width']);
			}
			if (isset($data['height'])) {
				$product->set_height($data['height']);
			}
			if (isset($data['depth'])) {
				$product->set_length($data['depth']);
			}
		}

		// Categorie
		if (!$this->config->get('PRODUCTS_DONT_SYNC_CATEGORIES') && isset($data['categories'])) {
			$this->updateProductCategories($product, $data['categories']);
		}
	}

	/**
	 * Gestisce le varianti di un prodotto.
	 *
	 * @param \WC_Product $product Il prodotto.
	 * @param array       $data    I dati delle varianti.
	 * @return void
	 */
	private function handleVariants($product, $data) {
		// Convertiamo il prodotto in variabile se non lo è già
		if (!$product->is_type('variable')) {
			$product_variable = new \WC_Product_Variable($product->get_id());
			$product = $product_variable;
		}

		$attributes = [];
		$variations = [];

		// Raccogliamo tutti i valori degli attributi
		foreach ($data['attributes'] as $variant) {
			// Gestione attributi standard o taglie/colori
			if ($this->config->get('TC_ENABLED') && $data['isigest']['is_tc']) {
				if (!empty($variant['size_name'])) {
					$attributes['taglia'][] = $variant['size_name'];
				}
				if (!empty($variant['color_name'])) {
					$attributes['colore'][] = $variant['color_name'];
				}
			} else {
				// Gestione attributi standard
				for ($i = 1; $i <= 3; $i++) {
					$type_key = "variant_type$i";
					$value_key = "variant_value$i";

					if (!empty($variant[$type_key]) && !empty($variant[$value_key])) {
						$attr_name = sanitize_title($variant[$type_key]);
						$attributes[$attr_name][] = $variant[$value_key];
					}
				}
			}

			// Prepariamo i dati della variante
			$variation_data = $this->prepareVariationData($variant);
			if ($variation_data) {
				$variations[] = $variation_data;
			}
		}

		// Creiamo o aggiorniamo gli attributi
		$this->updateProductAttributes($product, $attributes);

		// Creiamo o aggiorniamo le variazioni
		$this->updateProductVariations($product, $variations, $data['isigest']);
	}

	/**
	 * Prepara i dati di una variante.
	 *
	 * @param array $variant I dati della variante.
	 * @return array|null
	 */
	private function prepareVariationData($variant) {
		if (empty($variant['sku'])) {
			return null;
		}

		return [
			'sku' => $variant['sku'],
			'regular_price' => $variant['price'] ?? '',
			'sale_price' => $variant['sale_price'] ?? '',
			'stock_quantity' => $variant['stock_quantity'] ?? 0,
			'attributes' => $this->prepareVariationAttributes($variant),
			'dimensions' => [
				'width' => $variant['width'] ?? '',
				'height' => $variant['height'] ?? '',
				'length' => $variant['depth'] ?? '',
				'weight' => $variant['weight'] ?? '',
			],
		];
	}

	/**
	 * Aggiorna gli attributi di un prodotto.
	 *
	 * @param \WC_Product $product    Il prodotto.
	 * @param array       $attributes Gli attributi da aggiornare.
	 * @return void
	 */
	private function updateProductAttributes($product, $attributes) {
		$product_attributes = [];

		foreach ($attributes as $name => $values) {
			// Rimuovi duplicati e ordina
			$values = array_unique($values);
			sort($values);

			// Crea o aggiorna l'attributo
			$attribute_id = $this->getOrCreateAttribute($name);
			$taxonomy = wc_attribute_taxonomy_name($name);

			// Assegna i termini al prodotto
			$terms = [];
			foreach ($values as $value) {
				$term = $this->getOrCreateTerm($value, $taxonomy);
				if ($term) {
					$terms[] = $term->term_id;
				}
			}

			if (!empty($terms)) {
				wp_set_object_terms($product->get_id(), $terms, $taxonomy);
			}

			// Aggiungi alla lista degli attributi del prodotto
			$product_attributes[$taxonomy] = [
				'name' => $taxonomy,
				'value' => '',
				'position' => array_search($name, array_keys($attributes)),
				'is_visible' => true,
				'is_variation' => true,
				'is_taxonomy' => true,
			];
		}

		$product->set_attributes($product_attributes);
	}

	/**
	 * Aggiorna le variazioni di un prodotto.
	 *
	 * @param \WC_Product $product    Il prodotto.
	 * @param array       $variations Le variazioni.
	 * @param array       $isigest   I dati ISIGest.
	 * @return void
	 */
	private function updateProductVariations($product, $variations, $isigest) {
		$existing_variations = $product->get_children();
		$processed_variations = [];

		foreach ($variations as $variation_data) {
			$variation_id = $this->findVariationBySku($product->get_id(), $variation_data['sku']);

			if ($variation_id) {
				$variation = wc_get_product($variation_id);
			} else {
				$variation = new \WC_Product_Variation();
				$variation->set_parent_id($product->get_id());
			}

			// Aggiorna i dati della variazione
			$this->updateVariationData($variation, $variation_data);

			$variation->save();

			// Storicizza la variante
			$this->historyProduct(
				$product->get_id(),
				$variation->get_id(),
				array_merge(['sku' => $variation_data['sku']], $isigest),
			);

			$processed_variations[] = $variation->get_id();

			// Gestisci le offerte se necessario
			if ($this->config->get('PRODUCTS_SYNC_OFFER_AS_SPECIFIC_PRICES')) {
				$this->offers_handler->updateProductOffer(
					$product->get_id(),
					$variation->get_id(),
					$variation_data,
				);
			}
		}

		// Rimuovi le variazioni non più presenti
		foreach ($existing_variations as $existing_variation) {
			if (!in_array($existing_variation, $processed_variations)) {
				$variation = wc_get_product($existing_variation);
				if ($variation) {
					$variation->delete(true);
				}
			}
		}
	}

	/**
	 * Aggiorna i dati di una variazione.
	 *
	 * @param \WC_Product_Variation $variation      La variazione.
	 * @param array                 $variation_data I dati della variazione.
	 * @return void
	 */
	private function updateVariationData($variation, $variation_data) {
		$variation->set_sku($variation_data['sku']);

		if (!$this->config->get('PRODUCTS_DONT_SYNC_PRICES')) {
			$variation->set_regular_price($variation_data['regular_price']);
			$variation->set_sale_price($variation_data['sale_price']);
		}

		if (!$this->config->get('PRODUCTS_DONT_SYNC_STOCK')) {
			$variation->set_stock_quantity($variation_data['stock_quantity']);
			$variation->set_manage_stock(true);
		}

		if (!$this->config->get('PRODUCTS_DONT_SYNC_DIMENSION_AND_WEIGHT')) {
			$variation->set_width($variation_data['dimensions']['width']);
			$variation->set_height($variation_data['dimensions']['height']);
			$variation->set_length($variation_data['dimensions']['length']);
			$variation->set_weight($variation_data['dimensions']['weight']);
		}

		$variation->set_attributes($variation_data['attributes']);
	}

	/**
	 * Storicizza un prodotto.
	 *
	 * @param integer $id_product           ID del prodotto.
	 * @param integer $id_product_attribute ID dell'attributo prodotto.
	 * @param array   $data                 Dati da storicizzare.
	 * @return void
	 */
	private function historyProduct($id_product, $id_product_attribute, $data) {
		global $wpdb;

		if (empty($data)) {
			$wpdb->delete($wpdb->prefix . 'isi_api_product', ['id_product' => $id_product], ['%d']);
			return;
		}

		$wpdb->replace($wpdb->prefix . 'isi_api_product', [
			'id_product' => $id_product,
			'id_product_attribute' => $id_product_attribute,
			'codice' => $data['sku'],
			'is_tc' => isset($data['is_tc']) ? (int) $data['is_tc'] : 0,
			'unity' => isset($data['unity']) ? $data['unity'] : null,
			'unitConversion' => isset($data['unitConversion']) ? $data['unitConversion'] : 1,
			'secondaryUnity' => isset($data['secondaryUnity']) ? $data['secondaryUnity'] : null,
			'useSecondaryUnity' => isset($data['useSecondaryUnity'])
				? (int) $data['useSecondaryUnity']
				: 0,
		]);
	}

	/**
	 * Imposta un prodotto come ricevuto.
	 *
	 * @param array $data I dati del prodotto.
	 * @return boolean
	 */
	public function setProductAsReceived($data) {
		global $wpdb;

		$product_id = (int) $data['id'];
		$product = wc_get_product($product_id);
		if (!$product) {
			throw new ISIGestSyncApiNotFoundException("Prodotto non trovato ($product_id)");
		}

		$result = $wpdb->replace(
			$wpdb->prefix . 'isi_api_export_product',
			[
				'id_product' => $product_id,
				'exported' => 1,
				'exported_at' => current_time('mysql'),
			],
			['%d', '%d', '%s'],
		);

		return $result !== false;
	}

	/**
	 * Recupera tutti i prodotti da ricevere.
	 *
	 * @return array
	 */
	public function getProductsToReceive() {
		global $wpdb;

		$products = $wpdb->get_results("
                SELECT DISTINCT p.ID 
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->prefix}isi_api_export_product e ON p.ID = e.id_product
                WHERE p.post_type = 'product'
                AND p.post_status = 'publish'
                AND (e.id_product IS NULL OR e.exported = 0 OR p.post_modified <> e.exported_at)
            ");

		$result = [];
		foreach ($products as $product) {
			try {
				$result[] = $this->get($product->ID);
			} catch (\Exception $e) {
				Utilities::log($e->getMessage(), 'error');
			}
		}

		return $result;
	}

	/**
	 * Utility per trovare un prodotto tramite SKU.
	 *
	 * @param string $sku Lo SKU da cercare.
	 * @return integer|null
	 */
	private function findProductBySku($sku) {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id_product 
                FROM {$wpdb->prefix}isi_api_product 
                WHERE codice = %s",
				$sku,
			),
		);

		return $result ? (int) $result : null;
	}
}
