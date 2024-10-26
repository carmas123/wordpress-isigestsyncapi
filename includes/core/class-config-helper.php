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
class ConfigHelper {
	/**
	 * Istanza singleton della classe.
	 *
	 * @var ConfigHelper
	 */
	private static $instance = null;

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
		'PRODUCTS_DISABLE_OUTOFSTOCK',
		'PRODUCTS_DISABLE_WITHOUT_IMAGE',
		'PRODUCTS_DISABLE_WITH_EMPTY_PRICE',
		'PRODUCTS_USE_CODE_AS_REFERENCE',
		'PRODUCTS_USE_STOCK_AS_QTY',
		'PRODUCTS_REFERENCE_IN_MPN',
		'PRODUCTS_SYNC_OFFER_AS_SPECIFIC_PRICES',
		'PRODUCTS_ADVANCED_PRICES',
		'PRODUCTS_MULTI_WAREHOUSES',
		'PRODUCTS_PRICE_WITHTAX',
		'PRODUCTS_PRICE_ROUND_NET',
		'PRODUCTS_REFERENCE_MODE',

		// Blocca Aggiornamenti
		'PRODUCTS_DONT_SYNC_EAN',
		'PRODUCTS_DONT_SYNC_NAME',
		'PRODUCTS_DONT_SYNC_REFERENCE',
		'PRODUCTS_DONT_SYNC_CATEGORIES',
		'PRODUCTS_DONT_SYNC_DIMENSION_AND_WEIGHT',
		'PRODUCTS_DONT_SYNC_PRICES',

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
	private $string_fields = ['API_KEY', 'TC_SIZE_ATTRIBUTE', 'TC_COLOR_ATTRIBUTE'];

	/**
	 * Definizioni delle opzioni intere.
	 *
	 * @var array
	 */
	private $int_fields = ['PRODUCTS_DESCRIPTION', 'PRODUCTS_SHORT_DESCRIPTION'];

	/**
	 * Costruttore privato (pattern Singleton).
	 */
	private function __construct() {
		// Carica le opzioni di default se necessario
		$this->maybe_load_defaults();
	}

	/**
	 * Ottiene l'istanza della classe.
	 *
	 * @return ConfigHelper
	 */
	public static function getInstance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
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
			'API_KEY' => $this->generate_api_key(),

			// Prodotti
			'PRODUCTS_DISABLE_OUTOFSTOCK' => true,
			'PRODUCTS_DISABLE_WITHOUT_IMAGE' => false,
			'PRODUCTS_DISABLE_WITH_EMPTY_PRICE' => true,
			'PRODUCTS_USE_CODE_AS_REFERENCE' => false,
			'PRODUCTS_USE_STOCK_AS_QTY' => true,
			'PRODUCTS_REFERENCE_IN_MPN' => false,
			'PRODUCTS_SYNC_OFFER_AS_SPECIFIC_PRICES' => true,
			'PRODUCTS_ADVANCED_PRICES' => false,
			'PRODUCTS_MULTI_WAREHOUSES' => false,
			'PRODUCTS_PRICE_WITHTAX' => false,
			'PRODUCTS_PRICE_ROUND_NET' => true,
			'PRODUCTS_REFERENCE_MODE' => false,

			// Descrizioni
			'PRODUCTS_DESCRIPTION' => 0,
			'PRODUCTS_SHORT_DESCRIPTION' => 0,

			// Non sincronizzare
			'PRODUCTS_DONT_SYNC_EAN' => false,
			'PRODUCTS_DONT_SYNC_NAME' => false,
			'PRODUCTS_DONT_SYNC_REFERENCE' => false,
			'PRODUCTS_DONT_SYNC_CATEGORIES' => false,
			'PRODUCTS_DONT_SYNC_DIMENSION_AND_WEIGHT' => false,
			'PRODUCTS_DONT_SYNC_PRICES' => false,

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
}
