<?php
/**
 * Plugin Name: ISIGest Sync API
 * Description: Plugin per la sincronizzazione dei prodotti tramite API
 * Version: 1.0.47
 * Author: ISIGest S.r.l.
 * Author URI: https://www.isigest.net
 *
 * @package    ISIGestSyncAPI
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 * @license    GPL-2.0-or-later
 */

namespace ISIGestSyncAPI;

use ISIGestSyncAPI\Core\ConfigHelper;
use ISIGestSyncAPI\Core\UpgradeHelper;

// Se questo file viene chiamato direttamente, termina.
if (!defined('ABSPATH')) {
	exit();
}

// Definizioni costanti
define('ISIGESTSYNCAPI_VERSION', '1.0.47');
define('ISIGESTSYNCAPI_PLUGIN_FILE', __FILE__);
define('ISIGESTSYNCAPI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ISIGESTSYNCAPI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ISIGESTSYNCAPI_LOGS_DIR', ISIGESTSYNCAPI_PLUGIN_DIR . 'logs');

// Carica l'autoloader
require_once ISIGESTSYNCAPI_PLUGIN_DIR . '/includes/autoload.php';

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
		require_once ISIGESTSYNCAPI_PLUGIN_DIR . '/includes/core/class-exceptions.php';
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
			ISIGESTSYNCAPI_VERSION,
		);

		wp_enqueue_script(
			'isigestsyncapi-admin',
			plugin_dir_url(ISIGESTSYNCAPI_PLUGIN_FILE) . 'assets/js/admin.js',
			['jquery'],
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
}

// Inizializza il plugin
Plugin::getInstance();
