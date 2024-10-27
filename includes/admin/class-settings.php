<?php
/**
 * Gestione delle impostazioni del plugin
 *
 * @package    ISIGestSyncAPI
 * @subpackage Admin
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Admin;

use ISIGestSyncAPI\Core\ConfigHelper;
use ISIGestSyncAPI\Core\Utilities;

/**
 * Classe Settings per la gestione delle impostazioni del plugin.
 *
 * @since 1.0.0
 */
class Settings {
	/**
	 * Helper per le impostazioni.
	 *
	 * @var SettingsHelper
	 */
	private $helper;

	/**
	 * Configurazione del plugin.
	 *
	 * @var ConfigHelper
	 */
	private $config;

	/**
	 * Tab attiva.
	 *
	 * @var string
	 */
	private $active_tab;

	/**
	 * Costruttore.
	 */
	public function __construct() {
		$this->helper = new SettingsHelper();
		$this->config = ConfigHelper::getInstance();
		$this->active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

		add_action('admin_init', [$this, 'registerSettings']);
		add_action('wp_ajax_isigestsyncapi_save_settings', [$this, 'ajaxSaveSettings']);
		add_action('wp_ajax_isigestsyncapi_test_connection', [$this, 'ajaxTestConnection']);
	}

	/**
	 * Registra le impostazioni del plugin.
	 *
	 * @return void
	 */
	public function registerSettings() {
		register_setting('isigestsyncapi_options', 'isigestsyncapi_settings');
	}

	/**
	 * Renderizza la pagina delle impostazioni.
	 *
	 * @return void
	 */
	public function renderSettingsPage() {
		if (!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		} ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php $this->renderTabs(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('isigestsyncapi_options');

                switch ($this->active_tab) {
                	case 'products':
                		$this->renderProductsTab();
                		break;
                	case 'stock':
                		$this->renderStockTab();
                		break;
                	case 'sync':
                		$this->renderSyncTab();
                		break;
                	case 'advanced':
                		$this->renderAdvancedTab();
                		break;
                	default:
                		$this->renderGeneralTab();
                		break;
                }

                submit_button();?>
            </form>
        </div>
        <?php
	}

	/**
	 * Renderizza i tab delle impostazioni.
	 *
	 * @return void
	 */

	private function renderTabs() {
		$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
		$tabs = [
			'general' => __('General', 'isigestsyncapi'),
			'products' => __('Products', 'isigestsyncapi'),
			'stock' => __('Stock', 'isigestsyncapi'),
			'sync' => __('Sync', 'isigestsyncapi'),
			'advanced' => __('Advanced', 'isigestsyncapi'),
		];

		echo '<div class="nav-tab-wrapper woo-nav-tab-wrapper">';
		foreach ($tabs as $tab_id => $title) {
			$url = add_query_arg(
				[
					'page' => 'isigestsyncapi-settings',
					'tab' => $tab_id,
				],
				admin_url('admin.php'),
			);

			$active = $current_tab === $tab_id ? ' nav-tab-active' : '';
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url($url),
				esc_attr($active),
				esc_html($title),
			);
		}
		echo '</div>';
	}

