<?php
/**
 * Helper per la gestione delle impostazioni
 *
 * @package    ISIGestSyncAPI
 * @subpackage Admin
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Admin;

use ISIGestSyncAPI\Core\ConfigHelper;
use ISIGestSyncAPI\Core\Utilities;

class SettingsHelper {
	private $config;
	private $form_structure;

	public function __construct() {
		$this->config = ConfigHelper::getInstance();
	}

	/**
	 * Renderizza l'intero form delle impostazioni
	 *
	 * @param array $form_structure La struttura completa del form
	 */
	public function renderForm($form_structure) {
		$fs = $form_structure['form'];
		$this->form_structure = $fs;

		$active_tab = isset($_GET['tab'])
			? sanitize_key($_GET['tab'])
			: array_key_first($fs['tabs']);
		?>
        <div class="wrap">
            <h1><?php echo esc_html($fs['legend']['title']); ?></h1>

            <?php $this->renderTabs($active_tab); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('isigestsyncapi_options');
                $this->renderTabContent($active_tab);
                submit_button($fs['submit']['title']);?>
            </form>
        </div>
        <?php
	}

	/**
	 * Renderizza i tabs
	 */
	private function renderTabs($active_tab) {
		echo '<h2 class="nav-tab-wrapper woo-nav-tab-wrapper">';
		foreach ($this->form_structure['tabs'] as $tab_id => $tab_label) {
			$url = add_query_arg(
				[
					'page' => 'isigestsyncapi-settings',
					'tab' => $tab_id,
				],
				admin_url('admin.php'),
			);

			$active = $active_tab === $tab_id ? ' nav-tab-active' : '';
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url($url),
				esc_attr($active),
				esc_html($tab_label),
			);
		}
		echo '</h2>';
	}

	/**
	 * Renderizza il contenuto del tab attivo
	 */
	private function renderTabContent($active_tab) {
		// Raggruppa i campi per sezione all'interno del tab attivo
		$sections = [];
		foreach ($this->form_structure['input'] as $field) {
			if ($field && $field['tab'] === $active_tab) {
				$section = $field['section'] ?? 'default';
				$sections[$section][] = $field;
			}
		}

		// Renderizza ogni sezione
		foreach ($sections as $section_name => $fields) {
			echo '<div class="isi-settings-group">';
			if ($section_name !== 'default') {
				echo '<h3>' . esc_html($section_name) . '</h3>';
			}

			echo '<table class="form-table" role="presentation">';
			foreach ($fields as $field) {
				$this->renderField($field);
			}
			echo '</table>';
			echo '</div>';
		}
	}

	/**
	 * Renderizza un singolo campo
	 */
	private function renderField($field) {
		$field = wp_parse_args($field, [
			'type' => 'text',
			'name' => '',
			'label' => '',
			'description' => '',
			'class' => '',
			'value' => isset($field['name']) ? $this->config->get($field['name'], '') : '',
			'options' => [],
			'readonly' => false,
			'disabled' => false,
		]);

		switch ($field['type']) {
			case 'html':
				$this->renderHtml($field);
				break;
			case 'checkbox':
				$this->renderCheckboxField($field);
				break;
			case 'select':
				$this->renderSelectField($field);
				break;
			case 'textarea':
				$this->renderTextareaField($field);
				break;
			case 'custom_functions':
				$this->renderCustomFunctionsFileField($field);
				break;
			default:
				$this->renderDefaultField($field);
				break;
		}
	}

	/**
	 * Renderizza un campo checkbox
	 */
	private function renderCheckboxField($field) {
		?>
        <tr>
            <th scope="row" colspan="2">
                <label>
                    <input type="hidden" name="isigestsyncapi_settings[<?php echo esc_attr(
                    	$field['name'],
                    ); ?>]" value="0">
                    <input
                        type="checkbox"
                        name="isigestsyncapi_settings[<?php echo esc_attr($field['name']); ?>]"
                        value="1"
                        <?php checked($field['value'], true); ?>
                        <?php echo $field['readonly'] ? 'readonly' : ''; ?>
                        <?php echo $field['disabled'] ? 'disabled' : ''; ?>
                    >
                    <?php echo esc_html($field['label']); ?>
                </label>
                <?php if (!empty($field['description'])): ?>
                    <p class="description"><?php echo esc_html($field['description']); ?></p>
                <?php endif; ?>
            </th>
        </tr>
        <?php
	}

	/**
	 * Renderizza un campo select
	 */
	private function renderSelectField($field) {
		?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(
                	$field['name'],
                ); ?>"><?php echo esc_html($field['label']); ?></label>
            </th>
            <td>
                <select
                    name="isigestsyncapi_settings[<?php echo esc_attr($field['name']); ?>]"
                    id="<?php echo esc_attr($field['name']); ?>"
                    <?php echo $field['readonly'] ? 'readonly' : ''; ?>
                    <?php echo $field['disabled'] ? 'disabled' : ''; ?>
                >
                    <?php foreach ($field['options'] as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected(
	$field['value'],
	$value,
); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($field['description'])): ?>
                    <p class="description"><?php echo esc_html($field['description']); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
	}

	private function renderTextareaField($field) {
		$field = wp_parse_args($field, [
			'buttons' => [],
		]); ?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(
                	$field['name'],
                ); ?>"><?php echo esc_html($field['label']); ?></label>
            </th>
            <td>
                <div class="isi-settings-textarea-container">
                    <textarea
                        name="isigestsyncapi_settings[<?php echo esc_attr($field['name']); ?>]"
                        id="<?php echo esc_attr($field['name']); ?>"
                        class="large-text code"
                        rows="10"
                        <?php echo $field['readonly'] ? 'readonly' : ''; ?>
                        <?php echo $field['disabled'] ? 'disabled' : ''; ?>
                    ><?php echo esc_textarea($field['value']); ?></textarea>

                    <?php if (!empty($field['buttons'])): ?>
                        <div class="isi-settings-button-container" style="margin-top: 10px;">
                            <?php foreach ($field['buttons'] as $button): ?>
                                <button type="button"
                                    class="<?php echo esc_attr($button['class']); ?>"
                                    <?php if (isset($button['data-action'])): ?>
                                        data-action="<?php echo esc_attr(
                                        	$button['data-action'],
                                        ); ?>"
                                    <?php endif; ?>
									<?php if (isset($button['id'])): ?>
                                        id="<?php echo esc_attr($button['id']); ?>"
                                    <?php endif; ?>
                                >
                                    <?php echo esc_html($button['label']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($field['description'])): ?>
                        <p class="description"><?php echo esc_html($field['description']); ?></p>
                    <?php endif; ?>
                </div>
            </td>
        </tr>

        <?php
	}

	/**
	 * Renderizza un campo di input standard
	 */
	private function renderDefaultField($field) {
		?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(
                	$field['name'],
                ); ?>"><?php echo esc_html($field['label']); ?></label>
            </th>
            <td>
                <input
                    type="<?php echo esc_attr($field['type']); ?>"
                    name="isigestsyncapi_settings[<?php echo esc_attr($field['name']); ?>]"
                    id="<?php echo esc_attr($field['name']); ?>"
                    value="<?php echo esc_attr($field['value']); ?>"
                    class="regular-text"
                    <?php echo $field['readonly'] ? 'readonly' : ''; ?>
                    <?php echo $field['disabled'] ? 'disabled' : ''; ?>
                >
                <?php if (!empty($field['description'])): ?>
                    <p class="description"><?php echo esc_html($field['description']); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
	}

	/**
	 * Renderizza un campo personalizzato HTML
	 */
	private function renderHtml($field) {
		if (isset($field['callback']) && is_callable($field['callback'])) {
			call_user_func($field['callback'], $field);
		}
	}

	private function renderCustomFunctionsFileField($field) {
		?>
        <tr>
            <td>
                <div class="isi-settings-textarea-container">
                    <textarea
                        name="isigestsyncapi_settings[<?php echo esc_attr($field['name']); ?>]"
                        id="isi_custom_functions_editor"
                        class="large-text code"
                        rows="20"
                    ><?php echo esc_textarea(
                    	Utilities::ifBlank($field['value'], "<?php\n"),
                    ); ?></textarea>

                    <?php if (!empty($field['description'])): ?>
                        <p class="description"><?php echo esc_html($field['description']); ?></p>
                    <?php endif; ?>
                </div>
            </td>
        </tr>

        <style>
            .CodeMirror {
                height: 500px;
                border: 1px solid #ddd;
            }
        </style>
        <?php
	}

	public function renderPluginVersion() {
		?>
        <tr>
            <th scope="row">
                <label><?php echo esc_html('Versione Plugin'); ?></label>
            </th>
            <td>
                <span id="isi_plugin_version"><?php echo esc_html(ISIGESTSYNCAPI_VERSION); ?></span>
            </td>
        </tr>
        <?php
	}

	public function renderCustomersSetAllAsExported() {
		?>
            <button type="button" class="button" id="isi_customers_set_all_as_exported_button">
                <?php echo esc_html('Imposta tutti i clienti come esportati'); ?>
            </button>
        <?php
	}

	public function renderOrdersSetAllAsExported() {
		?>
            <button type="button" class="button" id="isi_orders_set_all_as_exported_button">
                <?php echo esc_html('Imposta tutti gli ordini come esportati'); ?>
            </button>
        <?php
	}

	public function renderProductsClearExportedHistory() {
		?>
            <button type="button" class="button" id="isi_products_clear_exported_history_button">
                <?php echo esc_html('Elimina lo storico dei prodotti'); ?>
            </button>
        <?php
	}

	// Questo metoto serve a mostrare un avviso quando la tabella degli ordini non è abilitata in WooCommerce
	public function renderWCCustomOrderTableIsNotEnabled() {
		?>
        <tr>
            <td>
                <div class="notice notice-error inline" style="margin: 0; padding: 8px 12px;">
                    <p>
                        <strong><?php echo esc_html('Attenzione:'); ?></strong>
                        <?php echo sprintf(
                        	/* translators: %s: link alle impostazioni */
                        	esc_html__(
                        		'È necessario abilitare le Custom Order Tables di WooCommerce nelle %sImpostazioni Avanzate%s per utilizzare correttamente questo plugin, altrimenti la ricezione degli ordini non funzionerà.',
                        	),
                        	'<a href="' .
                        		esc_url(
                        			admin_url(
                        				'admin.php?page=wc-settings&tab=advanced&section=features',
                        			),
                        		) .
                        		'">',
                        	'</a>',
                        ); ?>
                    </p>
                </div>
            </td>
        </tr>
        <?php
	}
}
