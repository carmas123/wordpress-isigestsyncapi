<?php
/**
 * Gestione delle immagini dei prodotti
 *
 * @package    ISIGestSyncAPI
 * @subpackage Services
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Services;

use ISIGestSyncAPI\Core\Utilities;

/**
 * Classe PushoverService per la gestione dell'invio delle notifiche tramite Pushover.
 *
 * @since 1.0.0
 */
class PushoverService extends BaseService {
	/**
	 * Indica se il servizio Pushover è abilitato.
	 *
	 * @var bool
	 */
	private $enabled = false;

	/**
	 * Token di autenticazione per l'API Pushover.
	 *
	 * @var string
	 */
	private $token;

	/**
	 * Chiave utente per l'API Pushover.
	 *
	 * @var string
	 */
	private $user_key;

	/**
	 * Nome del dispositivo di destinazione.
	 *
	 * @var string
	 */
	private $device;

	private const URL = 'https://api.pushover.net/1/messages.json';

	/**
	 * Costruttore.
	 */
	public function __construct() {
		parent::__construct();

		// Inizializziamo i parametri di configurazione
		$this->enabled = $this->config->get('pushover_enabled', false);
		$this->token = $this->config->get('pushover_token');
		$this->user_key = $this->config->get('pushover_userkey');
		$this->device = $this->config->get('pushover_device');
	}

	/**
	 * Verifica se il servizio Pushover è valido e abilitato.
	 *
	 * Questo metodo privato controlla se il servizio Pushover è stato abilitato
	 * e se sono stati configurati correttamente il token e la chiave utente.
	 *
	 * @return bool Restituisce true se il servizio è abilitato e configurato correttamente,
	 *              false altrimenti.
	 */
	private function isValidAndEnabled(): bool {
		return $this->enabled && !empty($this->token) && !empty($this->user_key);
	}

	/**
	 * Prepara i dati della richiesta per l'invio di una notifica tramite Pushover.
	 *
	 * Questo metodo privato costruisce un array associativo contenente tutti i parametri
	 * necessari per inviare una notifica tramite l'API di Pushover.
	 *
	 * @param string $title   Il titolo della notifica.
	 * @param string $message Il corpo del messaggio della notifica.
	 *
	 * @return array Un array associativo contenente i dati della richiesta:
	 *               - 'token':   Il token di autenticazione per l'API Pushover.
	 *               - 'user':    La chiave utente per l'API Pushover.
	 *               - 'device':  Il nome del dispositivo di destinazione (se specificato).
	 *               - 'title':   Il titolo della notifica.
	 *               - 'message': Il corpo del messaggio della notifica.
	 */
	private function prepareRequest(string $title, string $message) {
		return [
			'token' => $this->token,
			'user' => $this->user_key,
			'device' => $this->device,
			'title' => $title,
			'message' => $message,
		];
	}

	public function sendNotification(string $title, string $message) {
		if (!$this->isValidAndEnabled()) {
			return false;
		}

		// Prepariamo i dati
		$data = $this->prepareRequest($title, $message);

		$ch = curl_init(self::URL);
		try {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_FAILONERROR, 0);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			return curl_exec($ch) !== false;
		} catch (\Throwable $t) {
			// NO ERRORS
			return false;
		} finally {
			curl_close($ch);
		}
	}

	public static function sendNotificationNewCustomer($customer_id) {
		if (!$customer_id) {
			return false;
		}

		// Carichiamo il cliente
		$customer = new \WC_Customer($customer_id);

		// Verifichiamo se il cliente è valido
		if (!$customer) {
			return false;
		}

		// Prepariamo il titolo e il messaggio
		$title = 'Un nuovo cliente si è registrato';
		$message =
			$customer->get_first_name() .
			' ' .
			$customer->get_last_name() .
			' si è appena registrato con questo indirizzo e-mail: ' .
			$customer->get_email();

		// Inviamo la notifica
		$n = new PushoverService();
		return $n->sendNotification($title, $message);
	}

	public static function sendNotificationNewOrder($order_id) {
		if (!$order_id) {
			return false;
		}

		// Carichiamo l'ordine
		$order = wc_get_order($order_id);

		// Verifichiamo se l'ordine è valido
		if (!$order) {
			return false;
		}

		$firstname = Utilities::ifBlank(
			$order->get_billing_first_name(),
			$order->get_shipping_first_name(),
		);

		$lastname = Utilities::ifBlank(
			$order->get_billing_last_name(),
			$order->get_shipping_last_name(),
		);

		// Prepariamo il titolo e il messaggio
		$title = 'Congratulazioni! Hai ricevuto un nuovo ordine';
		$formatted_total = html_entity_decode(Utilities::formatPrice($order->get_total()));

		$message =
			'Un ordine da ' .
			$firstname .
			' ' .
			$lastname .
			' per un totale di ' .
			$formatted_total .
			' è stato ricevuto';

		// Inviamo la notifica
		$n = new PushoverService();
		return $n->sendNotification($title, $message);
	}
}
