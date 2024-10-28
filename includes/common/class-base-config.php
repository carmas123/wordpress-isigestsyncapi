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

	public function __construct() {
		parent::__construct();
		$this->config = ConfigHelper::getInstance();
	}
}
