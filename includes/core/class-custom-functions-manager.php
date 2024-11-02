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
}
