<?php
/**
 *
 * @package    ISIGestSyncAPI
 * @subpackage common
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Common;

use ISIGestSyncAPI\Core\ConfigHelper;

class BaseConfig extends Base {
	/**
	 * Configurazione del plugin.
	 *
	 * @var ConfigHelper
	 */
	protected $config;

	/**
	 * Modalità di riferimento per la sincronizzazione.
	 *
	 * @var boolean
	 */
	protected $products_reference_mode;

	public function __construct() {
		parent::__construct();
		$this->config = ConfigHelper::getInstance();
		$this->products_reference_mode = $this->config->get('products_reference_mode', false);
	}
}
