<?php
/**
 * Gestione delle funzioni customizzate
 *
 * @package    ISIGestSyncAPI
 * @subpackage Core
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Core;

class CustomFunctionsManager {
	private $custom_functions_file;
	private $custom_functions_dir;
	private static $instance = null;

	/**
	 * Singleton getInstance
	 */
	public static function getInstance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->custom_functions_dir = ISIGESTSYNCAPI_PLUGIN_DIR . 'custom';
		$this->custom_functions_file = $this->custom_functions_dir . '/functions.php';
	}

	/**
	 * Legge il contenuto del file delle funzioni custom
	 */
	public function getCustomFunctionsContent() {
		if (!file_exists($this->custom_functions_file)) {
			return "<?php\n";
		}
		return file_get_contents($this->custom_functions_file);
	}

	/**
	 * Salva il contenuto nel file delle funzioni custom
	 */
	public function saveCustomFunctionsContent($content) {
		// Validazione di base per assicurarsi che sia codice PHP
		if (strpos(trim($content), '<?php') !== 0) {
			$content = "<?php\n" . $content;
		}

		return file_put_contents($this->custom_functions_file, $content);
	}

	/**
	 * Carica il file delle funzioni custom
	 */
	public function loadCustomFunctions() {
		if (file_exists($this->custom_functions_file)) {
			require_once $this->custom_functions_file;
			return true;
		}
		return false;
	}

	public function getAvailableCustomFunctions(): array {
		return [
			'isigestsyncapi_func_product_customfields' => [
				'description' =>
					'Funzione per gestione i campi personalizzati dei prodotti, deve ritornare un array con la chiave del campo e il valore',
				'parameters' => [
					'product' => 'Riferimento al prodotto',
					'data' => 'Dati inviati da ISIGest',
				],
				'demo' => 'function isigestsyncapi_func_product_customfields($product, $data) {
						return ["_campo" => "Valore"];
					}',
			],
		];
	}

	/**
	 * Gestisce i campi personalizzati di un prodotto.
	 *
	 * Questa funzione si occupa di elaborare e aggiornare i campi personalizzati di un prodotto
	 * utilizzando una funzione di callback definita dall'utente.
	 *
	 * @param \WC_Product $product Il prodotto WooCommerce da aggiornare.
	 * @param array $data I dati del prodotto.
	 * @return bool Restituisce true se l'operazione è riuscita, false altrimenti.
	 */
	public function handleProductCustomFields($product, $data): bool {
		$func_name = 'isigestsyncapi_func_product_customfields';

		try {
			if (!function_exists($func_name) || !is_callable($func_name)) {
				return false;
			}

			$result = call_user_func_array($func_name, [
				'product' => $product,
				'data' => $data,
			]);

			if (!is_array($result)) {
				Utilities::logError(
					"Handle Custom Fields - Il risultato della funzione {$func_name} non è un array",
				);
				return false;
			}

			foreach ($result as $field_name => $field_value) {
				// Validazione del nome del campo
				$sanitized_field_name = sanitize_key($field_name);
				if ($sanitized_field_name !== $field_name) {
					Utilities::logDebug(
						"Handle Custom Fields - Campo personalizzato rinominato da {$field_name} a {$sanitized_field_name}",
					);
				}

				// Gestione del valore del campo
				if (is_array($field_value)) {
					$processed_value = wp_json_encode($field_value);
					if ($processed_value === false) {
						Utilities::logError(
							"Handle Custom Fields - Impossibile codificare il valore array per il campo {$sanitized_field_name}",
						);
					}
				} else {
					$processed_value = $field_value;
				}

				// Aggiornamento del meta del prodotto
				$update_result = update_post_meta(
					$product->get_id(),
					$sanitized_field_name,
					$processed_value,
				);

				if ($update_result === false) {
					Utilities::logWarn(
						"Handle Custom Fields - Impossibile aggiornare il meta {$sanitized_field_name} per il prodotto {$product->get_id()}",
					);
				}
			}

			return true;
		} catch (\Exception $e) {
			Utilities::logError(
				'Handle Custom Fields - Errore durante la gestione dei custom fields: ' .
					$e->getMessage(),
			);
			return false;
		}
	}
}
