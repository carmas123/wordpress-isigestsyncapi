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
	 * Costruisce un array di configurazione per un campo di testo.
	 *
	 * @param string $key       La chiave univoca del campo.
	 * @param string $label     L'etichetta del campo da visualizzare.
	 * @param string $tab       Il tab in cui il campo deve apparire.
	 * @param string $section   La sezione all'interno del tab (opzionale).
	 * @param string $info      Informazioni aggiuntive o descrizione del campo (opzionale).
	 * @param bool   $read_only Se il campo deve essere di sola lettura (opzionale, default false).
	 * @param string $type      Il tipo di input HTML (opzionale, default 'text').
	 *
	 * @return array Un array associativo che rappresenta la configurazione del campo.
	 */
	private function buildField(
		$key,
		$label,
		$tab,
		$section = '',
		$info = '',
		$read_only = false,
		$type = 'text'
	) {
		return [
			'type' => $type,
			'label' => __($label, 'isigestsyncapi'),
			'name' => $key,
			'readonly' => $read_only,
			'description' => empty($info) ? null : __($info, 'isigestsyncapi'),
			'tab' => $tab,
			'section' => $section,
		];
	}
	/**
	 * Costruisce un array di configurazione per un campo textarea.
	 *
	 * @param string $key       La chiave univoca del campo.
	 * @param string $label     L'etichetta del campo da visualizzare.
	 * @param string $tab       Il tab in cui il campo deve apparire.
	 * @param string $section   La sezione all'interno del tab (opzionale).
	 * @param string $info      Informazioni aggiuntive o descrizione del campo (opzionale).
	 * @param bool   $read_only Se il campo deve essere di sola lettura (opzionale, default false).
	 *
	 * @return array Un array associativo che rappresenta la configurazione del campo.
	 */
	private function buildTextarea(
		$key,
		$label,
		$tab,
		$section = '',
		$info = '',
		$read_only = false
	) {
		return $this->buildField($key, $label, $tab, $section, $info, $read_only, 'textarea');
	}

	private function buildCheckbox(
		$key,
		$label,
		$tab,
		$section = '',
		$info = '',
		$read_only = false
	) {
		return $this->buildField($key, $label, $tab, $section, $info, $read_only, 'checkbox');
	}

	/**
	 * Costruisce un array di configurazione per un campo select.
	 *
	 * @param string $key       La chiave univoca del campo.
	 * @param string $label     L'etichetta del campo da visualizzare.
	 * @param string $tab       Il tab in cui il campo deve apparire.
	 * @param string $section   La sezione all'interno del tab (opzionale).
	 * @param string $info      Informazioni aggiuntive o descrizione del campo (opzionale).
	 * @param array  $options   Array delle opzioni del select [value => label].
	 * @param bool   $read_only Se il campo deve essere di sola lettura (opzionale, default false).
	 *
	 * @return array Un array associativo che rappresenta la configurazione del campo.
	 */
	private function buildSelect(
		$key,
		$label,
		$tab,
		$section = '',
		$info = '',
		$options = [],
		$read_only = false
	) {
		$field = $this->buildField($key, $label, $tab, $section, $info, $read_only, 'select');
		$field['options'] = $options;
		return $field;
	}

	/**
	 * Builds a select input field for HTML import/export options.
	 *
	 * @param string $label    The label for the field.
	 * @param string $key      The key for the field.
	 * @param string $tab      The tab where the field should be displayed.
	 * @param string $section  The section where the field should be displayed.
	 * @param string $info     Additional information about the field.
	 *
	 * @return array An associative array representing the select input field.
	 */
	private function buildSelectHtmlFields($label, $key, $tab, $section = '', $info = '') {
		$options = [
			'0' => __("Importa così com'è", 'isigestsyncapi'),
			'1' => __('Pulisci i tag html', 'isigestsyncapi'),
			'2' => __('Non importare', 'isigestsyncapi'),
		];

		return $this->buildSelect($key, $label, $tab, $section, $info, $options);
	}

	/**
	 * Builds a select input field for product name configuration.
	 *
	 * @param string $label    The label for the select input field.
	 * @param string $key      The key for the configuration.
	 * @param string $tab      The tab where the field should be displayed.
	 * @param string $section  The section where the field should be displayed.
	 * @param string $info     Additional information about the field.
	 *
	 * @return array The configuration array for the select input field.
	 */
	private function buildSelectProductName($label, $key, $tab, $section = '', $info = '') {
		$options = [
			'0' => __('Nome', 'isigestsyncapi'),
			'1' => __('Tags', 'isigestsyncapi'),
			'2' => __('Descrizione breve', 'isigestsyncapi'),
			'3' => __('Non importare'),
		];

		return $this->buildSelect($key, $label, $tab, $section, $info, $options);
	}
	/**
	 * Restituisce la struttura completa delle impostazioni del plugin
	 *
	 * @return array La struttura delle impostazioni
	 */
	public function getFormFields() {
		return [
			'form' => [
				'legend' => [
					'title' => __('Impostazioni ISIGest Sync API', 'isigestsyncapi'),
					'icon' => 'icon-cogs',
				],
				'tabs' => [
					'general' => __('Generale', 'isigestsyncapi'),
					'categories' => __('Categorie', 'isigestsyncapi'),
					'products' => __('Prodotti', 'isigestsyncapi'),
					'products_dont_sync' => __('Prodotti (Blocca aggiornamento)', 'isigestsyncapi'),
					'sizesandcolors' => __('Taglie e Colori', 'isigestsyncapi'),
					'advanced' => __('Avanzate', 'isigestsyncapi'),
				],
				'input' => [
					// General Settings
					$this->buildField(
						'api_key',
						'Chiave API',
						'general',
						'Autenticazione',
						'Chiave di autenticazione da utilizzare in ISIGest per consentile la sincronizzazione dei dati',
						true,
					),

					// Categorie
					$this->buildCheckbox(
						'categories_disable_empty',
						'Disattiva automaticamente',
						'categories',
						'Disattivazione',
						'Disattiva automaticamente le categorie vuote',
					),

					// Prodotti
					$this->buildSelectProductName(
						'Nome prodotto',
						'products_name',
						'products',
						'Dati del prodotto',
					),
					$this->buildSelectHtmlFields(
						'Descrizione',
						'products_description',
						'products',
						'Dati del prodotto',
					),
					$this->buildSelectHtmlFields(
						'Descrizione breve',
						'products_short_description',
						'products',
						'Dati del prodotto',
					),
					$this->buildCheckbox(
						'products_reference_mode',
						'Modalità Reference',
						'products',
						'Modalità',
						'Usa il codice di riferimento invece dello SKU',
					),
					$this->buildCheckbox(
						'products_disable_outofstock',
						'Non disponibili',
						'products',
						'Disattivazione',
						'Disattiva automaticamente i prodotti non più disponibili',
					),
					$this->buildCheckbox(
						'products_disable_without_image',
						'Senza immagini',
						'products',
						'Disattivazione',
						'Disattiva i prodotti senza immagini',
					),
					$this->buildCheckbox(
						'products_disable_empty_price',
						'Senza prezzo',
						'products',
						'Disattivazione',
						'Disattiva i prodotti senza prezzo',
					),
					$this->buildCheckbox(
						'products_price_withtax',
						'Prezzi IVA inclusa',
						'products',
						'Prezzi',
						'Aggiorna il prezzo dei prodotti includendo l\'IVA',
					),
					$this->buildCheckbox(
						'products_round_net_price',
						'Arrotonda prezzi netti',
						'products',
						'Prezzi',
						'Arrotonda i prezzi IVA escusa a 2 decimali',
					),
					$this->buildCheckbox(
						'products_use_stock_qty',
						'Importa la giacenza',
						'products',
						'Inventario',
						'Valorizza la quantità di inventario con l\'esistenza invece della disponibilità',
					),
					$this->buildCheckbox(
						'products_multi_warehouse',
						'Magazzini multipli',
						'products',
						'Inventario',
						'Abilita la gestione multi-magazzino',
					),
					$this->buildField(
						'products_ean_key',
						'Chiave Codice a Barre',
						'products',
						'Altri campi',
						'Indica il campo slug per il codice a barre (Default: barcode)',
					),
					$this->buildField(
						'products_brand_key',
						'Chiave Marca',
						'products',
						'Altri campi',
						'Indica il campo slug per le marche (Default: marca)',
					),

					// Products Don't Sync Settings
					$this->buildCheckbox(
						'products_dont_sync_categories',
						'Categorie',
						'products_dont_sync',
						'Seleziona gli elementi da non aggiornare',
					),
					$this->buildCheckbox(
						'products_dont_sync_ean',
						'Codice a barre',
						'products_dont_sync',
						'Seleziona gli elementi da non aggiornare',
					),
					$this->buildCheckbox(
						'products_dont_sync_brand',
						'Marca',
						'products_dont_sync',
						'Seleziona gli elementi da non aggiornare',
					),
					$this->buildCheckbox(
						'products_dont_sync_prices',
						'Prezzo',
						'products_dont_sync',
						'Seleziona gli elementi da non aggiornare',
					),
					$this->buildCheckbox(
						'products_dont_sync_stocks',
						'Giacenze',
						'products_dont_sync',
						'Seleziona gli elementi da non aggiornare',
					),
					$this->buildCheckbox(
						'products_dont_sync_dimension_and_weight',
						'Dimensione e Peso',
						'products_dont_sync',
						'Seleziona gli elementi da non aggiornare',
					),

					// Taglie&Colori
					$this->buildField(
						'sizeandcolor_size_key',
						'Chiave Taglie',
						'sizesandcolors',
						'Definizione',
						'Indica il campo slug per le taglie (Default: taglia)',
					),
					$this->buildField(
						'sizeandcolor_color_key',
						'Chiave Colori',
						'sizesandcolors',
						'Definizione',
						'Indica il campo slug per i colori (Default: colore)',
					),

					// Advanced Settings
					$this->buildCheckbox(
						'enable_debug',
						'Modalità debug',
						'advanced',
						'Debug',
						'Abilita il log dettagliato per il debug',
					),
					$this->buildTextarea(
						'debug_log',
						'Log di debug',
						'advanced',
						'Debug',
						'Log delle operazioni recenti',
						true,
					),
				],
				'submit' => [
					'title' => __('Salva', 'isigestsyncapi'),
				],
			],
		];
	}

	/**
	 * Renderizza la pagina delle impostazioni
	 */
	public function renderSettingsPage() {
		if (!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}

		$form_fields = $this->getFormFields();
		$helper = new SettingsHelper();
		$helper->renderForm($form_fields);
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

			// Azzeriamo la cache delle opzioni
			ConfigHelper::clearCacheStatic();

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
}
