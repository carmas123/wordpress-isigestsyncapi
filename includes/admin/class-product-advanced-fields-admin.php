<?php
/**
 * Gestione dei campi avanzati nell'interfaccia admin
 */
class ProductAdvancedFieldsAdmin {
	/**
	 * Prefisso per i campi avanzati
	 */
	private const ADVANCED_FIELD_PREFIX = 'af_';

	/**
	 * Inizializza i ganci per l'admin
	 */
	public function __construct() {
		// Aggiunge la tab "Caratteristiche" nei dati prodotto
		add_filter('woocommerce_product_data_tabs', [$this, 'addFeatureTab']);

		// Aggiunge i campi nella nuova tab
		add_action('woocommerce_product_data_panels', [$this, 'addFeatureFields']);

		// Salva i dati quando il prodotto viene salvato
		add_action('woocommerce_process_product_meta', [$this, 'saveFeatures']);

		// Carica CSS e JavaScript
		add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
	}

	/**
	 * Carica CSS e JavaScript per l'admin
	 */
	public function enqueueAssets($hook) {
		// Carica solo nella pagina di modifica prodotto
		if ('post.php' !== $hook && 'post-new.php' !== $hook) {
			return;
		}

		$screen = get_current_screen();
		if ('product' !== $screen->post_type) {
			return;
		}

		// Carica CSS
		wp_enqueue_style(
			'isigest-admin-css',
			ISIGEST_SYNC_API_PLUGIN_URL . 'assets/css/admin-products.css',
			[],
			ISIGEST_SYNC_API_VERSION,
		);

		// Carica JavaScript
		wp_enqueue_script(
			'isigest-admin-product-js',
			ISIGEST_SYNC_API_PLUGIN_URL . 'assets/js/admin-products.js',
			['jquery'],
			ISIGEST_SYNC_API_VERSION,
			true,
		);
	}

	public function addFeatureTab($tabs) {
		$tabs['features'] = [
			'label' => __('Caratteristiche ISIGest', 'woocommerce'), // Modificato il titolo
			'target' => 'product_features',
			'class' => ['hide_if_grouped'],
			'priority' => 70,
		];
		return $tabs;
	}

	public function addFeatureFields() {
		global $post;

		$features = $this->getProductFeatures($post->ID);
		?>
        <div id="product_features" class="panel woocommerce_options_panel">
            <div class="options_group">
                <div class="form-field isi-features-wrapper">
                    <?php if (empty($features)): ?>
                    <p class="isi-no-features">
                        <?php _e(
                        	'Nessuna caratteristica sincronizzata da ISIGest.',
                        	'woocommerce',
                        ); ?>
                    </p>
                    <?php else: ?>
                    <div class="isi-sync-notice">
                        <p><?php _e(
                        	'Questi valori vengono sincronizzati automaticamente da ISIGest e non possono essere modificati manualmente.',
                        	'woocommerce',
                        ); ?></p>
                    </div>
                    <table class="isi-features-table">
                        <thead>
                            <tr>
                                <th class="isi-feature-name"><?php _e(
                                	'Nome',
                                	'woocommerce',
                                ); ?></th>
                                <th class="isi-feature-value"><?php _e(
                                	'Valore',
                                	'woocommerce',
                                ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($features as $feature) {
                            	echo '<tr>';
                            	$this->renderFeatureRow($feature);
                            	echo '</tr>';
                            } ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
	}

	private function renderFeatureRow($feature) {
		$name = $feature['name'];
		$value = $feature['value'];
		?>
        <td class="isi-feature-name">
            <span class="isi-feature-label"><?php echo esc_html($name); ?></span>
        </td>
        <td class="isi-feature-value">
            <span class="isi-feature-value-text"><?php echo esc_html($value); ?></span>
        </td>
        <?php
	}

	/**
	 * Recupera tutte le caratteristiche di un prodotto
	 */
	private function getProductFeatures($product_id) {
		global $wpdb;

		$features = [];

		$meta_data = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT meta_key, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE post_id = %d 
            AND meta_key LIKE %s",
				$product_id,
				self::ADVANCED_FIELD_PREFIX . '%',
			),
		);

		foreach ($meta_data as $meta) {
			$name = substr($meta->meta_key, strlen(self::ADVANCED_FIELD_PREFIX));
			$features[] = [
				'name' => ucwords(str_replace('-', ' ', $name)),
				'value' => $meta->meta_value,
			];
		}

		return $features;
	}

	/**
	 * Elimina tutte le caratteristiche di un prodotto
	 */
	private function deleteAllFeatures($product_id) {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"
            DELETE FROM {$wpdb->postmeta} 
            WHERE post_id = %d 
            AND meta_key LIKE %s",
				$product_id,
				self::ADVANCED_FIELD_PREFIX . '%',
			),
		);
	}
}

// Inizializza la classe
add_action('admin_init', function () {
	new ProductAdvancedFieldsAdmin();
});
