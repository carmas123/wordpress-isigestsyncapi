<?php
/**
 * Gestione delle immagini dei prodotti
 *
 * @package    ISIGestSyncAPI
 * @subpackage Services
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Services;

use ISIGestSyncAPI\Core\ISIGestSyncApiNotFoundException;
use ISIGestSyncAPI\Core\Utilities;
use ISIGestSyncAPI\Core\ISIGestSyncApiException;
use ISIGestSyncAPI\Core\ISIGestSyncApiBadRequestException;

/**
 * Classe ImageService per la gestione delle immagini.
 *
 * @since 1.0.0
 */
class CategoryService extends BaseService {
	/**
	 * Gestisce ricorsivamente la visibilità delle categorie WooCommerce
	 * con ottimizzazioni delle performance
	 *
	 * @deprecated QUESTO NON SERVE PERCHE' NASCONDERE LE CATEGORIE VUOTE E' COMPITO DEL TEMA E DEL WIDGET DELLE CATEGORIE
	 * @return void
	 */
	public static function refreshCategoriesVisibility() {
		global $wpdb;

		// Ottieni tutte le categorie
		$categories = get_terms([
			'taxonomy' => 'product_cat',
			'hide_empty' => false,
			'hierarchical' => true,
		]);

		if (is_wp_error($categories) || empty($categories)) {
			return;
		}

		// Prepara la mappa delle categorie
		$categories_map = [];
		foreach ($categories as $category) {
			$categories_map[$category->term_id] = [
				'term' => $category,
				'has_visible_content' => false,
				'product_count' => 0,
			];
		}

		// Query per ottenere i prodotti visibili per categoria
		$visible_products_query = "
            SELECT tt.term_id, COUNT(DISTINCT p.ID) as product_count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            LEFT JOIN {$wpdb->postmeta} pm_visibility ON (p.ID = pm_visibility.post_id AND pm_visibility.meta_key = '_visibility')
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND tt.taxonomy = 'product_cat'
            AND (pm_visibility.meta_value IS NULL OR pm_visibility.meta_value != 'hidden')
            GROUP BY tt.term_id";

		$results = $wpdb->get_results($visible_products_query);

		// Aggiorna il conteggio dei prodotti
		foreach ($results as $result) {
			if (isset($categories_map[$result->term_id])) {
				$categories_map[$result->term_id]['has_visible_content'] =
					$result->product_count > 0;
				$categories_map[$result->term_id]['product_count'] = (int) $result->product_count;
			}
		}

		// Funzione ricorsiva per verificare la visibilità
		$check_visibility = function ($term_id) use (&$check_visibility, &$categories_map) {
			$category_data = &$categories_map[$term_id];

			// Se ha prodotti visibili, è già true
			if ($category_data['has_visible_content']) {
				return true;
			}

			// Controlla le sottocategorie
			foreach ($categories_map as $other_term_id => $other_category) {
				if ($other_category['term']->parent === $term_id) {
					if ($check_visibility($other_term_id)) {
						$category_data['has_visible_content'] = true;
						$category_data['product_count'] += $other_category['product_count'];
						return true;
					}
				}
			}

			return false;
		};

		// Processa le categorie principali
		foreach ($categories_map as $term_id => $category_data) {
			if ($category_data['term']->parent === 0) {
				$check_visibility($term_id);
			}
		}

		// Aggiorna la visibilità delle categorie
		foreach ($categories_map as $term_id => $category_data) {
			$current_excluded = get_term_meta($term_id, 'excluded_from_catalog', true);
			$current_count = get_term_meta($term_id, 'product_count_product_cat', true);

			if ($category_data['has_visible_content']) {
				// Mostra la categoria solo se è attualmente nascosta
				if ($current_excluded === '1') {
					update_term_meta($term_id, 'excluded_from_catalog', '0');
				}
				// Aggiorna il conteggio solo se è cambiato
				if ((int) $current_count !== $category_data['product_count']) {
					update_term_meta(
						$term_id,
						'product_count_product_cat',
						$category_data['product_count'],
					);
				}
			} else {
				// Nascondi la categoria solo se è attualmente visibile
				if ($current_excluded !== '1') {
					update_term_meta($term_id, 'excluded_from_catalog', '1');
				}
				// Azzera il conteggio solo se non è già zero
				if ($current_count !== '0') {
					update_term_meta($term_id, 'product_count_product_cat', 0);
				}
			}
		}

		// Pulisci la cache delle categorie
		clean_term_cache(array_keys($categories_map), 'product_cat');
	}
}
