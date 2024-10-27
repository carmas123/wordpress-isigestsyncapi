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
		$this->active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general'; ?>
		<div class="wrap isi-settings-wrap">
			<h1><?php echo esc_html__('ISIGest Sync Settings', 'isigestsyncapi'); ?></h1>
			
			<?php $this->renderTabs(); ?>
			
			<form method="post" action="options.php" id="isi-settings-form">
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
		$tabs = [
			'general' => __('General', 'isigestsyncapi'),
			'products' => __('Products', 'isigestsyncapi'),
			'stock' => __('Stock', 'isigestsyncapi'),
			'sync' => __('Sync', 'isigestsyncapi'),
			'advanced' => __('Advanced', 'isigestsyncapi'),
		];

		echo '<h2 class="nav-tab-wrapper woo-nav-tab-wrapper">';
		foreach ($tabs as $tab => $name) {
			$class = 'nav-tab';
			if ($tab === $this->active_tab) {
				$class .= ' nav-tab-active';
			}
			echo sprintf(
				'<a href="?page=isigestsyncapi-settings&tab=%s" class="%s">%s</a>',
				esc_attr($tab),
				esc_attr($class),
				esc_html($name),
			);
		}
		echo '</h2>';
	}

	/**
	 * Renderizza il tab generale.
	 *
	 * @return void
	 */
	private function renderGeneralTab() {
		?>
		<table class="form-table">
			<?php
   $this->helper->renderField([
   	'type' => 'text',
   	'name' => 'api_key',
   	'label' => __('API Key', 'isigestsyncapi'),
   	'description' => __('API Key per l\'autenticazione delle richieste', 'isigestsyncapi'),
   	'readonly' => true,
   	'value' => $this->config->get('API_KEY'),
   ]);

   $this->helper->renderField([
   	'type' => 'button',
   	'name' => 'generate_api_key',
   	'label' => __('Generate New Key', 'isigestsyncapi'),
   	'class' => 'button button-secondary',
   	'data' => [
   		'action' => 'generate_key',
   	],
   ]);

   $this->helper->renderField([
   	'type' => 'checkbox',
   	'name' => 'reference_mode',
   	'label' => __('Reference Mode', 'isigestsyncapi'),
   	'description' => __('Usa il codice di riferimento invece dello SKU', 'isigestsyncapi'),
   	'value' => $this->config->get('PRODUCTS_REFERENCE_MODE'),
   ]);?>
		</table>
		<?php
	}

	/**
	 * Renderizza il tab dei prodotti.
	 *
	 * @return void
	 */
	private function renderProductsTab() {
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e('Product Status', 'isigestsyncapi'); ?></th>
				<td>
					<?php
     $this->helper->renderField([
     	'type' => 'checkbox',
     	'name' => 'disable_outofstock',
     	'label' => __('Disable out of stock products', 'isigestsyncapi'),
     	'value' => $this->config->get('PRODUCTS_DISABLE_OUTOFSTOCK'),
     ]);

     $this->helper->renderField([
     	'type' => 'checkbox',
     	'name' => 'disable_without_image',
     	'label' => __('Disable products without images', 'isigestsyncapi'),
     	'value' => $this->config->get('PRODUCTS_DISABLE_WITHOUT_IMAGE'),
     ]);

     $this->helper->renderField([
     	'type' => 'checkbox',
     	'name' => 'disable_empty_price',
     	'label' => __('Disable products with empty price', 'isigestsyncapi'),
     	'value' => $this->config->get('PRODUCTS_DISABLE_WITH_EMPTY_PRICE'),
     ]);
     ?>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e('Product Prices', 'isigestsyncapi'); ?></th>
				<td>
					<?php
     $this->helper->renderField([
     	'type' => 'checkbox',
     	'name' => 'sync_offers',
     	'label' => __('Sync special offers', 'isigestsyncapi'),
     	'value' => $this->config->get('PRODUCTS_SYNC_OFFER_AS_SPECIFIC_PRICES'),
     ]);

     $this->helper->renderField([
     	'type' => 'checkbox',
     	'name' => 'price_withtax',
     	'label' => __('Prices include tax', 'isigestsyncapi'),
     	'value' => $this->config->get('PRODUCTS_PRICE_WITHTAX'),
     ]);

     $this->helper->renderField([
     	'type' => 'checkbox',
     	'name' => 'round_net_price',
     	'label' => __('Round net prices', 'isigestsyncapi'),
     	'value' => $this->config->get('PRODUCTS_PRICE_ROUND_NET'),
     ]);
     ?>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e('Skip Updates', 'isigestsyncapi'); ?></th>
				<td>
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
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Renderizza il tab dello stock.
	 *
	 * @return void
	 */
	private function renderStockTab() {
		?>
		<table class="form-table">
			<?php
   $this->helper->renderField([
   	'type' => 'checkbox',
   	'name' => 'use_stock_qty',
   	'label' => __('Use stock quantity', 'isigestsyncapi'),
   	'description' => __('Usa la quantità di stock invece della disponibilità', 'isigestsyncapi'),
   	'value' => $this->config->get('PRODUCTS_USE_STOCK_AS_QTY'),
   ]);

   $this->helper->renderField([
   	'type' => 'checkbox',
   	'name' => 'multi_warehouse',
   	'label' => __('Enable multi-warehouse', 'isigestsyncapi'),
   	'description' => __('Abilita la gestione multi-magazzino', 'isigestsyncapi'),
   	'value' => $this->config->get('PRODUCTS_MULTI_WAREHOUSES'),
   ]);?>
		</table>
		<?php
	}

	/**
	 * Renderizza il tab della sincronizzazione.
	 *
	 * @return void
	 */
	private function renderSyncTab() {
		?>
		<table class="form-table">
			<?php $this->helper->renderField([
   	'type' => 'select',
   	'name' => 'sync_interval',
   	'label' => __('Sync Interval', 'isigestsyncapi'),
   	'description' => __('Intervallo di sincronizzazione automatica', 'isigestsyncapi'),
   	'options' => [
   		'never' => __('Never', 'isigestsyncapi'),
   		'hourly' => __('Hourly', 'isigestsyncapi'),
   		'twicedaily' => __('Twice Daily', 'isigestsyncapi'),
   		'daily' => __('Daily', 'isigestsyncapi'),
   	],
   	'value' => $this->config->get('SYNC_INTERVAL', 'never'),
   ]); ?>

			<tr>
				<th scope="row"><?php esc_html_e('Manual Sync', 'isigestsyncapi'); ?></th>
				<td>
					<button type="button" class="button" id="sync-now">
						<?php esc_html_e('Sync Now', 'isigestsyncapi'); ?>
					</button>
					<p class="description">
						<?php esc_html_e('Avvia una sincronizzazione manuale', 'isigestsyncapi'); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Renderizza il tab avanzato.
	 *
	 * @return void
	 */
	private function renderAdvancedTab() {
		?>
		<table class="form-table">
			<?php
   $this->helper->renderField([
   	'type' => 'textarea',
   	'name' => 'debug_log',
   	'label' => __('Debug Log', 'isigestsyncapi'),
   	'description' => __('Log delle operazioni di debug', 'isigestsyncapi'),
   	'readonly' => true,
   	'value' => $this->getDebugLog(),
   ]);

   $this->helper->renderField([
   	'type' => 'checkbox',
   	'name' => 'enable_debug',
   	'label' => __('Enable Debug Mode', 'isigestsyncapi'),
   	'description' => __('Attiva la modalità debug', 'isigestsyncapi'),
   	'value' => $this->config->get('ENABLE_DEBUG'),
   ]);?>
		</table>
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
