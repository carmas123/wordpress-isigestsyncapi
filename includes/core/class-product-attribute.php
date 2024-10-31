<?php
/**
 *
 * @package    ISIGestSyncAPI
 * @subpackage core
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Core;

abstract class ProductAttribute {
	/**
	 * Ottiene o crea un attributo
	 *
	 * @param string $name Nome dell'attributo
	 * @return int
	 */
	public static function getOrCreateAttribute($name, $label = '', $has_archives = true) {
		global $wpdb;

		$attribute_name = wc_sanitize_taxonomy_name($name);
		$attribute_label = !empty($label) ? $label : ucfirst($name);

		$attribute_id = wc_attribute_taxonomy_id_by_name($attribute_name);

		if (is_wp_error($attribute_id)) {
			throw new ISIGestSyncApiException(
				"Errore nella creazione dell'attributo: " . $attribute_id->get_error_message(),
			);
		}

		if (!$attribute_id) {
			$attribute_id = wc_create_attribute([
				'name' => $attribute_label,
				'slug' => $attribute_name,
				'type' => 'select',
				'order_by' => 'menu_order',
				'has_archives' => $has_archives,
			]);

			if (is_wp_error($attribute_id)) {
				throw new ISIGestSyncApiException(
					"Errore nella creazione dell'attributo: " . $attribute_id->get_error_message(),
				);
			}

			if (!taxonomy_exists($attribute_name)) {
				register_taxonomy($attribute_name, 'product', [
					'hierarchical' => false,
					'label' => $attribute_label,
					'show_ui' => true,
					'query_var' => true,
					'rewrite' => ['slug' => $attribute_name],
				]);

				// Flush rewrite rules dopo la creazione della tassonomia
				flush_rewrite_rules();
			}
		}

		return $attribute_id;
	}

	/**
	 * Ottiene o crea un termine per un attributo
	 *
	 * @param string $value           Valore del termine
	 * @param string $attribute_name  Nome dell'attributo
	 * @return \WP_Term|null
	 */
	public static function getOrCreateTerm($value, $attribute_name) {
		$term = get_term_by('name', $value, $attribute_name);

		if (!$term) {
			$result = wp_insert_term($value, $attribute_name);
			if (is_wp_error($result)) {
				throw new ISIGestSyncApiException(
					'Errore nella creazione del termine: ' . $result->get_error_message(),
				);
			}
			$term = get_term($result['term_id'], $attribute_name);
		}

		return $term;
	}
}
