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
		// Categorie
		'categories_disable_empty',

		// Prodotti
		'products_disable_outofstock',
		'products_disable_without_image',
		'products_disable_empty_price',
		'products_multi_warehouse',
		'products_price_withtax',
		'products_round_net_price',
		'products_reference_mode',
		'products_passive_mode',
		'products_reference_hidden',
		'products_brand_hidden',
		'products_featured_hidden',

		// Blocca Aggiornamenti
		'products_dont_sync_ean',
		'products_dont_sync_featured_flag',
		'products_dont_sync_categories',
		'products_dont_sync_dimension_and_weight',
		'products_dont_sync_prices',

		// Clienti
		'customers_send_push_on_signedup',

		// Pushover
		'pushover_enabled',
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
	private $int_fields = [
		'products_name',
		'products_description',
		'products_short_description',
		'products_stock_qty',
	];

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
		// Verifichiamo se la chiave è booleana
		$is_boolean =
			in_array($key, $this->boolean_fields, true) ||
			strpos($key, 'orders_export_status_') === 0;

		// Valida e converte il valore in base al tipo di campo
		if ($is_boolean) {
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

			// Categorie
			'categories_disable_empty' => false,

			// Prodotti
			'products_disable_outofstock' => true,
			'products_disable_without_image' => true,
			'products_disable_empty_price' => true,
			'products_price_withtax' => true,
			'products_round_net_price' => false,
			'products_reference_mode' => false,
			'products_passive_mode' => false,

			// Taglie&Colori

			// Descrizioni
			'products_name' => 0,
			'products_description' => 0,
			'products_short_description' => 0,

			// Non sincronizzare
			'products_dont_sync_categories' => false,
			'products_dont_sync_ean' => false,
			'products_dont_sync_featured_flag' => false,
			'products_dont_sync_brand' => false,
			'products_dont_sync_prices' => false,
			'products_dont_sync_dimension_and_weight' => false,
			'products_dont_sync_stocks' => false,

			// Clienti
			'customers_send_push_on_signedup' => false,

			// Ordini
			'orders_export_with_payment_bacs' => false,
			'orders_export_with_payment_cheque' => false,

			// Pushover
			'pushover_enabled' => false,
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

	public function clearCache() {
		$this->options_cache = [];
	}
}
