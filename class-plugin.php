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
		$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}isi_api_product` (
			`post_id` bigint(20) NOT NULL,                    /* ID del post WooCommerce */
			`variation_id` bigint(20) NOT NULL DEFAULT 0,     /* ID della variazione WooCommerce */
			`sku` varchar(64) NOT NULL,                       /* SKU del prodotto */
			`is_variable` tinyint(1) NOT NULL DEFAULT 0,      /* Indica se è un prodotto variabile */
			`discount_group` varchar(32) DEFAULT NULL,        /* Gruppo sconto */
			`product_group` varchar(32) DEFAULT NULL,         /* Gruppo merceologico */
			`product_subgroup` varchar(32) DEFAULT NULL,      /* Sottogruppo merceologico */
			`brand` varchar(32) DEFAULT NULL,                 /* Marca */
			`season` varchar(32) DEFAULT NULL,                /* Stagione */
			`year` int(11) DEFAULT NULL,                      /* Anno */
			`unit` varchar(32) DEFAULT NULL,                  /* Unità di misura */
			`unit_conversion` decimal(15,6) DEFAULT 1.000000, /* Conversione unità */
			`secondary_unit` varchar(32) DEFAULT NULL,        /* Unità secondaria */
			`use_secondary_unit` tinyint(1) DEFAULT 0,        /* Usa unità secondaria */
			`created_at` datetime DEFAULT NULL,               /* Data creazione */
			`updated_at` datetime DEFAULT NULL,               /* Data aggiornamento */
			PRIMARY KEY (`post_id`,`variation_id`),
			KEY `IDX_SKU` (`sku`)
		) $charset_collate;";

		// Tabella per lo storico dello stock
		$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}isi_api_stock` (
			`post_id` bigint(20) NOT NULL,
			`variation_id` bigint(20) NOT NULL DEFAULT 0,
			`sku` varchar(64) NOT NULL,
			`warehouse` varchar(32) NOT NULL,
			`stock_quantity` int(11) NOT NULL DEFAULT 0,
			`stock_status` varchar(32) DEFAULT 'instock',     /* Stato stock WooCommerce */
			`updated_at` datetime DEFAULT NULL,
			PRIMARY KEY (`post_id`,`variation_id`,`warehouse`),
			KEY `IDX_SKU` (`sku`)
		) $charset_collate;";

		// Tabella per l'export dei prodotti
		$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}isi_api_export_product` (
			`post_id` bigint(20) NOT NULL,
			`is_exported` tinyint(1) NOT NULL DEFAULT 0,
			`exported_at` datetime DEFAULT NULL,
			`last_sync_hash` varchar(32) DEFAULT NULL,        /* Hash per verificare modifiche */
			PRIMARY KEY (`post_id`)
		) $charset_collate;";

		// Tabella per le caratteristiche dei prodotti
		$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}isi_api_attribute` (
			`id` bigint(20) NOT NULL AUTO_INCREMENT,
			`code` varchar(128) NOT NULL,
			`attribute_id` bigint(20) NOT NULL,              /* ID attributo WooCommerce */
			`attribute_key` varchar(32) NOT NULL,            /* Chiave attributo WooCommerce */
			`created_at` datetime DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `UK_CODE` (`code`)
		) $charset_collate;";

		// Tabella per le offerte dei prodotti
		$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}isi_api_product_offers` (
			`id` bigint(20) NOT NULL AUTO_INCREMENT,
			`post_id` bigint(20) NOT NULL,
			`variation_id` bigint(20) NOT NULL DEFAULT 0,
			`discount_amount` decimal(20,6) NOT NULL DEFAULT 0,
			`discount_type` varchar(20) DEFAULT 'fixed',      /* Tipo sconto WooCommerce */
			`min_quantity` int(11) NOT NULL DEFAULT 1,
			`date_from` datetime DEFAULT NULL,
			`date_to` datetime DEFAULT NULL,
			`created_at` datetime DEFAULT NULL,
			`updated_at` datetime DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `IDX_PRODUCT` (`post_id`,`variation_id`)
		) $charset_collate;";

		// Tabella per il multimagazzino
		$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}isi_api_warehouse` (
			`post_id` bigint(20) NOT NULL,
			`variation_id` bigint(20) NOT NULL DEFAULT 0,
			`warehouse_code` varchar(32) NOT NULL,
			`stock_quantity` int(11) NOT NULL DEFAULT 0,
			`stock_status` varchar(32) DEFAULT 'instock',
			`manage_stock` tinyint(1) DEFAULT 1,             /* Gestione stock WooCommerce */
			`updated_at` datetime DEFAULT NULL,
			PRIMARY KEY (`post_id`,`variation_id`,`warehouse_code`),
			KEY `IDX_WAREHOUSE` (`warehouse_code`)
		) $charset_collate;";

		// Tabella per variazioni (taglie/colori)
		$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}isi_api_variations` (
			`post_id` bigint(20) NOT NULL,
			`reference` varchar(64) NOT NULL,
			`attribute_data` longtext DEFAULT NULL,          /* JSON dei dati attributi */
			`updated_at` datetime DEFAULT NULL,
			PRIMARY KEY (`post_id`),
			KEY `IDX_REFERENCE` (`reference`)
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
