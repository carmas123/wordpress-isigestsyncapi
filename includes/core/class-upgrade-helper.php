<?php
/**
 *
 * @package    ISIGestSyncAPI
 * @subpackage Core
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Core;

use ISIGestSyncAPI\Common\BaseConfig;

class UpgradeHelper extends BaseConfig {
	private const LOCK_KEY = 'isigestsyncapi_upgrade_lock';

	public function __construct() {
		parent::__construct();
	}

	/**
	 * Esegue l'upgrade del plugin con meccanismo di lock
	 *
	 * @return bool True se l'upgrade è stato eseguito, False se era già in esecuzione
	 */
	public function performUpgrade() {
		// Leggiamo la versione corrente del database
		$current = ConfigHelper::getDbVersion();
		// Impostiamo la versione attuale del plugin
		$new = ISIGESTSYNCAPI_VERSION;

		if ($current != $new) {
			// Verifica se c'è già un lock attivo
			if ($this->isUpgradeRunning()) {
				Utilities::log('ISIGestSyncAPI: Upgrade già in esecuzione');
				return false;
			}

			try {
				// Imposta il lock
				update_option(self::LOCK_KEY, time(), false);

				$upgrade_path = ISIGESTSYNCAPI_PLUGIN_DIR . 'upgrade/';
				$files = scandir($upgrade_path);

				$versions = [];

				foreach ($files as $file) {
					if (strpos($file, 'upgrade-') === 0) {
						$version_file = str_replace(['upgrade-', '.php'], '', $file);
						if (version_compare($version_file, $current, '>')) {
							$versions[] = $version_file;
						}
					}
				}

				// Ordiniamo le versioni usando version_compare
				usort($versions, 'version_compare');

				// Ora creiamo ed eseguiamo i metodi nell'ordine corretto
				foreach ($versions as $version) {
					// @unlink($upgrade_path . 'upgrade-' . $version . '.php');
					require_once $upgrade_path . 'upgrade-' . $version . '.php';
					$method = 'isigestsyncapi_upgrade_' . str_replace('.', '_', $version);
					if (function_exists($method)) {
						call_user_func($method);
						Utilities::logWarn(
							'Upgrade: Eseguito con successo verso la versione ' . $version,
						);
					} else {
						Utilities::logError(
							'Upgrde: Non è possibile trovare il metodo ' .
								$method .
								'per l\'upgrade ' .
								$version,
						);
					}
				}

				// Aggiorniamo ora all'ultima versione, quella del plugin
				ConfigHelper::setDbVersionCurrent();

				return true;
			} catch (\Exception $e) {
				Utilities::logError(
					'ISIGestSyncAPI: Errore critico durante l\'upgrade: ' . $e->getMessage(),
				);
				return false;
			} finally {
				// Rimuovi sempre il lock alla fine, anche in caso di errori
				delete_option(self::LOCK_KEY);
			}
		}

		return true; // Non c'era bisogno di upgrade
	}

	/**
	 * Verifica se c'è un upgrade in corso
	 *
	 * @return bool True se un upgrade è in corso, False altrimenti
	 */
	public function isUpgradeRunning() {
		$lock_timeout = 300; // 5 minuti

		$lock_data = get_option(self::LOCK_KEY);
		if (!$lock_data) {
			return false;
		}

		$lock_time = (int) $lock_data;
		return time() - $lock_time < $lock_timeout;
	}

	/**
	 * Forza la rimozione del lock dell'upgrade
	 * Da usare con cautela, solo se si è sicuri che non ci siano upgrade in corso
	 */
	public function forceClearUpgradeLock() {
		delete_option(self::LOCK_KEY);
	}
}
