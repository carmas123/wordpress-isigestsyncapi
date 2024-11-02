<?php
/**
 * Gestione dei prodotti
 *
 * @package    ISIGestSyncAPI
 * @subpackage Services
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Services;

use ISIGestSyncAPI\Admin\ProductAdvancedFields;
use ISIGestSyncAPI\Core\ConfigHelper;
use ISIGestSyncAPI\Core\DbHelper;
use ISIGestSyncAPI\Core\ProductAttribute;
use ISIGestSyncAPI\Services\BaseService;
use ISIGestSyncAPI\Core\Utilities;
use ISIGestSyncAPI\Core\ISIGestSyncApiException;
use ISIGestSyncAPI\Core\ISIGestSyncApiBadRequestException;
use ISIGestSyncAPI\Core\ISIGestSyncApiNotFoundException;

/**
 * Classe ProductService per la gestione dei prodotti.
 *
 * @since 1.0.0
 */
class ProductService extends BaseService {
	/**
	 * Handler per lo status dei prodotti.
	 *
	 * @var ProductStatusHandler
	 */
	private $status_handler;

	/**
	 * Prezzi IVA inclusa
	 *
	 * @var bool $prices_with_tax Se i prezzi devono essere inclusi IVA.
	 */
	private $prices_with_tax = true;

	/**
	 * Costruttore.
	 */
	public function __construct() {
		parent::__construct();

		// Prezzi IVA inclusa
		$this->prices_with_tax = ConfigHelper::getPricesWithTax();

		$this->status_handler = new ProductStatusHandler();
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

		$isigest = $data['isigest'] ?? null;
		if (!$isigest) {
			throw new ISIGestSyncApiBadRequestException('Dati ISIGest non trovati');
		}

		// Verifica se il prodotto è a Taglie&Colori
		$is_tc = (bool) $isigest['is_tc'];

		// Cerchiamo il prodotto per SKU
		$product_id =
			$this->findProductBySku($data['sku']) ?? $this->findProductByWCSku($data['sku']);
		$product = $product_id ? wc_get_product($product_id) : null;
		$is_new = !$product;

		$use_attributes =
			$is_tc ||
			(isset($data['attributes']) &&
				is_array($data['attributes']) &&
				count($data['attributes']) > 0);

		// Se il prodotto non esiste, lo creiamo
		if ($is_new) {
			$product = $use_attributes ? new \WC_Product_Variable() : new \WC_Product_Simple();
		}

		// Aggiorniamo i dati base del prodotto
		$this->updateBasicProductData($product, $data);

		// Aggiorniamo le Varianti e gli Attributi
		$this->handleVariantsAndAttributes($product, $data);

		// Gestione varianti
		if ($use_attributes) {
			$this->updateParentPriceMeta($product);
		}

		// Salviamo il prodotto
		$product->save();

		// Log
		Utilities::log(
			'Creazione/Aggiornamento prodotto: ' . $product->get_id() . ' - SKU: ' . $data['sku'],
		);

		// Storicizziamo il prodotto
		$this->historyProduct($product->get_id(), 0, $isigest);

		// Aggiorniamo lo stock se non è un prodotto con varianti
		if (!$product->is_type('variable')) {
			StockService::updateProductStock($product->get_id(), $data);
		}

		// Verifichiamo lo stato del prodotto
		$force_disable = isset($data['active']) && !$data['active'];
		$this->status_handler->checkAndUpdateProductStatus(
			$product->get_id(),
			false,
			$force_disable,
		);

		// Ritorniamo i dati del prodotto
		return $this->get($product->get_id());
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
			'price' => (float) $product->get_regular_price(),
			'sale_price' => (float) $product->get_sale_price(),
			'quantity' => (float) $product->get_stock_quantity(),
			'weight' => (float) $product->get_weight(),
			'width' => (float) $product->get_width(),
			'height' => (float) $product->get_height(),
			'depth' => (float) $product->get_length(),
			'categories' => $this->getProductCategories($product),
			'images' => $this->getProductImages($product),
			'brand' => $this->getProductBrand($product),
			'ean13' => get_post_meta($product->get_id(), ConfigHelper::getBarcodeMetaKey(), true),
		];

