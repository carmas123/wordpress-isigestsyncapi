<?php
/**
 * Plugin Name: ISIGest Sync API
 * Description: Plugin per la sincronizzazione dei prodotti tramite API
 * Version: 1.0.6
 * Author: ISIGest S.r.l.
 * Author URI: https://www.isigest.net
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

// Definizioni costanti
define('ISIGEST_SYNC_API_VERSION', '1.0.6');
define('ISIGESTSYNCAPI_PLUGIN_FILE', __FILE__);
define('ISIGEST_SYNC_API_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ISIGEST_SYNC_API_PLUGIN_URL', plugin_dir_url(__FILE__));

// Carica l'autoloader
require_once ISIGEST_SYNC_API_PLUGIN_DIR . '/includes/autoload.php';

if (!defined('WP_USE_THEMES')) {
	define('WP_USE_THEMES', false);
}
require_once ISIGEST_SYNC_API_PLUGIN_DIR . '../../../wp-load.php';

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
		// Inizializzazione base al momento giusto
		add_action('plugins_loaded', [$this, 'init']);

		// Aggiungi link alle impostazioni nella pagina dei plugin
		add_filter('plugin_action_links_' . plugin_basename(ISIGESTSYNCAPI_PLUGIN_FILE), [
			$this,
			'addPluginActionLinks',
		]);

		// Aggiungi il menu nel backend
		if (is_admin()) {
			add_action('admin_menu', [$this, 'addAdminMenuItems']);
			add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
		}
	}

	public function addAdminMenuItems() {
		add_menu_page(
			__('ISIGest Sync', 'isigestsyncapi'), // Titolo pagina
			__('ISIGest Sync', 'isigestsyncapi'), // Titolo menu
			'manage_woocommerce', // Capability richiesta
			'isigestsyncapi-settings', // Slug menu
			[$this, 'renderSettingsPage'], // Callback
			'dashicons-update', // Icona
			56, // Posizione
		);

		// Sottomenu opzionale
		add_submenu_page(
			'isigestsyncapi-settings', // Parent slug
			__('Settings', 'isigestsyncapi'), // Titolo pagina
			__('Settings', 'isigestsyncapi'), // Titolo menu
			'manage_woocommerce', // Capability richiesta
			'isigestsyncapi-settings', // Slug menu
			[$this, 'renderSettingsPage'], // Callback
		);
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
	public function init() {
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
		// AGGIUNGERE QUI LE DIPENDENZE RICHIESTE SE NECESSARIE
		// SOLO SE NON GESTITI TRAMITE AUTOLOAD
		require_once ISIGEST_SYNC_API_PLUGIN_DIR . '/includes/core/class-exceptions.php';
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
		$p = $wpdb->prefix;

		$sql = [];

		// Tabella per la storicizzazione dei prodotti
		$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}isi_api_product` (
			`post_id` int(10) NOT NULL,                    		/* ID del post WooCommerce */
			`variation_id` int(10) NOT NULL DEFAULT 0,     		/* ID della variazione WooCommerce */
			`sku` char(5) NOT NULL,                       		/* SKU del prodotto */
			`is_tc` tinyint(1) NOT NULL DEFAULT 0,      	  	/* Indica se è un prodotto a taglie&colori */
			`fascia_sconto_art` char(3) DEFAULT NULL,    	    /* Gruppo sconto */
			`gruppo_merc` char(3) DEFAULT NULL,      	 	  	/* Gruppo merceologico */
			`sottogruppo_merc` char(3) DEFAULT NULL,     		/* Sottogruppo merceologico */
			`marca` char(32) DEFAULT NULL,                		/* Marca */
			`stagione` char(32) DEFAULT NULL,                	/* Stagione */
			`anno` int(4) DEFAULT NULL,                     	/* Anno */
			`unit` char(2) DEFAULT NULL,                  	/* Unità di misura */
			`unit_conversion` decimal(15,6) DEFAULT 1.000000, 	/* Conversione unità */
			`secondary_unit` char(2) DEFAULT NULL,        		/* Unità secondaria */
			`use_secondary_unit` tinyint(1) DEFAULT 0,        	/* Usa unità secondaria */
			PRIMARY KEY (`sku`)
		) $charset_collate;";

		$sql[] =
			'ALTER TABLE `' .
			$p .
			'isi_api_product` ADD INDEX idx_isi_api_product_1 (`post_id`) USING BTREE;';
		$sql[] =
			'ALTER TABLE `' .
			$p .
			'isi_api_product` ADD UNIQUE INDEX idx_isi_api_product_2 (`post_id`, `variation_id`) USING BTREE;';

		// Tabella per lo storico dello stock
		$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}isi_api_stock` (
			`post_id` bigint(20) NOT NULL,
			`variation_id` bigint(20) NOT NULL DEFAULT 0,
			`sku` varchar(64) NOT NULL,
			`warehouse` varchar(32) NOT NULL,
			`stock_quantity` int(11) NOT NULL DEFAULT 0,
			`stock_status` varchar(32) DEFAULT 'instock',     /* Stato stock WooCommerce */
			PRIMARY KEY (`post_id`,`variation_id`,`warehouse`),
			KEY `IDX_SKU` (`sku`)
		) $charset_collate;";

		// Tabella per l'export dei prodotti
		$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}isi_api_export_product` (
			`post_id` bigint(20) NOT NULL,
			`is_exported` tinyint(1) NOT NULL DEFAULT 0,
			`exported_at` datetime DEFAULT NULL,
			PRIMARY KEY (`post_id`)
		) $charset_collate;";

		// Tabella per il multimagazzino
		$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}isi_api_warehouse` (
			`post_id` bigint(20) NOT NULL,
			`variation_id` bigint(20) NOT NULL DEFAULT 0,
			`warehouse` varchar(32) NOT NULL,
			`stock_quantity` int(11) NOT NULL DEFAULT 0,
			`stock_status` varchar(32) DEFAULT 'instock',
			`manage_stock` tinyint(1) DEFAULT 1,             /* Gestione stock WooCommerce */
			`updated_at` datetime DEFAULT NULL,
			PRIMARY KEY (`post_id`,`variation_id`,`warehouse`),
			KEY `IDX_WAREHOUSE` (`warehouse`)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ($sql as $query) {
			dbDelta($query);
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

	/**
	 * Aggiunge i link alle impostazioni nella pagina dei plugin.
	 *
	 * @param array $links Array dei link esistenti.
	 * @return array
	 */
	public function addPluginActionLinks($links) {
		$settings_link =
			'<a href="' .
			admin_url('admin.php?page=isigestsyncapi-settings') .
			'">' .
			__('Settings', 'isigestsyncapi') .
			'</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	/**
	 * Aggiunge la voce di menu in WooCommerce.
	 *
	 * @return void
	 */
	public function addAdminMenu() {
		add_submenu_page(
			'woocommerce', // Parent slug
			'ISIGest Sync', // Page title
			'ISIGest Sync', // Menu title
			'manage_woocommerce', // Capability
			'isigestsyncapi-settings', // Menu slug
			[$this, 'renderSettingsPage'], // Callback function
		);
	}

	/**
	 * Carica gli asset necessari nell'admin.
	 *
	 * @param string $hook L'hook della pagina corrente.
	 * @return void
	 */
	public function enqueueAdminAssets($hook) {
		if (
			$hook !== 'toplevel_page_isigestsyncapi-settings' &&
			$hook !== 'isigest-sync_page_isigestsyncapi-settings'
		) {
			return;
		}

		wp_enqueue_style(
			'isigestsyncapi-admin',
			plugin_dir_url(ISIGESTSYNCAPI_PLUGIN_FILE) . 'assets/css/admin.css',
			[],
			'1.0.0',
		);

		wp_enqueue_script(
			'isigestsyncapi-admin',
			plugin_dir_url(ISIGESTSYNCAPI_PLUGIN_FILE) . 'assets/js/admin.js',
			['jquery'],
			'1.0.0',
			true,
		);

		wp_localize_script('isigestsyncapi-admin', 'isigestsyncapi', [
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('isigestsyncapi-settings'),
		]);
	}

	/**
	 * Renderizza la pagina delle impostazioni.
	 *
	 * @return void
	 */
	public function renderSettingsPage() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = new Admin\Settings();
		$settings->renderSettingsPage();
	}
}

// Inizializza il plugin
Plugin::getInstance();
