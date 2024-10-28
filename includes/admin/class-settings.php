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

		add_action('wp_ajax_isigestsyncapi_save_settings', [$this, 'ajaxSaveSettings']);
	}

	/**
	 * Mostra le notifiche dopo il salvataggio delle impostazioni.
	 */
	public function showSettingsNotices() {
		if (isset($_GET['settings-updated']) && $_GET['settings-updated']) { ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Impostazioni salvate con successo.', 'isigestsyncapi'); ?></p>
        </div>
        <?php }
	}

	/**
	 * Sanitizza le impostazioni prima del salvataggio.
	 *
	 * @param array $input Le impostazioni da sanitizzare.
	 * @return array Le impostazioni sanitizzate.
	 */
	public function sanitizeSettings($input) {
		$settings = get_option('isigestsyncapi_settings', []);

		if (is_array($input)) {
			foreach ($input as $key => $value) {
				if (is_bool($value) || $value === '1' || $value === '0') {
					$settings[$key] = (bool) $value;
				} else {
					$settings[$key] = sanitize_text_field($value);
				}
			}
		}

		// Aggiorna la configurazione in memoria
		foreach ($settings as $key => $value) {
			$this->config->set($key, $value);
		}

		return $settings;
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
			'products' => __('Prodotti', 'isigestsyncapi'),
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
    	'class' => 'regular-text',
    ]); ?>
			</table>
		</div>
	
		<div class="isi-settings-group">
			<h3><?php esc_html_e('General Settings', 'isigestsyncapi'); ?></h3>
			<table class="form-table">
				<?php $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'products_reference_mode',
    	'label' => __('Reference Mode', 'isigestsyncapi'),
    	'description' => __('Usa il codice di riferimento invece dello SKU', 'isigestsyncapi'),
    	'class' => 'isi-checkbox',
    ]); ?>
			</table>
		</div>
		<?php
	}

	private function renderProductsTab() {
		?>
		<div class="isi-settings-group">
			<h3><?php esc_html_e('Disattivazione Prodotti', 'isigestsyncapi'); ?></h3>
			<table class="form-table">
				<?php
    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'products_disable_outofstock',
    	'label' => __('Non disponibili', 'isigestsyncapi'),
    	'description' => __(
    		'Disattiva automaticamente i prodotti non più disponibili',
    		'isigestsyncapi',
    	),
    ]);

    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'products_disable_without_image',
    	'label' => __('Senza immagini', 'isigestsyncapi'),
    	'description' => __('Disattiva i prodotti senza immagini', 'isigestsyncapi'),
    ]);

    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'products_disable_empty_price',
    	'label' => __('Senza prezzo', 'isigestsyncapi'),
    	'description' => __('Disattiva i prodotti senza prezzo', 'isigestsyncapi'),
    ]);
    ?>
			</table>
		</div>
	
		<div class="isi-settings-group">
			<h3><?php esc_html_e('Prezzi', 'isigestsyncapi'); ?></h3>
			<table class="form-table">
				<?php
    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'products_price_withtax',
    	'label' => __('Prezzi IVA inclusa', 'isigestsyncapi'),
    	'description' => __('Aggiorna il prezzo dei prodotti includendo l\'IVA', 'isigestsyncapi'),
    ]);

    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'products_round_net_price',
    	'label' => __('Arrotonda prezzi netti', 'isigestsyncapi'),
    	'description' => __('Arrotonda i prezzi IVA escusa a 2 decimali', 'isigestsyncapi'),
    	'value' => $this->config->get('products_round_net_price'),
    ]);
    ?>
			</table>
		</div>

		
		<div class="isi-settings-group">
			<h3><?php esc_html_e('Inventario', 'isigestsyncapi'); ?></h3>
			<table class="form-table">
				<?php $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'products_use_stock_qty',
    	'label' => __('Importa la giacenza', 'isigestsyncapi'),
    	'description' => __(
    		'Valorizza la quantità di inventario con l\'esistenza invece della disponibilità',
    		'isigestsyncapi',
    	),
    ]); ?>
			</table>
		</div>
	
		<div class="isi-settings-group">
			<h3><?php esc_html_e('Blocca Aggiornamento', 'isigestsyncapi'); ?></h3>
			<table class="form-table">
				<?php
    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'products_dont_sync_name',
    	'label' => __('Nome prodotto', 'isigestsyncapi'),
    ]);

    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'products_dont_sync_ean',
    	'label' => __('Codice a barre', 'isigestsyncapi'),
    ]);

    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'products_dont_sync_prices',
    	'label' => __('Prezzo', 'isigestsyncapi'),
    	'value' => $this->config->get('products_dont_sync_prices'),
    ]);

    $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'products_dont_sync_stocks',
    	'label' => __('Giacenze', 'isigestsyncapi'),
    	'value' => $this->config->get('products_dont_sync_prices'),
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
				<?php $this->helper->renderField([
    	'type' => 'checkbox',
    	'name' => 'products_multi_warehouse',
    	'label' => __('Enable multi-warehouse', 'isigestsyncapi'),
    	'description' => __('Enable multi-warehouse support', 'isigestsyncapi'),
    	'value' => $this->config->get('products_multi_warehouse'),
    ]); ?>
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
		// Verifica il nonce
		check_ajax_referer('isigestsyncapi-settings', 'nonce');

		// Verifica i permessi
		if (!current_user_can('manage_options')) {
			wp_send_json_error([
				'message' => __('Non autorizzato', 'isigestsyncapi'),
			]);
		}

		// Recupera e analizza i dati del form
		$form_data = [];
		parse_str($_POST['formData'], $form_data);

		// Estrai le impostazioni dal form serializzato
		if (isset($form_data['isigestsyncapi_settings'])) {
			$input = $form_data['isigestsyncapi_settings'];

			// Sanitizza e salva le impostazioni
			$sanitized_settings = $this->sanitizeSettings($input);
			update_option('isigestsyncapi_settings', $sanitized_settings);

			wp_send_json_success([
				'message' => __('Impostazioni salvate con successo', 'isigestsyncapi'),
				'settings' => $sanitized_settings, // Per debug
			]);
		} else {
			wp_send_json_error([
				'message' => __('Nessuna impostazione da salvare', 'isigestsyncapi'),
			]);
		}
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
