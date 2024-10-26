<?php
/**
 * File principale del plugin ISIGest Sync API
 *
 * @package    ISIGestSyncAPI
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 * @license    GPL-2.0-or-later
 */

namespace ISIGestSyncAPI;

// Se questo file viene chiamato direttamente, termina.
if (!defined('ABSPATH')) {
	exit();
}

/**
 * Classe principale del plugin.
 *
 * Questa classe è responsabile dell'inizializzazione del plugin
 * e del caricamento di tutte le sue dipendenze.
 *
 * @since 1.0.0
 */
class Plugin {
	/**
	 * Singola istanza del plugin (pattern Singleton).
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Plugin
	 */
	private static $instance = null;

	/**
	 * Prefisso utilizzato per hooks, nomi delle opzioni, ecc.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $prefix = 'isigestsyncapi';

	/**
	 * Gestore delle route API.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Core\Router
	 */
	private $router;

	/**
	 * Impedisce l'istanziazione diretta (pattern Singleton).
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Ottiene l'istanza del plugin, creandola se necessario.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return Plugin L'istanza singola del plugin.
	 */
	public static function getInstance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Inizializza il plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function init() {
		$this->loadDependencies();
		$this->registerHooks();
		$this->registerCustomTables();

		// Inizializza il router delle API
		$this->router = new Core\Router();

		// Inizializza le impostazioni admin
		if (is_admin()) {
			new Admin\Settings();
		}
	}

	/**
	 * Carica le dipendenze del plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function loadDependencies() {
		$base_path = plugin_dir_path(__FILE__) . 'includes/';

		// Core
		require_once $base_path . 'core/class-router.php';
		require_once $base_path . 'core/class-api-handler.php';
		require_once $base_path . 'core/class-config-helper.php';
		require_once $base_path . 'core/class-exceptions.php';
		require_once $base_path . 'core/class-utilities.php';

		// Services
		require_once $base_path . 'services/class-product-service.php';
		require_once $base_path . 'services/class-stock-service.php';
		require_once $base_path . 'services/class-image-service.php';
		require_once $base_path . 'services/class-product-status-handler.php';
		require_once $base_path . 'services/class-product-offers-handler.php';

		// Admin
		require_once $base_path . 'admin/class-settings.php';
		require_once $base_path . 'admin/class-settings-helper.php';
	}

	/**
	 * Registra gli hooks del plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function registerHooks() {
		// Hooks di attivazione/disattivazione
		register_activation_hook(__FILE__, [$this, 'activate']);
		register_deactivation_hook(__FILE__, [$this, 'deactivate']);

		// Hooks per le API
		add_action('init', [$this, 'handleApiRequest']);

		// Hooks per WooCommerce
		add_action('woocommerce_init', [$this, 'initializeWooCommerce']);
	}

	/**
	 * Registra le tabelle personalizzate del database.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function registerCustomTables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = [];

		// Tabella per la storicizzazione dei prodotti
		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}isi_api_product (
			id_product bigint(20) NOT NULL,
			id_product_attribute bigint(20) NOT NULL DEFAULT 0,
			codice varchar(64) NOT NULL,
			is_tc tinyint(1) NOT NULL DEFAULT 0,
			unity varchar(32) DEFAULT NULL,
			unitConversion decimal(15,6) DEFAULT 1.000000,
			secondaryUnity varchar(32) DEFAULT NULL,
			useSecondaryUnity tinyint(1) DEFAULT 0,
			PRIMARY KEY  (id_product,id_product_attribute),
			KEY IDX_CODICE (codice)
		) $charset_collate;";

		// Tabella per lo storico dello stock
		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}isi_api_stock (
			id_product bigint(20) NOT NULL,
			id_product_attribute bigint(20) NOT NULL DEFAULT 0,
			codice varchar(64) NOT NULL,
			warehouse varchar(32) NOT NULL,
			quantity int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id_product,id_product_attribute,warehouse),
			KEY IDX_CODICE (codice)
		) $charset_collate;";

		// Tabella per l'export dei prodotti
		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}isi_api_export_product (
			id_product bigint(20) NOT NULL,
			exported tinyint(1) NOT NULL DEFAULT 0,
			exported_at datetime DEFAULT NULL,
			PRIMARY KEY  (id_product)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ($sql as $query) {
			dbDelta($query);
		}
	}

	/**
	 * Handler per le richieste API.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function handleApiRequest() {
		if (strpos(trim($_SERVER['REQUEST_URI'], '/'), $this->prefix) !== false) {
			$this->router->handleRequest();
		}
	}

	/**
	 * Inizializza l'integrazione con WooCommerce.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function initializeWooCommerce() {
		// Verifica che WooCommerce sia attivo
		if (!class_exists('WooCommerce')) {
			add_action('admin_notices', function () {
				echo '<div class="error"><p>';
				echo esc_html__(
					'ISIGest Sync API richiede WooCommerce per funzionare.',
					'isigestsyncapi',
				);
				echo '</p></div>';
			});
			return;
		}
	}

	/**
	 * Attiva il plugin.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function activate() {
		$this->registerCustomTables();
		flush_rewrite_rules();
	}

	/**
	 * Disattiva il plugin.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}
}

// Inizializza il plugin
ISIGestSyncAPI\Plugin::getInstance();
