<?php
/**
 * Gestione dello stato dei prodotti
 *
 * @package    ISIGestSyncAPI
 * @subpackage Services
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Services;

use ISIGestSyncAPI\Core\ConfigHelper;
use ISIGestSyncAPI\Core\DbHelper;
use ISIGestSyncAPI\Core\Utilities;

/**
 * Classe ProductStatusHandler per la gestione dello stato dei prodotti.
 *
 * @since 1.0.0
 */
class ProductStatusHandler extends BaseService {
	/**
	 * Costruttore.
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Verifica e aggiorna lo stato di un prodotto.
	 *
	 * @param integer $product_id   ID del prodotto.
	 * @param boolean $is_variant 	Indica se è una variante
	 * @param boolean $force_disable Forza la disattivazione del prodotto (Default false).
	 * @return boolean True se lo stato è stato aggiornato.
	 */
	public static function checkAndUpdateProductStatus(
		$product_id,
		$is_variant,
		$force_disable = false
	) {
		$product = wc_get_product($product_id);
		if (!$product) {
			return false;
		}

		$should_be_active = !$force_disable && self::shouldProductBeActive($product);
		$current_status = $product->get_status();
		$new_status = $should_be_active ? 'publish' : ($is_variant ? 'private' : 'draft');

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
			);

			// Aggiorniamo lo stato del Padre
			if ($is_variant) {
				self::checkAndUpdateParentStatus($product_id);
			}

			return true;
		}

		return false;
	}

	/**
	 * Verifica e aggiorna lo stato del prodotto padre basato sullo stato delle sue varianti.
	 *
	 * @param integer $product_id ID del prodotto variante.
	 * @return boolean True se lo stato del padre è stato aggiornato.
	 */
	private static function checkAndUpdateParentStatus($product_id) {
		// Azzeriamo la cache
		Utilities::cleanProductCache($product_id);

		// Ottieni il prodotto variante
		$variant = wc_get_product($product_id);
		if (!$variant || !$variant->is_type('variation')) {
			return false;
		}

		// Ottieni il prodotto padre
		$parent_id = $variant->get_parent_id();

		// Azzeriamo la cache
		Utilities::cleanProductCache($parent_id);

		$parent = wc_get_product($parent_id);
		if (!$parent) {
			return false;
		}

		// Ottieni tutte le varianti usando get_children()
		$variation_ids = $parent->get_children();

		// Se non ci sono varianti (caso improbabile dato che stiamo processando una variante)
		// ma meglio mantenere il controllo per sicurezza
		if (empty($variation_ids)) {
			Utilities::log(
				sprintf(
					'Nessuna variante trovata per il prodotto padre %d (caso inatteso)',
					$parent_id,
				),
				'warning',
			);
			return false;
		}

		// Controlla lo stato di tutte le varianti
		$all_inactive = true;
		foreach ($variation_ids as $variation_id) {
			// Azzeriamo la cache
			Utilities::cleanProductCache($variation_id);

			$variation_product = wc_get_product($variation_id);
			if ($variation_product && $variation_product->get_status() === 'publish') {
				$all_inactive = false;
				break;
			}
		}

		// Se tutte le varianti sono disattivate, disattiva il padre
		if ($all_inactive && $parent->get_status() !== 'draft') {
			$parent->set_status('draft');
			$parent->save();

			// Log del cambio di stato
			Utilities::log(
				sprintf(
					'Stato prodotto padre %d impostato a draft perché tutte le varianti sono disattivate',
					$parent_id,
				),
			);

			return true;
		}

		// Se almeno una variante è attiva, assicurati che il padre sia pubblicato
		if (!$all_inactive && $parent->get_status() !== 'publish') {
			$parent->set_status('publish');
			$parent->save();

			// Log del cambio di stato
			Utilities::log(
				sprintf(
					'Stato prodotto padre %d impostato a publish perché almeno una variante è attiva',
					$parent_id,
				),
			);

			return true;
		}

		return false;
	}

	/**
	 * Determina se un prodotto dovrebbe essere attivo.
	 *
	 * @param \WC_Product $product       Il prodotto.
	 * @return boolean
	 */
	public static function shouldProductBeActive($product) {
		if ($product->is_type('variable')) {
			// La disattivazione viene eseguita solo per le varianti o prodotti semplici
			return true;
		}

		// Controllo immagine
		if (
			ConfigHelper::getInstance()->get('products_disable_without_image') &&
			!$product->get_image_id()
		) {
			Utilities::log(
				sprintf('Prodotto %d disattivato: nessuna immagine', $product->get_id()),
				'info',
			);
			return false;
		}

		// Controllo prezzo
		if (ConfigHelper::getInstance()->get('products_disable_empty_price')) {
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
		if (ConfigHelper::getInstance()->get('products_disable_outofstock')) {
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
