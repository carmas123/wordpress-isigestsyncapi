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

/**
 * Classe SettingsHelper per la gestione del rendering delle impostazioni.
 *
 * @since 1.0.0
 */
class SettingsHelper {
	/**
	 * Configurazione del plugin.
	 *
	 * @var ConfigHelper
	 */
	private $config;

	/**
	 * Costruttore.
	 */
	public function __construct() {
		$this->config = ConfigHelper::getInstance();
	}

	/**
	 * Renderizza un campo del form.
	 *
	 * @param array $args Argomenti del campo.
	 * @return void
	 */
	public function renderField($args) {
		$defaults = [
			'type' => 'text',
			'name' => '',
			'label' => '',
			'description' => '',
			'placeholder' => '',
			'class' => '',
			'value' => '',
			'options' => [],
			'readonly' => false,
			'disabled' => false,
			'required' => false,
			'data' => [],
			'multiple' => false,
		];

		$args = wp_parse_args($args, $defaults);
		$name = 'isigestsyncapi_settings[' . $args['name'] . ']';
		$id = 'isigestsyncapi_' . $args['name'];

		// Calcoliamo il calore in base al nome
		$args['value'] = $this->config->get($args['name'], $args['value']);

		switch ($args['type']) {
			case 'text':
			case 'password':
			case 'number':
			case 'email':
			case 'url':
				$this->renderInputField($name, $id, $args);
				break;

			case 'textarea':
				$this->renderTextarea($name, $id, $args);
				break;

			case 'checkbox':
				$this->renderCheckbox($name, $id, $args);
				break;

			case 'radio':
				$this->renderRadio($name, $id, $args);
				break;

			case 'select':
				$this->renderSelect($name, $id, $args);
				break;

			case 'button':
				$this->renderButton($name, $id, $args);
				break;

			case 'color':
				$this->renderColorPicker($name, $id, $args);
				break;

			case 'file':
				$this->renderFileUpload($name, $id, $args);
				break;
		}
	}

	/**
	 * Renderizza un campo input.
	 *
	 * @param string $name Nome del campo.
	 * @param string $id   ID del campo.
	 * @param array  $args Argomenti del campo.
	 * @return void
	 */
	private function renderInputField($name, $id, $args) {
		?>
		<input 
			type="<?php echo esc_attr($args['type']); ?>"
			id="<?php echo esc_attr($id); ?>"
			name="<?php echo esc_attr($name); ?>"
			value="<?php echo esc_attr($args['value']); ?>"
			class="regular-text <?php echo esc_attr($args['class']); ?>"
			<?php echo $args['placeholder'] ? 'placeholder="' . esc_attr($args['placeholder']) . '"' : ''; ?>
			<?php echo $args['readonly'] ? 'readonly' : ''; ?>
			<?php echo $args['disabled'] ? 'disabled' : ''; ?>
			<?php echo $args['required'] ? 'required' : ''; ?>
			<?php $this->renderDataAttributes($args['data']); ?>
		>
		<?php if ($args['description']) {
  	echo '<p class="description">' . esc_html($args['description']) . '</p>';
  }
	}

	/**
	 * Renderizza un campo textarea.
	 *
	 * @param string $name Nome del campo.
	 * @param string $id   ID del campo.
	 * @param array  $args Argomenti del campo.
	 * @return void
	 */
	private function renderTextarea($name, $id, $args) {
		?>
		<textarea
			id="<?php echo esc_attr($id); ?>"
			name="<?php echo esc_attr($name); ?>"
			class="large-text code <?php echo esc_attr($args['class']); ?>"
			rows="10"
			<?php echo $args['readonly'] ? 'readonly' : ''; ?>
			<?php echo $args['disabled'] ? 'disabled' : ''; ?>
			<?php echo $args['required'] ? 'required' : ''; ?>
			<?php $this->renderDataAttributes($args['data']); ?>
		><?php echo esc_textarea($args['value']); ?></textarea>
		<?php if ($args['description']) {
  	echo '<p class="description">' . esc_html($args['description']) . '</p>';
  }
	}

	/**
	 * Renderizza un campo checkbox.
	 *
	 * @param string $name Nome del campo.
	 * @param string $id   ID del campo.
	 * @param array  $args Argomenti del campo.
	 * @return void
	 */
	private function renderCheckbox($name, $id, $args) {
		?>
    <label for="<?php echo esc_attr($id); ?>">
        <input type="hidden" name="<?php echo esc_attr($name); ?>" value="0">
        <input 
            type="checkbox"
            id="<?php echo esc_attr($id); ?>"
            name="<?php echo esc_attr($name); ?>"
            value="1"
            <?php checked($args['value'], true); ?>
            class="<?php echo esc_attr($args['class']); ?>"
            <?php echo $args['disabled'] ? 'disabled' : ''; ?>
            <?php $this->renderDataAttributes($args['data']); ?>
        >
        <?php echo esc_html($args['label']); ?>
    </label>
    <?php if ($args['description']) {
    	echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }
	}

	/**
	 * Renderizza un campo radio.
	 *
	 * @param string $name Nome del campo.
	 * @param string $id   ID del campo.
	 * @param array  $args Argomenti del campo.
	 * @return void
	 */
	private function renderRadio($name, $id, $args) {
		?>
	<fieldset>
		<?php foreach ($args['options'] as $key => $label): ?>
			<label for="<?php echo esc_attr($id . '_' . $key); ?>">
				<input 
					type="radio"
					id="<?php echo esc_attr($id . '_' . $key); ?>"
					name="<?php echo esc_attr($name); ?>"
					value="<?php echo esc_attr($key); ?>"
					<?php checked($args['value'], $key); ?>
					class="<?php echo esc_attr($args['class']); ?>"
					<?php echo $args['disabled'] ? 'disabled' : ''; ?>
					<?php echo $args['required'] ? 'required' : ''; ?>
					<?php $this->renderDataAttributes($args['data']); ?>
				>
				<?php echo esc_html($label); ?>
			</label><br>
		<?php endforeach; ?>
	</fieldset>
	<?php if ($args['description']) {
 	echo '<p class="description">' . esc_html($args['description']) . '</p>';
 }
	}

