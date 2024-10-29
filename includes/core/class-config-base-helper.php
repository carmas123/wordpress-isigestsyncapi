<?php
/**
 * Gestione della configurazione del plugin
 *
 * @package    ISIGestSyncAPI
 * @subpackage Core
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Core;

/**
 * Classe ConfigHelper per la gestione della configurazione.
 *
 * @since 1.0.0
 */
abstract class ConfigBaseHelper {
	/**
	 * Prefisso per le opzioni del plugin.
	 *
	 * @var string
	 */
	private $prefix = 'isigestsyncapi_';

	/**
	 * Cache delle opzioni.
	 *
	 * @var array
	 */
	private $options_cache = [];

	/**
	 * Definizioni delle opzioni booleane.
	 *
	 * @var array
	 */
	private $boolean_fields = [
		// Prodotti
		'products_disable_outofstock',
		'products_disable_without_image',
		'products_disable_empty_price',
		'PRODUCTS_USE_CODE_AS_REFERENCE',
		'products_use_stock_qty',
		'PRODUCTS_REFERENCE_IN_MPN',
		'PRODUCTS_ADVANCED_PRICES',
		'PRODUCTS_MULTI_WAREHOUSES',
		'products_price_withtax',
		'products_round_net_price',
		'products_reference_mode',

		// Blocca Aggiornamenti
		'products_dont_sync_ean',
		'products_dont_sync_name',
		'PRODUCTS_DONT_SYNC_REFERENCE',
		'products_dont_sync_categories',
		'products_dont_sync_dimension_and_weight',
		'products_dont_sync_prices',

		// Taglie & Colori
		'TC_ENABLED',
		'TC_SPLIT_BY_COLOR',
		'TC_ADD_COLOR_TO_NAME',
	];

	/**
	 * Definizioni delle opzioni stringa.
	 *
	 * @var array
	 */
	private $string_fields = ['api_key', 'sizeandcolor_size_key', 'sizeandcolor_color_key'];

	/**
	 * Definizioni delle opzioni intere.
	 *
	 * @var array
	 */
	private $int_fields = ['products_name', 'products_description', 'products_short_description'];

	/**
	 * Costruttore privato (pattern Singleton).
	 */
	protected function __construct() {
		// Carica le opzioni di default se necessario
		$this->maybe_load_defaults();
	}

	/**
	 * Ottiene il valore di un'opzione.
	 *
	 * @param string $key     Chiave dell'opzione.
	 * @param mixed  $default Valore di default.
	 * @return mixed
	 */
	public function get($key, $default = null) {
		// Se il valore è in cache, lo restituiamo
		if (isset($this->options_cache[$key])) {
			return $this->options_cache[$key];
		}

		$option_name = $this->prefix . $key;
		$value = get_option($option_name, $default);

		// Converte il valore in base al tipo di campo
		if (in_array($key, $this->boolean_fields, true)) {
			$value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
		} elseif (in_array($key, $this->int_fields, true)) {
			$value = (int) $value;
		}

		// Memorizza in cache
		$this->options_cache[$key] = $value;

		return $value;
	}

	/**
	 * Imposta il valore di un'opzione.
	 *
	 * @param string $key   Chiave dell'opzione.
	 * @param mixed  $value Valore da impostare.
	 * @return boolean
	 */
	public function set($key, $value) {
		// Valida e converte il valore in base al tipo di campo
		if (in_array($key, $this->boolean_fields, true)) {
			$value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
		} elseif (in_array($key, $this->int_fields, true)) {
			$value = (int) $value;
		}

		$option_name = $this->prefix . $key;
		$result = update_option($option_name, $value);

		// Aggiorna la cache
		if ($result) {
			$this->options_cache[$key] = $value;
		}

		return $result;
	}

	/**
	 * Elimina un'opzione.
	 *
	 * @param string $key Chiave dell'opzione.
	 * @return boolean
	 */
	public function delete($key) {
		$option_name = $this->prefix . $key;
		$result = delete_option($option_name);

		// Rimuove dalla cache
		if ($result && isset($this->options_cache[$key])) {
			unset($this->options_cache[$key]);
		}

		return $result;
	}

	/**
	 * Carica le opzioni di default se necessario.
	 *
	 * @return void
	 */
	private function maybe_load_defaults() {
		// Verifica se le opzioni sono già state inizializzate
		$initialized = $this->get('initialized', false);
		if (!$initialized) {
			$this->load_default_options();
			$this->set('initialized', true);
		}
	}

	/**
	 * Carica le opzioni di default.
	 *
	 * @return void
	 */
	private function load_default_options() {
		$defaults = [
			// API
			'api_key' => $this->generate_api_key(),

			// Prodotti
			'products_disable_outofstock' => true,
			'products_disable_without_image' => true,
			'products_disable_empty_price' => true,
			'PRODUCTS_USE_CODE_AS_REFERENCE' => false,
			'products_use_stock_qty' => true,
			'PRODUCTS_REFERENCE_IN_MPN' => false,
			'PRODUCTS_ADVANCED_PRICES' => false,
			'PRODUCTS_MULTI_WAREHOUSES' => false,
			'products_price_withtax' => false,
			'products_round_net_price' => true,
			'products_reference_mode' => false,

			// Descrizioni
			'products_name' => 0,
			'products_description' => 0,
			'products_short_description' => 0,

			// Non sincronizzare
			'products_dont_sync_ean' => false,
			'products_dont_sync_name' => false,
			'PRODUCTS_DONT_SYNC_REFERENCE' => false,
			'products_dont_sync_categories' => false,
			'products_dont_sync_dimension_and_weight' => false,
			'products_dont_sync_prices' => false,

			// Taglie & Colori
			'TC_ENABLED' => false,
			'TC_SPLIT_BY_COLOR' => false,
			'TC_ADD_COLOR_TO_NAME' => false,
			'TC_SIZE_ATTRIBUTE' => '',
			'TC_COLOR_ATTRIBUTE' => '',
		];

		foreach ($defaults as $key => $value) {
			$this->set($key, $value);
		}
	}

	/**
	 * Genera una nuova API key.
	 *
	 * @return string
	 */
	private function generate_api_key() {
		return wp_generate_password(32, false);
	}

	/**
	 * Ottiene tutte le opzioni.
	 *
	 * @return array
	 */
	public function get_all() {
		$options = [];

		// Combina tutti i campi
		$all_fields = array_merge($this->boolean_fields, $this->string_fields, $this->int_fields);

		// Ottiene i valori per ogni campo
		foreach ($all_fields as $field) {
			$options[$field] = $this->get($field);
		}

		return $options;
	}

	/**
	 * Resetta tutte le opzioni ai valori di default.
	 *
	 * @return void
	 */
	public function reset() {
		// Elimina tutte le opzioni esistenti
		$all_fields = array_merge($this->boolean_fields, $this->string_fields, $this->int_fields);

		foreach ($all_fields as $field) {
			$this->delete($field);
		}

		// Ricarica i default
		$this->load_default_options();
	}

	// Altre proprietà
}
