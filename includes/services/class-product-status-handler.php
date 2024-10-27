<?php
/**
 * Gestione dello stato dei prodotti
 *
 * @package    ISIGestSyncAPI
 * @subpackage Services
 * @author     Massimo Caroccia & Claude
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Services;

use ISIGestSyncAPI\Core\ConfigHelper;
use ISIGestSyncAPI\Core\Utilities;

/**
 * Classe ProductStatusHandler per la gestione dello stato dei prodotti.
 *
 * @since 1.0.0
 */
class ProductStatusHandler {
	/**
	 * Configurazione del plugin.
	 *
	 * @var ConfigHelper
	 */
	private $config;

	/**
	 * Costruttore.
	 */
	public function __construct() {
		$this->config = ConfigHelper::getInstance();
	}

	/**
	 * Verifica e aggiorna lo stato di un prodotto.
	 *
	 * @param integer $product_id    ID del prodotto.
	 * @param boolean $force_disable Forza la disattivazione.
	 * @return boolean True se lo stato è stato aggiornato.
	 */
	public function checkAndUpdateProductStatus($product_id, $force_disable = false) {
		$product = wc_get_product($product_id);
		if (!$product) {
			return false;
		}

		$should_be_active = $this->shouldProductBeActive($product, $force_disable);
		$current_status = $product->get_status();
		$new_status = $should_be_active ? 'publish' : 'draft';

		if ($current_status !== $new_status) {
			$product->set_status($new_status);
			$product->save();

			// Log del cambio di stato
			Utilities::log(
				sprintf(
					'Stato prodotto %d cambiato da %s a %s',
					$product_id,
					$current_status,
					$new_status,
				),
				'info',
			);

			// Se il prodotto è variabile, aggiorniamo anche le varianti
			if ($product->is_type('variable')) {
				foreach ($product->get_children() as $child_id) {
					$this->checkAndUpdateProductStatus($child_id, !$should_be_active);
				}
			}

			// Verifichiamo le categorie se necessario
			if ($this->config->get('CATEGORIES_DISABLE_IF_EMPTY')) {
				$this->checkCategories();
			}

			return true;
		}

		return false;
	}

	/**
	 * Determina se un prodotto dovrebbe essere attivo.
	 *
	 * @param \WC_Product $product       Il prodotto.
	 * @param boolean     $force_disable Forza la disattivazione.
	 * @return boolean
	 */
	private function shouldProductBeActive($product, $force_disable) {
		if ($force_disable) {
			return false;
		}

		// Controllo immagine
		if ($this->config->get('PRODUCTS_DISABLE_WITHOUT_IMAGE') && !$product->get_image_id()) {
			Utilities::log(
				sprintf('Prodotto %d disattivato: nessuna immagine', $product->get_id()),
				'info',
			);
			return false;
		}

		// Controllo prezzo
		if ($this->config->get('PRODUCTS_DISABLE_WITH_EMPTY_PRICE')) {
			$price = $product->get_regular_price();
			if (empty($price) || $price <= 0) {
				Utilities::log(
					sprintf('Prodotto %d disattivato: prezzo non valido', $product->get_id()),
					'info',
				);
				return false;
			}
		}

		// Controllo stock
		if ($this->config->get('PRODUCTS_DISABLE_OUTOFSTOCK')) {
			$stock_quantity = $product->get_stock_quantity();
			if (is_null($stock_quantity) || $stock_quantity <= 0) {
				Utilities::log(
					sprintf('Prodotto %d disattivato: stock esaurito', $product->get_id()),
					'info',
				);
				return false;
			}
		}

		return true;
	}

	/**
	 * Verifica e aggiorna lo stato delle categorie.
	 *
	 * @return void
	 */
	public function checkCategories() {
		$product_cats = get_terms([
			'taxonomy' => 'product_cat',
			'hide_empty' => false,
		]);

		if (is_wp_error($product_cats)) {
			return;
		}

		foreach ($product_cats as $category) {
			$this->checkCategoryStatus($category);
		}
	}

	/**
	 * Verifica e aggiorna lo stato di una categoria.
	 *
	 * @param \WP_Term $category La categoria da verificare.
	 * @return void
	 */
	private function checkCategoryStatus($category) {
		// Contiamo i prodotti attivi nella categoria
		$products_in_cat = wc_get_products([
			'status' => 'publish',
			'category' => [$category->slug],
			'limit' => 1,
		]);

		$should_be_active = !empty($products_in_cat);
		$is_currently_active = get_term_meta($category->term_id, '_active', true) !== 'no';

		if ($should_be_active !== $is_currently_active) {
			update_term_meta($category->term_id, '_active', $should_be_active ? 'yes' : 'no');

			// Aggiorniamo anche le categorie genitore
			$this->updateParentCategories($category->term_id, $should_be_active);

			Utilities::log(
				sprintf(
					'Stato categoria %s (%d) aggiornato a %s',
					$category->name,
					$category->term_id,
					$should_be_active ? 'attivo' : 'disattivo',
				),
				'info',
			);
		}
	}

	/**
	 * Aggiorna lo stato delle categorie genitore.
	 *
	 * @param integer $category_id     ID della categoria.
	 * @param boolean $child_is_active Se la categoria figlia è attiva.
	 * @return void
	 */
	private function updateParentCategories($category_id, $child_is_active) {
		$parents = get_ancestors($category_id, 'product_cat', 'taxonomy');

		foreach ($parents as $parent_id) {
			if ($child_is_active) {
				// Se il figlio è attivo, il genitore deve essere attivo
				update_term_meta($parent_id, '_active', 'yes');
			} else {
				// Se il figlio è disattivo, verifichiamo se ci sono altri figli attivi
				$other_children = get_terms([
					'taxonomy' => 'product_cat',
					'child_of' => $parent_id,
					'fields' => 'ids',
					'hide_empty' => false,
				]);

				$has_active_children = false;
				foreach ($other_children as $child_id) {
					if (get_term_meta($child_id, '_active', true) !== 'no') {
						$has_active_children = true;
						break;
					}
				}

				update_term_meta($parent_id, '_active', $has_active_children ? 'yes' : 'no');
			}
		}
	}
}