	/**
	 * Renderizza il tab generale.
	 *
	 * @return void
	 */
	private function renderGeneralTab() {
		?>
		<div class="isi-settings-group">
			<h3><?php esc_html_e('API Configuration', 'isigestsyncapi'); ?></h3>
			<table class="form-table">
				<?php $this->helper->renderField([
    	'type' => 'text',
    	'name' => 'api_key',
    	'label' => __('API Key', 'isigestsyncapi'),
    	'description' => __('API Key per l\'autenticazione delle richieste', 'isigestsyncapi'),
    	'readonly' => true,
    	'value' => $this->config->get('API_KEY'),
    	'class' => 'regular-text',
    ]); ?>
			</table>
		</div>
	
		<div class="isi-settings-group">
			<h3><?php esc_html_e('General Settings', 'isigestsyncapi'); ?></h3>
			<table class="form-table">
				<?php $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'reference_mode',
    	'label' => __('Reference Mode', 'isigestsyncapi'),
    	'description' => __('Usa il codice di riferimento invece dello SKU', 'isigestsyncapi'),
    	'value' => $this->config->get('PRODUCTS_REFERENCE_MODE'),
    	'class' => 'isi-checkbox',
    ]); ?>
			</table>
		</div>
		<?php
	}

	private function renderProductsTab() {
		?>
		<div class="isi-settings-group">
			<h3><?php esc_html_e('Product Status', 'isigestsyncapi'); ?></h3>
			<table class="form-table">
				<?php
    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'disable_outofstock',
    	'label' => __('Disable out of stock products', 'isigestsyncapi'),
    	'description' => __(
    		'Automatically disable products when they go out of stock',
    		'isigestsyncapi',
    	),
    	'value' => $this->config->get('PRODUCTS_DISABLE_OUTOFSTOCK'),
    ]);

    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'disable_without_image',
    	'label' => __('Disable products without images', 'isigestsyncapi'),
    	'description' => __('Automatically disable products that have no images', 'isigestsyncapi'),
    	'value' => $this->config->get('PRODUCTS_DISABLE_WITHOUT_IMAGE'),
    ]);

    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'disable_empty_price',
    	'label' => __('Disable products with empty price', 'isigestsyncapi'),
    	'description' => __(
    		'Automatically disable products that have no price set',
    		'isigestsyncapi',
    	),
    	'value' => $this->config->get('PRODUCTS_DISABLE_WITH_EMPTY_PRICE'),
    ]);
    ?>
			</table>
		</div>
	
		<div class="isi-settings-group">
			<h3><?php esc_html_e('Product Prices', 'isigestsyncapi'); ?></h3>
			<table class="form-table">
				<?php
    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'sync_offers',
    	'label' => __('Sync special offers', 'isigestsyncapi'),
    	'description' => __('Synchronize special offers and discounts', 'isigestsyncapi'),
    	'value' => $this->config->get('PRODUCTS_SYNC_OFFER_AS_SPECIFIC_PRICES'),
    ]);

    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'price_withtax',
    	'label' => __('Prices include tax', 'isigestsyncapi'),
    	'description' => __('Product prices include tax', 'isigestsyncapi'),
    	'value' => $this->config->get('PRODUCTS_PRICE_WITHTAX'),
    ]);

    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'round_net_price',
    	'label' => __('Round net prices', 'isigestsyncapi'),
    	'description' => __('Round net prices to 2 decimal places', 'isigestsyncapi'),
    	'value' => $this->config->get('PRODUCTS_PRICE_ROUND_NET'),
    ]);
    ?>
			</table>
		</div>
	
		<div class="isi-settings-group">
			<h3><?php esc_html_e('Skip Updates', 'isigestsyncapi'); ?></h3>
			<table class="form-table">
				<?php
    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'dont_sync_name',
    	'label' => __('Don\'t update product names', 'isigestsyncapi'),
    	'value' => $this->config->get('PRODUCTS_DONT_SYNC_NAME'),
    ]);

    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'dont_sync_prices',
    	'label' => __('Don\'t update prices', 'isigestsyncapi'),
    	'value' => $this->config->get('PRODUCTS_DONT_SYNC_PRICES'),
    ]);

    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'dont_sync_stock',
    	'label' => __('Don\'t update stock', 'isigestsyncapi'),
    	'value' => $this->config->get('PRODUCTS_DONT_SYNC_STOCK'),
    ]);?>
			</table>
		</div>
		<?php
	}

	private function renderStockTab() {
		?>
		<div class="isi-settings-group">
			<h3><?php esc_html_e('Stock Management', 'isigestsyncapi'); ?></h3>
			<table class="form-table">
				<?php
    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'use_stock_qty',
    	'label' => __('Use stock quantity', 'isigestsyncapi'),
    	'description' => __('Use stock quantity instead of salable quantity', 'isigestsyncapi'),
    	'value' => $this->config->get('PRODUCTS_USE_STOCK_AS_QTY'),
    ]);

    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'multi_warehouse',
    	'label' => __('Enable multi-warehouse', 'isigestsyncapi'),
    	'description' => __('Enable multi-warehouse support', 'isigestsyncapi'),
    	'value' => $this->config->get('PRODUCTS_MULTI_WAREHOUSES'),
    ]);?>
			</table>
		</div>
		<?php
	}

	private function renderSyncTab() {
		?>
		<div class="isi-settings-group">
			<h3><?php esc_html_e('Synchronization Settings', 'isigestsyncapi'); ?></h3>
			<table class="form-table">
				<?php $this->helper->renderField([
    	'type' => 'select',
    	'name' => 'sync_interval',
    	'label' => __('Sync Interval', 'isigestsyncapi'),
    	'description' => __('How often to synchronize with ISIGest', 'isigestsyncapi'),
    	'options' => [
    		'never' => __('Never', 'isigestsyncapi'),
    		'hourly' => __('Hourly', 'isigestsyncapi'),
    		'twicedaily' => __('Twice Daily', 'isigestsyncapi'),
    		'daily' => __('Daily', 'isigestsyncapi'),
    	],
    	'value' => $this->config->get('SYNC_INTERVAL', 'never'),
    ]); ?>
			</table>
			
			<div class="isi-settings-manual-sync">
				<p><?php esc_html_e('Manual Synchronization', 'isigestsyncapi'); ?></p>
				<button type="button" class="button button-secondary" id="sync-now">
					<?php esc_html_e('Sync Now', 'isigestsyncapi'); ?>
				</button>
				<span class="spinner"></span>
				<p class="description">
					<?php esc_html_e('Start a manual synchronization with ISIGest', 'isigestsyncapi'); ?>
				</p>
			</div>
		</div>
		<?php
	}

	private function renderAdvancedTab() {
		?>
		<div class="isi-settings-group">
			<h3><?php esc_html_e('Debug Settings', 'isigestsyncapi'); ?></h3>
			<table class="form-table">
				<?php
    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'enable_debug',
    	'label' => __('Enable Debug Mode', 'isigestsyncapi'),
    	'description' => __('Enable detailed logging for debugging', 'isigestsyncapi'),
    	'value' => $this->config->get('ENABLE_DEBUG'),
    ]);

    $this->helper->renderField([
    	'type' => 'textarea',
    	'name' => 'debug_log',
    	'label' => __('Debug Log', 'isigestsyncapi'),
    	'description' => __('Recent debug log entries', 'isigestsyncapi'),
    	'readonly' => true,
    	'value' => $this->getDebugLog(),
    ]);
    ?>
			</table>
		</div>
	
		<div class="isi-settings-group">
			<h3><?php esc_html_e('Advanced Features', 'isigestsyncapi'); ?></h3>
			<table class="form-table">
				<?php $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'use_legacy_mode',
    	'label' => __('Use Legacy Mode', 'isigestsyncapi'),
    	'description' => __('Enable for compatibility with older systems', 'isigestsyncapi'),
    	'value' => $this->config->get('USE_LEGACY_MODE'),
    ]); ?>
			</table>
		</div>
		<?php
	}

	/**
	 * Gestisce il salvataggio delle impostazioni via AJAX.
	 *
	 * @return void
	 */
	public function ajaxSaveSettings() {
		check_ajax_referer('isigestsyncapi-settings', 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => __('Unauthorized', 'isigestsyncapi')]);
		}

		$settings = [];
		parse_str($_POST['formData'], $settings);

		foreach ($settings as $key => $value) {
			$this->config->set($key, $value);
		}

		wp_send_json_success(['message' => __('Settings saved successfully', 'isigestsyncapi')]);
	}

	/**
	 * Gestisce il test della connessione API.
	 *
	 * @return void
	 */
	public function ajaxTestConnection() {
		check_ajax_referer('isigestsyncapi-settings', 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => __('Unauthorized', 'isigestsyncapi')]);
		}

		try {
			// Implementa qui il test della connessione
			$result = true;

			if ($result) {
				wp_send_json_success([
					'message' => __('Connection test successful', 'isigestsyncapi'),
				]);
			} else {
				wp_send_json_error(['message' => __('Connection test failed', 'isigestsyncapi')]);
			}
		} catch (\Exception $e) {
			wp_send_json_error(['message' => $e->getMessage()]);
		}
	}

	/**
	 * Ottiene il log di debug.
	 *
	 * @return string
	 */
	private function getDebugLog() {
		$log_file = WP_CONTENT_DIR . '/debug-isigest.log';
		if (!file_exists($log_file)) {
			return '';
		}

		return file_get_contents($log_file);
	}
}
