<?php
/**
 * Plugin Name: ISIGest Sync API
 * Description: Plugin per la sincronizzazione dei prodotti tramite API
 * Version: 1.0.95
 * Author: ISIGest S.r.l.
 * Author URI: https://www.isigest.net
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package    ISIGestSyncAPI
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 * @license    GPL-2.0-or-later
 */

namespace ISIGestSyncAPI;

use ISIGestSyncAPI\Common\PushoverHooks;
use ISIGestSyncAPI\Core\ConfigHelper;
use ISIGestSyncAPI\Core\UpgradeHelper;

// Se questo file viene chiamato direttamente, termina.
if (!defined('WPINC')) {
	die();
}

// Definizioni costanti
define('ISIGESTSYNCAPI_VERSION', '1.0.95');
define('ISIGESTSYNCAPI_PLUGIN_FILE', __FILE__);
define('ISIGESTSYNCAPI_PLUGIN_DIR', plugin_dir_path(ISIGESTSYNCAPI_PLUGIN_FILE));
define('ISIGESTSYNCAPI_PLUGIN_URL', plugin_dir_url(ISIGESTSYNCAPI_PLUGIN_FILE));
define('ISIGESTSYNCAPI_LOGS_DIR', ISIGESTSYNCAPI_PLUGIN_DIR . 'logs');

// Carica l'autoloader
require_once ISIGESTSYNCAPI_PLUGIN_DIR . 'includes/autoload.php';