	/**
	 * Renderizza un campo select.
	 *
	 * @param string $name Nome del campo.
	 * @param string $id   ID del campo.
	 * @param array  $args Argomenti del campo.
	 * @return void
	 */
	private function renderSelect($name, $id, $args) {
		?>
		<select
			id="<?php echo esc_attr($id); ?>"
			name="<?php
   echo esc_attr($name);
   echo $args['multiple'] ? '[]' : '';
   ?>"
			class="<?php echo esc_attr($args['class']); ?>"
			<?php echo $args['multiple'] ? 'multiple' : ''; ?>
			<?php echo $args['disabled'] ? 'disabled' : ''; ?>
			<?php echo $args['required'] ? 'required' : ''; ?>
			<?php $this->renderDataAttributes($args['data']); ?>
		>
			<?php foreach ($args['options'] as $key => $label): ?>
				<option 
					value="<?php echo esc_attr($key); ?>"
					<?php selected($args['value'], $key); ?>
				>
					<?php echo esc_html($label); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ($args['description']) {
  	echo '<p class="description">' . esc_html($args['description']) . '</p>';
  }
	}

	/**
	 * Renderizza un bottone.
	 *
	 * @param string $name Nome del campo.
	 * @param string $id   ID del campo.
	 * @param array  $args Argomenti del campo.
	 * @return void
	 */
	private function renderButton($name, $id, $args) {
		?>
		<button
			type="button"
			id="<?php echo esc_attr($id); ?>"
			name="<?php echo esc_attr($name); ?>"
			class="button <?php echo esc_attr($args['class']); ?>"
			<?php echo $args['disabled'] ? 'disabled' : ''; ?>
			<?php $this->renderDataAttributes($args['data']); ?>
		>
			<?php echo esc_html($args['label']); ?>
		</button>
		<?php if ($args['description']) {
  	echo '<p class="description">' . esc_html($args['description']) . '</p>';
  }
	}

	/**
	 * Renderizza un color picker.
	 *
	 * @param string $name Nome del campo.
	 * @param string $id   ID del campo.
	 * @param array  $args Argomenti del campo.
	 * @return void
	 */
	private function renderColorPicker($name, $id, $args) {
		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('wp-color-picker');
		?>
		<input 
			type="text"
			id="<?php echo esc_attr($id); ?>"
			name="<?php echo esc_attr($name); ?>"
			value="<?php echo esc_attr($args['value']); ?>"
			class="color-picker <?php echo esc_attr($args['class']); ?>"
			<?php echo $args['readonly'] ? 'readonly' : ''; ?>
			<?php echo $args['disabled'] ? 'disabled' : ''; ?>
			<?php $this->renderDataAttributes($args['data']); ?>
		>
		<script>
			jQuery(document).ready(function($) {
				$('#<?php echo esc_js($id); ?>').wpColorPicker();
			});
		</script>
		<?php if ($args['description']) {
  	echo '<p class="description">' . esc_html($args['description']) . '</p>';
  }
	}

	/**
	 * Renderizza un campo per l'upload di file.
	 *
	 * @param string $name Nome del campo.
	 * @param string $id   ID del campo.
	 * @param array  $args Argomenti del campo.
	 * @return void
	 */
	private function renderFileUpload($name, $id, $args) {
		wp_enqueue_media(); ?>
		<div class="file-upload-field">
			<input 
				type="text"
				id="<?php echo esc_attr($id); ?>"
				name="<?php echo esc_attr($name); ?>"
				value="<?php echo esc_attr($args['value']); ?>"
				class="regular-text <?php echo esc_attr($args['class']); ?>"
				<?php echo $args['readonly'] ? 'readonly' : ''; ?>
				<?php echo $args['disabled'] ? 'disabled' : ''; ?>
				<?php $this->renderDataAttributes($args['data']); ?>
			>
			<button type="button" class="button file-upload-button">
				<?php esc_html_e('Upload File', 'isigestsyncapi'); ?>
			</button>
		</div>
		<script>
			jQuery(document).ready(function($) {
				$('.file-upload-button').on('click', function(e) {
					e.preventDefault();
					var button = $(this);
					var field = button.siblings('input');
					
					var frame = wp.media({
						title: '<?php esc_html_e('Select File', 'isigestsyncapi'); ?>',
						button: {
							text: '<?php esc_html_e('Use File', 'isigestsyncapi'); ?>'
						},
						multiple: false
					});

					frame.on('select', function() {
						var attachment = frame.state().get('selection').first().toJSON();
						field.val(attachment.url);
					});

					frame.open();
				});
			});
		</script>
		<?php if ($args['description']) {
  	echo '<p class="description">' . esc_html($args['description']) . '</p>';
  }
	}

	/**
	 * Renderizza gli attributi data.
	 *
	 * @param array $data Attributi data.
	 * @return void
	 */
	private function renderDataAttributes($data) {
		foreach ($data as $key => $value) {
			echo ' data-' . esc_attr($key) . '="' . esc_attr($value) . '"';
		}
	}
}