		// Aggiungiamo le varianti se il prodotto è variabile
		if ($product->is_type('variable')) {
			$data['variants'] = $this->getProductVariants($product);
		}

		return $data;
	}

	private function getCanImportDescriptionShort() {
		$type = (int) $this->config->get('products_short_description');
		return $type !== 2;
	}

	private function getCanImportDescription() {
		$type = (int) $this->config->get('products_description');
		return $type !== 2;
	}

	private function getCanImportName() {
		$type = (int) $this->config->get('products_name');
		return $type !== 2;
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
		$product->set_sku($data['sku']);

		// Nome
		if ($this->getCanImportName() || empty($product->get_name())) {
			$product->set_name($this->extractName($data));
		}

		// Descrizioni
		if (isset($data['description']) && $this->getCanImportDescription()) {
			$product->set_description($this->convertDescription($data['description']));
		}
		if (isset($data['description_short']) && $this->getCanImportDescriptionShort()) {
			$product->set_short_description(
				$this->convertDescriptionShort($data['description_short']),
			);
		}

		// Prezzo solo per i prodotti semplici
		if (
			!$this->config->get('products_dont_sync_prices') &&
			!$product instanceof \WC_Product_Variable
		) {
			$product->set_regular_price($this->extractRegularPrice($data));
			$product->set_sale_price($this->extractPrice($data));
		}

		// Dimensioni e peso
		if (!$this->config->get('products_dont_sync_dimension_and_weight')) {
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

		// Salviamo il prodotto (IMPORTANTE)
		// Se non salviamo qui non verrà assegnato l'ID del prodotto e quindi in caso di prodotti nuovi
		// i dati successivi non verranno assegnati correttamente
		$product->save();

		// Categorie
		if (
			!$this->config->get('products_dont_sync_categories', false) &&
			isset($data['categories'])
		) {
			$this->updateProductCategories($product, $data['categories']);
		}

		// Gestione dei campi aggiuntivi
		$feat = array_merge(
			[
				[
					'name' => 'In evidenza',
					'type' => $data['isigest']['featured'] === 'yes' ? 'Si' : 'No',
				],
			],
			$data['features'] ?? [],
		);
		// ProductAdvancedFields::syncProductAttributes($product->get_id(), $feat);
	}

	private function extractName($body) {
		$type = (int) $this->config->get('products_name');
		$value = $body['name'] ?? '';
		switch ($type) {
			case 1:
				$value = $body['tags'] ?? '';
				break;
			case 2:
				$value = $body['description_short'] ?? '';
				break;
		}

		// Se il valore è vuoto allora impostiamo sempre il campo "name"
		$value = Utilities::ifBlank($value, $body['name'] ?? '');
		return Utilities::cleanProductName(strip_tags($value));
	}

	private function convertDescriptionShort($value) {
		$type = (int) $this->config->get('products_short_description');
		switch ($type) {
			case 0:
				return $value; // Escape HTML
			case 1:
				return strip_tags($value); // Remove HTML tags
		}
		return $value;
	}

	private function convertDescription($value) {
		$type = (int) $this->config->get('products_description');
		switch ($type) {
			case 0:
				return $value; // Escape HTML
			case 1:
				return strip_tags($value); // Remove HTML tags
		}
		return $value;
	}

	private function extractPrice($body) {
		return (float) $body[$this->prices_with_tax ? 'price_wt' : 'price'];
	}

	private function extractRegularPrice($body) {
		$p = $this->extractPrice($body);
		$rp = (float) $body[$this->prices_with_tax ? 'sale_price_wt' : 'sale_price'];
		if ($rp > $p) {
			return $rp;
		}
		return $p;
	}

	/**
	 * Updates the price metadata for a variable product.
	 *
	 * This method calculates and updates various price-related metadata for a variable product
	 * based on its variations' prices. It sets the minimum and maximum prices for regular,
	 * sale, and overall prices.
	 *
	 * @param \WC_Product_Variable $product The variable product to update.
	 *
	 * @return void
	 */
	private function updateParentPriceMeta($product) {
		// Get all variation prices
		$prices = $product->get_variation_prices(true);

		// Update overall price meta
		if (!empty($prices['price'])) {
			update_post_meta($product->get_id(), '_price', min($prices['price']));
			update_post_meta($product->get_id(), '_min_variation_price', min($prices['price']));
			update_post_meta($product->get_id(), '_max_variation_price', max($prices['price']));
		}

		// Update regular price meta
		if (!empty($prices['regular_price'])) {
			update_post_meta(
				$product->get_id(),
				'_min_variation_regular_price',
				min($prices['regular_price']),
			);
			update_post_meta(
				$product->get_id(),
				'_max_variation_regular_price',
				max($prices['regular_price']),
			);
		}

		// Update sale price meta
		if (!empty($prices['sale_price'])) {
			update_post_meta(
				$product->get_id(),
				'_min_variation_sale_price',
				min($prices['sale_price']),
			);
			update_post_meta(
				$product->get_id(),
				'_max_variation_sale_price',
				max($prices['sale_price']),
			);
		}
	}

	/**
	 * Storicizza un prodotto.
	 *
	 * @param integer $post_id           ID del prodotto.
	 * @param integer $variant_id ID dell'attributo prodotto.
	 * @param array   $data                 Dati da storicizzare.
	 * @return void
	 */
	private function historyProduct($post_id, $variation_id, $data) {
		global $wpdb;

		if (empty($data)) {
			if (empty($variant_id)) {
				Utilities::logDbResult(
					$wpdb->delete(
						$wpdb->prefix . 'isi_api_product',
						['post_id' => $post_id],
						['%d'],
					),
				);
			} else {
				Utilities::logDbResult(
					$wpdb->delete(
						$wpdb->prefix . 'isi_api_product',
						['post_id' => $post_id, 'variation_id' => $variation_id],
						['%d', '%d'],
					),
				);
			}

			return;
		}

		Utilities::logDbResult(
			$wpdb->replace($wpdb->prefix . 'isi_api_product', [
				'post_id' => $post_id,
				'variation_id' => $variation_id,
				'sku' => $data['sku'],
				'is_tc' => isset($data['is_tc']) ? (int) $data['is_tc'] : 0,
				'unit' => isset($data['unity']) ? $data['unity'] : null,
				'unit_conversion' => isset($data['unitConversion']) ? $data['unitConversion'] : 1,
				'secondary_unit' => isset($data['secondaryUnity']) ? $data['secondaryUnity'] : null,
				'use_secondary_unit' => isset($data['useSecondaryUnity'])
					? (int) $data['useSecondaryUnity']
					: 0,
			]),
		);
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
				'post_id' => $product_id,
				'exported' => 1,
				'exported_at' => current_time('mysql'),
			],
			['%d', '%d', '%s'],
		);
		Utilities::logDbResult($result);

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
                LEFT JOIN {$wpdb->prefix}isi_api_export_product e ON p.ID = e.post_id
                WHERE p.post_type = 'product'
                AND p.post_status = 'publish'
                AND (e.post_id IS NULL OR e.exported = 0 OR p.post_modified <> e.exported_at)
            ");

		$result = [];
		foreach ($products as $product) {
			try {
				$result[] = $this->get($product->ID);
			} catch (\Exception $e) {
				Utilities::logError($e->getMessage());
			}
		}

		return $result;
	}

	/**
	 * Ottiene le categorie di un prodotto
	 *
	 * @param \WC_Product $product Prodotto
	 * @return array
	 */
	private function getProductCategories($product) {
		$categories = [];
		$terms = get_the_terms($product->get_id(), 'product_cat');

		if (!empty($terms) && !is_wp_error($terms)) {
			foreach ($terms as $term) {
				$category_path = $this->getCategoryPath($term);
				if (!empty($category_path)) {
					$categories[] = $category_path;
				}
			}
		}

		return $categories;
	}

	/**
	 * Ottiene il percorso completo di una categoria
	 *
	 * @param \WP_Term $term Termine della categoria
	 * @return string
	 */
	private function getCategoryPath($term) {
		$path = [];
		$current = $term;

		while ($current) {
			$path[] = $current->name;
			$current = $current->parent ? get_term($current->parent, 'product_cat') : null;
		}

		return implode('>', array_reverse($path));
	}

	/**
	 * Ottiene le immagini di un prodotto
	 *
	 * @param \WC_Product $product Prodotto
	 * @return array
	 */
	private function getProductImages($product) {
		$images = [];

		// Immagine principale
		if ($product->get_image_id()) {
			$images[] = [
				'id' => $product->get_image_id(),
				'src' => wp_get_attachment_url($product->get_image_id()),
				'position' => 0,
				'is_main' => true,
			];
		}

		// Immagini della galleria
		$gallery_images = $product->get_gallery_image_ids();
		$position = 1;
		foreach ($gallery_images as $image_id) {
			$images[] = [
				'id' => $image_id,
				'src' => wp_get_attachment_url($image_id),
				'position' => $position++,
				'is_main' => false,
			];
		}

		return $images;
	}

	/**
	 * Ottiene le varianti di un prodotto
	 *
	 * @param \WC_Product_Variable $product Prodotto variabile
	 * @return array
	 */
	private function getProductVariants($product) {
		if (!$product->is_type('variable')) {
			return [];
		}

		$variants = [];
		$variations = $product->get_available_variations();

		foreach ($variations as $variation) {
			$variation_obj = wc_get_product($variation['variation_id']);
			if (!$variation_obj || !$variation_obj instanceof \WC_Product_Variation) {
				continue;
			}

			$variants[] = [
				'id' => $variation_obj->get_id(),
				'sku' => $variation_obj->get_sku(),
				'price' => (float) $variation_obj->get_regular_price(),
				'sale_price' => (float) $variation_obj->get_sale_price(),
				'stock_quantity' => (int) $variation_obj->get_stock_quantity(),
				'attributes' => $this->getVariationAttributes($variation_obj),
				'images' => $this->getProductImages($variation_obj),
			];
		}

		return $variants;
	}

	/**
	 * Aggiorna le categorie di un prodotto
	 *
	 * @param \WC_Product $product    Prodotto
	 * @param array       $categories Array di percorsi categorie
	 * @return void
	 */
	private function updateProductCategories($product, $categories) {
		$category_ids = [];

		foreach ($categories as $category_path) {
			$category_path ??= trim($category_path);
			if (!empty($category_path)) {
				$category_id = $this->getOrCreateCategoryFromPath($category_path);
				if ($category_id) {
					$category_ids[] = $category_id;
				}
			}
		}

		wp_set_object_terms($product->get_id(), $category_ids, 'product_cat');
	}

	/**
	 * Prepara gli attributi di una variazione
	 *
	 * @param array $variant Dati della variante
	 * @param bool $is_tc Flag per utilizzare Taglie&Colori
	 * @return array
	 */
	private function prepareVariationAttributes($variant, $is_tc) {
		$attributes = [];

		if ($is_tc) {
			$attributes[ConfigHelper::getSizeAndColorSizeKey()] = [
				'variant' => true,
				'label' => 'Taglia',
				'value' => $variant['size_name'],
			];
			$attributes[ConfigHelper::getSizeAndColorColorKey()] = [
				'variant' => true,
				'label' => 'Colore',
				'value' => $variant['color_name'],
			];
		} else {
			// Attributi standard
			for ($i = 1; $i <= 3; $i++) {
				$type_key = "variant_type$i";
				$value_key = "variant_value$i";

				if (!empty($variant[$type_key]) && !empty($variant[$value_key])) {
					$attr_label = $variant[$type_key];
					$attr_name = wc_attribute_taxonomy_name($attr_label);
					$attributes[$attr_name] = [
						'variant' => true,
						'label' => $attr_label,
						'value' => $variant[$value_key],
					];
				}
			}
		}

		return $attributes;
	}

	/**
	 * Prepara gli attributi di tipo funzionalità per un prodotto.
	 *
	 * Questo metodo crea un array di attributi per una singola funzionalità del prodotto.
	 * Può essere utilizzato per attributi che non sono varianti, come ad esempio la marca o altre caratteristiche fisse.
	 *
	 * @param string $value Il valore dell'attributo.
	 * @param string $label L'etichetta dell'attributo.
	 * @param string|null $key La chiave dell'attributo. Se non specificata, verrà generata dal label.
	 *
	 * @return array Un array contenente l'attributo preparato.
	 */
	private function prepareFeatureAttributes($value, $label, $key, $hidden = false) {
		$attributes = [];

		// Impostiamo la chiave
		$key ??= wc_attribute_taxonomy_name($label);

		$attributes[$key] = [
			'variant' => false,
			'label' => $label,
			'value' => $value,
			'hidden' => (bool) $hidden,
		];

		return $attributes;
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
	 * Ottiene gli attributi di una variazione
	 *
	 * @param \WC_Product_Variation $variation Variazione del prodotto
	 * @return array
	 */
	private function getVariationAttributes($variation) {
		$attributes = [];
		$variation_attributes = $variation->get_variation_attributes();

		foreach ($variation_attributes as $attribute_name => $attribute_value) {
			// Rimuoviamo il prefisso 'attribute_' che WooCommerce aggiunge
			$clean_name = str_replace('attribute_', '', $attribute_name);

			// Se è un attributo di tassonomia (pa_*)
			if (strpos($clean_name, 'pa_') === 0) {
				// Otteniamo il termine dall'ID o dallo slug
				$term = get_term_by('slug', $attribute_value, $clean_name);
				if ($term && !is_wp_error($term)) {
					$attributes[$clean_name] = [
						'id' => $term->term_id,
						'name' => $term->name,
						'slug' => $term->slug,
						'taxonomy' => $clean_name,
					];
				}
			} else {
				// Attributo personalizzato
				$attributes[$clean_name] = [
					'id' => 0,
					'name' => $attribute_value,
					'slug' => sanitize_title($attribute_value),
					'taxonomy' => '',
				];
			}
		}

		return $attributes;
	}

	/**
	 * Ottiene o crea una categoria dal percorso completo
	 * Es: "Abbigliamento > Uomo > T-Shirt"
	 *
	 * @param string $category_path Percorso della categoria separato da '>'
	 * @return int|null ID della categoria o null se non valido
	 */
	private function getOrCreateCategoryFromPath($category_path) {
		$categories = array_map('trim', explode('>', $category_path));
		$parent_id = 0;
		$final_category_id = null;
		$full_path = '';

		foreach ($categories as $category_name) {
			if (empty($category_name)) {
				continue;
			}

			// Rimuoviamo gli spazi iniziali e finali
			$category_name = trim($category_name);
			$category_slug = wc_sanitize_taxonomy_name($category_name);
			$category_name = htmlentities($category_name);

			// Azzeriamo il Category Id (Importante altrimenti potrebbe rimanere quello del padre)
			$category_id = 0;

			$found = false;

			// Ricerca Tramite Slug
			$existing_terms = get_terms([
				'taxonomy' => 'product_cat',
				'slug' => $category_slug,
				'hide_empty' => false,
				'parent' => $parent_id,
			]);

			if (!empty($existing_terms) && !is_wp_error($existing_terms)) {
				// Impostiamo il path
				$find_full_path = ($full_path ? $full_path . '>' : '') . $category_slug;

				// Verifica se una delle categorie trovate corrisponde al percorso completo
				foreach ($existing_terms as $term) {
					if ($this->validateCategoryPath($term->term_id, $find_full_path)) {
						$category_id = $term->term_id;
						$found = true;
						break;
					}
				}
			}

			// Ricerca Tramite Nome
			if (!$found) {
				$existing_terms = get_terms([
					'taxonomy' => 'product_cat',
					'name' => $category_name,
					'hide_empty' => false,
					'parent' => $parent_id,
				]);

				if (!empty($existing_terms) && !is_wp_error($existing_terms)) {
					// Verifica se una delle categorie trovate corrisponde al percorso completo
					foreach ($existing_terms as $term) {
						// Impostiamo il path
						$find_full_path = ($full_path ? $full_path . '>' : '') . $term->slug;

						if ($this->validateCategoryPath($term->term_id, $find_full_path)) {
							// Riassegnamo lo Slug dalla Categoria Trovata tramite nome
							$category_slug = $term->slug;
							$category_id = $term->term_id;
							$found = true;
							break;
						}
					}
				}
			}

			// Reimpostiamo il Path corretto
			$full_path .= ($full_path ? '>' : '') . $category_slug;

			// Se non è stata trovata una categoria valida, creane una nuova
			if (!$found) {
				$result = wp_insert_term($category_name, 'product_cat', [
					'slug' => $category_slug,
					'parent' => $parent_id,
				]);

				if (is_wp_error($result)) {
					Utilities::logError(
						"Errore nella creazione della categoria '$category_name': " .
							$result->get_error_message(),
					);
					return null;
				} else {
					Utilities::log(
						"Creata categoria '$category_name' con ID: {$result['term_id']}",
					);
				}
				$category_id = $result['term_id'];
			}

			$parent_id = $category_id;
			$final_category_id = $category_id;
		}

		if ($final_category_id) {
			return $final_category_id;
		}

		return null;
	}

	// Funzione helper per validare il percorso completo di una categoria
	private function validateCategoryPath($term_id, $expected_path) {
		$actual_path = '';
		$current_term_id = $term_id;

		while ($current_term_id) {
			$term = get_term($current_term_id, 'product_cat');
			if (is_wp_error($term)) {
				return false;
			}

			$actual_path = $term->slug . ($actual_path ? '>' . $actual_path : '');
			$current_term_id = $term->parent;
		}

		return $actual_path === $expected_path;
	}

	/**
	 * Aggiorna gli attributi di un prodotto.
	 *
	 * Questa funzione crea o aggiorna gli attributi di un prodotto WooCommerce.
	 * Gestisce la creazione di attributi globali, l'assegnazione di termini
	 * e l'impostazione degli attributi sul prodotto.
	 *
	 * @param \WC_Product $product      Il prodotto WooCommerce da aggiornare.
	 * @param array      $attributes   Un array di attributi da aggiungere o aggiornare.
	 * @param bool       $has_archives Indica se gli attributi devono avere archivi.
	 *
	 * @return void
	 */
	private function updateProductAttributes($product, $attributes, $has_archives = true) {
		$product_attributes = [];
		$processed_attributes = [];

		// Riorganizza l'array degli attributi
		foreach ($attributes as $item) {
			foreach ($item as $key => $value) {
				if (!isset($processed_attributes[$key])) {
					$processed_attributes[$key] = [
						'label' => $value['label'],
						'variant' => $value['variant'] ? 1 : 0,
						'hidden' => $value['hidden'] ? 1 : 0,
					];
					$processed_attributes[$key]['values'] = [];
				}
				$processed_attributes[$key]['values'][] = $value['value'];
			}
		}

		foreach ($processed_attributes as $name => $values) {
			// Verifichiamo l'esistenza dell'attributo
			ProductAttribute::getOrCreateAttribute($name, $values['label'], $has_archives);

			$term_ids = [];
			foreach ($values['values'] as $value) {
				$term = ProductAttribute::getOrCreateTerm($value, $name);
				if ($term) {
					$term_ids[] = $term->term_id;
				}
			}

			if (!empty($term_ids)) {
				wp_set_object_terms($product->get_id(), $term_ids, $name);

				// Formattiamo gli attributi secondo la documentazione
				$attribute = new \WC_Product_Attribute();
				$attribute->set_id(wc_attribute_taxonomy_id_by_name($name));
				$attribute->set_name($name);
				$attribute->set_options($term_ids);
				$attribute->set_position(array_search($name, array_keys($processed_attributes)));
				$attribute->set_visible(!((bool) $values['hidden']));
				$attribute->set_variation((bool) $values['variant']);

				$product_attributes[] = $attribute;
			}
		}

		if (!empty($product_attributes)) {
			wp_set_object_terms($product->get_id(), 'variable', 'product_type');
			$product->set_attributes($product_attributes);
			$product->save();
		}
	}

	private function handleVariantsAndAttributes($product, $data) {
		$isigest = $data['isigest'] ?? null;
		if (!$isigest) {
			throw new ISIGestSyncApiBadRequestException('Dati ISIGest non trovati');
		}

		// Verifichiamo se il prodotto è una variabile
		$is_variable =
			isset($data['attributes']) &&
			is_array($data['attributes']) &&
			!empty($data['attributes']);

		// Convertiamo il prodotto in variabile se non lo è già
		if ($is_variable && !$product->is_type('variable')) {
			$product_variable = new \WC_Product_Variable($product->get_id());
			$product = $product_variable;
		}

		$attributes = [];
		$variations = [];

		// Raccogliamo tutti i valori degli attributi delle varianti
		if ($is_variable) {
			foreach ($data['attributes'] as $variant) {
				// Prepariamo gli attributi della variante
				$attributes[] = $this->prepareVariationAttributes(
					$variant,
					(bool) $isigest['is_tc'],
				);

				// Prepariamo i dati della variante
				if (!empty($variant['sku'])) {
					$variations[] = $variant;
				}
			}
		}

		// Aggiungiamo altri Attributi

		// Marca (Brand)
		$this->handleMarca($attributes, $data);

		// Codice Produttore
		$this->handleReference($attributes, $data);

		// Flag In Evidenza
		$this->handleInEvidenza($attributes, $data);

		// Salviamo il prodotto dopo aver impostato gli attributi
		$product->save();

		// Creiamo o aggiorniamo gli attributi
		$this->updateProductAttributes($product, $attributes);

		// Codice a barre
		$this->handleBarcode($product->get_id(), $data);

		// Creiamo o aggiorniamo le variazioni
		if ($is_variable) {
			$this->updateProductVariations($product, $variations, $isigest);
		}
	}

	private function handleMarca(&$attributes, $data) {
		if (
			!$this->config->get('products_dont_sync_brand', false) &&
			isset($data['brand']) &&
			isset($data['brand']['name']) &&
			!empty($data['brand']['name'])
		) {
			$attributes[] = $this->prepareFeatureAttributes(
				$data['brand']['name'],
				'Marca',
				ConfigHelper::getBrandMetaKey(),
				$this->config->get('products_brand_hidden', false),
			);
		}
	}

	private function handleReference(&$attributes, $data) {
		if (
			!$this->config->get('products_dont_sync_reference', false) &&
			isset($data['reference']) &&
			!empty($data['reference'])
		) {
			$attributes[] = $this->prepareFeatureAttributes(
				$data['reference'],
				'Codice Produttore',
				ConfigHelper::getReferenceMetaKey(),
				$this->config->get('products_reference_hidden', false),
			);
		}
	}

	private function handleBarcode($post_id, $data) {
		if (
			!$this->config->get('products_dont_sync_ean', false) &&
			isset($data['ean13']) &&
			!empty($data['ean13'])
		) {
			update_post_meta(
				$post_id,
				ConfigHelper::getBarcodeMetaKey(),
				sanitize_text_field($data['ean13']),
			);
		}
	}

	private function handleInEvidenza(&$attributes, $data) {
		if (!$this->config->get('products_dont_sync_featured_flag', false)) {
			$attributes[] = $this->prepareFeatureAttributes(
				$data['isigest']['featured'] ? 'Si' : 'No',
				'In Evidenza',
				ConfigHelper::getInEvidenzaMetaKey(),
				$this->config->get('products_featured_hidden', false),
			);
		}
	}

	private function updateProductVariations($product, $variations, $isigest) {
		$existing_variations = $product->get_children();
		$processed_variations = [];

		foreach ($variations as $variant) {
			$sku = $variant['sku'];

			$variation_id = $this->findProductBySku($sku) ?? $this->findVariationBySku($sku);

			$variation = null;
			if ($variation_id) {
				$variation = wc_get_product($variation_id);
			}

			if (!$variation) {
				// Creiamo una nuova variante
				$variation = new \WC_Product_Variation();
			}

			// Impostiamo il parent
			$variation->set_parent_id($product->get_id());

			// Aggiorniamo i dati della variante
			$this->updateVariationData($variation, $variant, (bool) $isigest['is_tc']);

			$variation->save();

			Utilities::log("Creata/Aggiornata variante: SKU {$sku}");

			$processed_variations[] = $variation->get_id();

			// Storicizza la variante
			$this->historyProduct(
				$product->get_id(),
				$variation->get_id(),
				array_merge($isigest, ['sku' => $sku]),
			);
		}

		// Rimuovi variazioni non più presenti
		foreach ($existing_variations as $existing_variation) {
			if (!in_array($existing_variation, $processed_variations)) {
				$variation = wc_get_product($existing_variation);
				if ($variation) {
					$variation->delete(true);
				}
			}
		}

		// Aggiorniamo la variante di default
		$this->updateDefaultVariant($product->get_id());
	}

	private function updateVariationData($variation, $data, $is_tc) {
		// Imposta SKU
		$variation->set_sku($data['sku']);

		// Imposta prezzi
		if (!$this->config->get('products_dont_sync_prices')) {
			$variation->set_regular_price($this->extractRegularPrice($data));
			$variation->set_sale_price($this->extractPrice($data));
		}

		// Imposta stock
		StockService::updateProductStock($variation->get_id(), $data);

		// Codice a barre
		$this->handleBarcode($variation->get_id(), $data);

		// Imposta attributi
		$attributes = [];
		if ($is_tc) {
			if (!empty($data['size_name'])) {
				$attributes[ConfigHelper::getSizeAndColorSizeKey()] = $data['size_name'];
			}
			if (!empty($data['color_name'])) {
				$attributes[ConfigHelper::getSizeAndColorColorKey()] = $data['color_name'];
			}
		} else {
			// Attributi standard
			for ($i = 1; $i <= 3; $i++) {
				$type_key = "variant_type$i";
				$value_key = "variant_value$i";

				if (!empty($data[$type_key]) && !empty($data[$value_key])) {
					$attr_label = $data[$type_key];
					$attr_name = wc_attribute_taxonomy_name($attr_label);
					$attributes[$attr_name] = $data[$value_key];
				}
			}
		}

		$variation->set_attributes($attributes);
	}

	/**
	 * Ottiene la marca di un prodotto
	 *
	 * @param \WC_Product $product Prodotto
	 * @return string|null
	 */
	private function getProductBrand($product) {
		$terms = get_the_terms($product->get_id(), ConfigHelper::getBrandMetaKey());
		if (!empty($terms) && !is_wp_error($terms)) {
			return $terms[0]->name;
		}
		return null;
	}

	function updateDefaultVariant($product_id) {
		$product = wc_get_product($product_id);

		// Verifica che sia un prodotto variabile
		if ($product && $product->is_type('variable')) {
			$available_variations = $product->get_available_variations();

			if (!empty($available_variations)) {
				$lowest_price = PHP_FLOAT_MAX;
				$lowest_price_variation = null;

				foreach ($available_variations as $variation) {
					// Controlla se la variante è acquistabile
					if (!$variation['is_purchasable'] || !$variation['is_in_stock']) {
						continue;
					}

					$price = !empty($variation['display_price'])
						? $variation['display_price']
						: (!empty($variation['display_regular_price'])
							? $variation['display_regular_price']
							: PHP_FLOAT_MAX);

					if ($price < $lowest_price) {
						$lowest_price = $price;
						$lowest_price_variation = $variation;
					}
				}

				if ($lowest_price_variation) {
					// Imposta gli attributi predefiniti
					$default_attributes = [];
					foreach (
						$lowest_price_variation['attributes']
						as $attribute_name => $attribute_value
					) {
						$taxonomy = str_replace('attribute_', '', $attribute_name);
						$default_attributes[$taxonomy] = $attribute_value;
					}

					// Aggiorna gli attributi predefiniti del prodotto
					update_post_meta($product_id, '_default_attributes', $default_attributes);

					// Gestione immagine
					$variation_obj = wc_get_product($lowest_price_variation['variation_id']);
					if ($variation_obj) {
						$image_id = $variation_obj->get_image_id();

						// Se la variante ha un'immagine, impostala come principale
						if ($image_id) {
							// Salva l'immagine principale corrente come meta se non è già una variante
							$current_image_id = get_post_thumbnail_id($product_id);
							if ($current_image_id && $current_image_id != $image_id) {
								update_post_meta(
									$product_id,
									'_original_thumbnail_id',
									$current_image_id,
								);
							}

							// Imposta la nuova immagine principale
							set_post_thumbnail($product_id, $image_id);
						} else {
							// Cerca la prima variante con un'immagine
							foreach ($available_variations as $variation) {
								$var_obj = wc_get_product($variation['variation_id']);
								if ($var_obj && $var_obj->get_image_id()) {
									$image_id = $var_obj->get_image_id();
									set_post_thumbnail($product_id, $image_id);
									break;
								}
							}
						}
					}

					return $lowest_price_variation['variation_id'];
				}
			}
		}

		return null;
	}
}