if (!defined('WP_USE_THEMES')) {
	define('WP_USE_THEMES', false);
}
require_once ISIGESTSYNCAPI_PLUGIN_DIR . '../../../wp-load.php';

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
			$this->initOrderColumns();
		}
	}

	/**
	 * Inizializza le colonne degli ordini.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	public function initOrderColumns() {
		// Per la visualizzazione classica (post-based)
		add_filter('manage_edit-shop_order_columns', [$this, 'addOrderExportColumn'], 20);
		add_action(
			'manage_shop_order_posts_custom_column',
			[$this, 'renderOrderExportColumn'],
			10,
			2,
		);
		add_filter('manage_edit-shop_order_sortable_columns', [
			$this,
			'makeOrderExportColumnSortable',
		]);

		// Per la visualizzazione HPOS (Custom Order Tables)
		add_filter(
			'manage_woocommerce_page_wc-orders_columns',
			[$this, 'addOrderExportColumn'],
			20,
		);
		add_action(
			'manage_woocommerce_page_wc-orders_custom_column',
			[$this, 'renderOrderExportColumn'],
			10,
			2,
		);
		add_filter('manage_woocommerce_page_wc-orders_sortable_columns', [
			$this,
			'makeOrderExportColumnSortable',
		]);

		// Gestione ordinamento
		add_action('pre_get_posts', [$this, 'handleOrderExportColumnSorting']);
		add_filter('woocommerce_order_list_table_prepare_items_query_args', [
			$this,
			'handleHPOSColumnSorting',
		]);
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

		// Gestione Aggiornamenti
		if (is_admin() && ConfigHelper::getDbNeedToBeUpgrade()) {
			$this->upgradeDb();
		}

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
		require_once ISIGESTSYNCAPI_PLUGIN_DIR . 'includes/core/class-exceptions.php';
		require_once ISIGESTSYNCAPI_PLUGIN_DIR . 'includes/core/class-functions.php';
		require_once ISIGESTSYNCAPI_PLUGIN_DIR . 'includes/common/class-pushover-hooks.php';
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

		// Hooks per AJAX
		add_action('wp_ajax_isigestsyncapi_toggle_export_status', [
			$this,
			'handleToggleExportStatus',
		]);

		// Aggiungiamo le Hook di Pushover
		if (ConfigHelper::getPushoverEnabled()) {
			PushoverHooks::init();
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
		$this->upgradeDb();
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
			__('Impostazioni', 'isigestsyncapi') .
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
		// Carica gli assets nelle pagine del plugin e nella pagina ordini
		if (
			$hook !== 'toplevel_page_isigestsyncapi-settings' &&
			$hook !== 'isigest-sync_page_isigestsyncapi-settings' &&
			$hook !== 'edit.php' && // Pagina ordini classica
			$hook !== 'woocommerce_page_wc-orders' // Pagina ordini HPOS
		) {
			return;
		}

		wp_enqueue_style(
			'isigestsyncapi-admin',
			ISIGESTSYNCAPI_PLUGIN_URL . 'assets/css/admin.css',
			[],
			ISIGESTSYNCAPI_VERSION,
		);

		wp_enqueue_script(
			'isigestsyncapi-admin',
			ISIGESTSYNCAPI_PLUGIN_URL . 'assets/js/admin.js',
			['jquery'],
			ISIGESTSYNCAPI_VERSION,
			true,
		);

		// Script personalizzato per inizializzare l'editor
		wp_enqueue_script(
			'isigestsyncapi-editor',
			plugin_dir_url(ISIGESTSYNCAPI_PLUGIN_FILE) . 'assets/js/editor.js',
			['jquery', 'wp-theme-plugin-editor', 'code-editor'],
			ISIGESTSYNCAPI_VERSION,
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

	// Gestione Aggiornamento Database
	public function upgradeDb() {
		$helper = new UpgradeHelper();
		$helper->performUpgrade();
	}

	/**
	 * Aggiunge la colonna di esportazione alla lista ordini.
	 *
	 * @param array $columns Colonne esistenti
	 * @return array Colonne modificate
	 */
	public function addOrderExportColumn($columns) {
		$new_columns = [];

		foreach ($columns as $column_name => $column_info) {
			$new_columns[$column_name] = $column_info;

			// Aggiungi la colonna dopo lo status
			if ($column_name === 'order_status') {
				$new_columns['isigestsyncapi_is_exported'] = __('Esp.', 'isigestsyncapi');
			}
		}

		return $new_columns;
	}

	/**
	 * Renderizza il contenuto della colonna di esportazione.
	 *
	 * @param string $column Nome della colonna
	 * @param int|\WC_Order $order ID dell'ordine o oggetto ordine
	 */
	public function renderOrderExportColumn($column, $order) {
		if ($column === 'isigestsyncapi_is_exported') {
			global $wpdb;

			// Ottiene l'ID dell'ordine
			$order_id = is_object($order) ? $order->get_id() : $order;

			// Verifica se l'ordine è stato esportato
			$is_exported = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT is_exported 
            FROM {$wpdb->prefix}isi_api_export_order 
            WHERE order_id = %d",
					$order_id,
				),
			);

			// Aggiungiamo l'ID dell'ordine come attributo data per JavaScript
			echo '<span class="isigest-export-status" data-order-id="' . esc_attr($order_id) . '">';
			if ($is_exported === true) {
				echo '<span class="dashicons dashicons-yes-alt" style="color: #2ea2cc;" title="' .
					esc_attr__('Ordine esportato', 'isigestsyncapi') .
					'"></span>';
			} else {
				echo '<span class="dashicons dashicons-no-alt" style="color: #dc3232;" title="' .
					esc_attr__('Ordine non esportato', 'isigestsyncapi') .
					'"></span>';
			}
		}
	}

	/**
	 * Rende la colonna di esportazione ordinabile.
	 *
	 * @param array $columns Colonne ordinabili
	 * @return array Colonne ordinabili modificate
	 */
	public function makeOrderExportColumnSortable($columns) {
		$columns['isigestsyncapi_is_exported'] = 'isigestsyncapi_is_exported';
		return $columns;
	}

	/**
	 * Gestisce l'ordinamento della colonna per HPOS.
	 *
	 * @param array $query_args Arguments for the query
	 * @return array Modified query arguments
	 */
	public function handleHPOSColumnSorting($query_args) {
		if (isset($_GET['orderby']) && $_GET['orderby'] === 'isigestsyncapi_is_exported') {
			global $wpdb;

			$query_args[
				'join'
			] .= " LEFT JOIN {$wpdb->prefix}isi_api_export_order AS isi_export ON isi_export.order_id = orders.id";
			$query_args['orderby'] = 'isi_export.is_exported';
			$query_args['order'] =
				isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';
		}

		return $query_args;
	}

	/**
	 * Gestisce l'ordinamento della colonna di esportazione.
	 *
	 * @param \WP_Query $query Query object
	 */
	public function handleOrderExportColumnSorting($query) {
		if (!is_admin()) {
			return;
		}

		$orderby = $query->get('orderby');

		if ($orderby === 'isigestsyncapi_is_exported') {
			$query->set('meta_key', '_isigestsyncapi_is_exported');
			$query->set('orderby', 'meta_value');
		}
	}

	/**
	 * Gestisce il toggle dello stato di esportazione di un ordine
	 */
	public function handleToggleExportStatus() {
		// Verifica il nonce
		if (!check_ajax_referer('isigestsyncapi-settings', 'nonce', false)) {
			wp_send_json_error(['message' => 'Nonce non valido']);
			return;
		}

		// Verifica i permessi
		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => 'Permessi insufficienti']);
			return;
		}

		// Ottieni l'ID dell'ordine
		$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
		if (!$order_id) {
			wp_send_json_error(['message' => 'ID ordine non valido']);
			return;
		}

		global $wpdb;

		// Verifica se l'ordine esiste
		$current_status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT is_exported 
				FROM {$wpdb->prefix}isi_api_export_order 
				WHERE order_id = %d",
				$order_id,
			),
		);

		if ($current_status === null) {
			// Se non esiste, inserisci un nuovo record
			$result = $wpdb->insert(
				"{$wpdb->prefix}isi_api_export_order",
				[
					'order_id' => $order_id,
					'is_exported' => 1,
					'exported_at' => current_time('mysql'),
				],
				['%d', '%d', '%s'],
			);

			$new_status = true;
		} else {
			// Se esiste, inverti lo stato
			$new_status = !$current_status;
			$result = $wpdb->update(
				"{$wpdb->prefix}isi_api_export_order",
				[
					'is_exported' => $new_status ? 1 : 0,
					'exported_at' => current_time('mysql'),
				],
				['order_id' => $order_id],
				['%d', '%s'],
				['%d'],
			);
		}

		if ($result === false) {
			wp_send_json_error([
				'message' => 'Si è verificato un problema durante l\'aggiornamento del database',
			]);
			return;
		}

		wp_send_json_success(['is_exported' => $new_status]);
	}
}

// Inizializza il plugin
Plugin::getInstance();
