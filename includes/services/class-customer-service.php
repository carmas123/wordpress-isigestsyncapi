<?php
/**
 * Gestione dei clienti
 *
 * @package    ISIGestSyncAPI
 * @subpackage Services
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Services;

use ISIGestSyncAPI\Core\Utilities;
use ISIGestSyncAPI\Core\ISIGestSyncApiBadRequestException;
use ISIGestSyncAPI\Core\ISIGestSyncApiNotFoundException;

/**
 * Classe CustomerService per la gestione dei clienti.
 *
 * @since 1.0.0
 */
class CustomerService extends BaseService {
	/**
	 * Costruttore.
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Recupera i dati di un cliente.
	 *
	 * @param integer $customer_id ID del cliente.
	 * @return array|null
	 * @throws ISIGestSyncApiNotFoundException Se il cliente non viene trovato.
	 */
	public static function get($customer_id) {
		global $wpdb;
		try {
			$customer = new \WC_Customer($customer_id);
			if (!$customer->get_id()) {
				throw new ISIGestSyncApiNotFoundException('Cliente non trovato');
			}

			if (!is_email($customer->get_email())) {
				throw new ISIGestSyncApiNotFoundException('E-Mail non valida');
			}

			$addresses = [
				self::billingAddressToData($customer),
				self::shippingAddressToData($customer),
			];

			// Puliamo l'array degli indirizzi
			$addresses = array_filter(
				[self::billingAddressToData($customer), self::shippingAddressToData($customer)],
				function ($value) {
					return $value !== null && (!is_array($value) || !empty($value));
				},
			);

			// Verifichiamo che ci sia almeno un indirizzo
			if (empty($addresses)) {
				throw new ISIGestSyncApiBadRequestException('Nessun indirizzo valido disponibile');
			}

			return [
				'id' => $customer->get_id(),
				'email' => $customer->get_email(),
				'addresses' => $addresses,
			];
		} catch (\Exception $e) {
			self::setAsReceived($customer_id, $e);
			return null;
		}
	}

	private function getToReceiveQuery(): string {
		global $wpdb;

		// Inizializzazione della tabella di lookup
		$customer_lookup_table = $wpdb->prefix . 'wc_customer_lookup';
		$order_stats_table = $wpdb->prefix . 'wc_order_stats';
		$users_table = $wpdb->prefix . 'users';

		return "SELECT DISTINCT cl.customer_id AS id
            FROM {$customer_lookup_table} cl
            LEFT JOIN {$wpdb->prefix}isi_api_export_customer e ON cl.customer_id = e.customer_id
    		LEFT JOIN {$order_stats_table} os ON cl.customer_id = os.customer_id
        	INNER JOIN {$users_table} u ON cl.user_id = u.ID
        	WHERE cl.user_id IS NOT NULL
				AND cl.user_id > 0
				AND u.user_status = 0
				AND (
					e.customer_id IS NULL 
					OR e.is_exported = 0 
				)
				AND (
					/* Utente ha ordini validi */
					os.status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
					OR 
					/* OPPURE utente è valido (verifica custom) */
					EXISTS (
						SELECT 1 FROM {$wpdb->usermeta} um 
						WHERE um.user_id = cl.user_id 
						AND um.meta_key = 'account_status'
						AND um.meta_value = 'active'
					)
				)
        	ORDER BY 1 ASC
		";
	}

	/**
	 * Recupera tutti i clienti da esportare.
	 *
	 * @return array
	 */
	public function getToReceive() {
		global $wpdb;

		// Query per recuperare i clienti modificati o non ancora esportati usando la tabella di lookup
		$customers = $wpdb->get_results($this->getToReceiveQuery(), ARRAY_A);

		$result = [];
		foreach ($customers as $customer) {
			try {
				$customer_data = $this->get($customer['id']);
				if (is_array($customer_data) && !empty($customer_data)) {
					$result[] = $customer_data;
				}
			} catch (\Exception $e) {
				Utilities::logError($e->getMessage());
			}
		}

		return $result;
	}

	/**
	 * Imposta un cliente come esportato.
	 *
	 * @param integer $customer_id ID del cliente.
	 * @param string|\Exception|null $error
	 * @return boolean
	 */
	public static function setAsReceived($customer_id, $error = null) {
		global $wpdb;

		$error_message = null;
		if ($error instanceof \Exception) {
			$error_message = $error->getMessage();
		} elseif (is_string($error)) {
			$error_message = $error;
		}

		Utilities::logDebug("Impostazione cliente come esportato: {$customer_id}");

		// Usa la data di modifica dell'ordine
		$modified_date = current_time('mysql');

		$result = $wpdb->replace(
			$wpdb->prefix . 'isi_api_export_customer',
			[
				'customer_id' => (int) $customer_id,
				'is_exported' => 1,
				'exported_at' => $modified_date,
				'has_error' => $error_message ? 1 : 0,
				'message' => $error_message,
			],
			['%d', '%d', '%s', '%d', '%s'],
		);
		Utilities::logDbResult($result);

		return $result !== false;
	}

	/**
	 * Recupera l'indirizzo di fatturazione.
	 *
	 * @param \WC_Customer $customer
	 * @return array
	 */
	public static function billingAddressToData($customer) {
		$address = [
			'firstname' => Utilities::ifBlank(
				$customer->get_billing_first_name(),
				$customer->get_first_name(),
			),
			'lastname' => Utilities::ifBlank(
				$customer->get_billing_last_name(),
				$customer->get_last_name(),
			),
			'company' => $customer->get_billing_company(),
			'address1' => $customer->get_billing_address_1(),
			'address2' =>
				$customer->get_billing_address_2() !== $customer->get_billing_address_1()
					? $customer->get_billing_address_2()
					: '',
			'postcode' => $customer->get_billing_postcode(),
			'city' => $customer->get_billing_city(),
			'state' => $customer->get_billing_state(),
			'country' => $customer->get_billing_country(),
		];

		// Crea un ID numerico
		$address['id'] = abs(crc32(implode('|', array_filter($address))));

		// Aggiungiamo il Telefono
		$address['phone'] = $customer->get_billing_phone();

		return $address;
	}

	/**
	 * Recupera l'indirizzo di spedizione.
	 *
	 * @param \WC_Customer $customer
	 * @return array
	 */
	public static function shippingAddressToData($customer) {
		$address = [
			'firstname' => Utilities::ifBlank(
				$customer->get_shipping_first_name(),
				$customer->get_first_name(),
			),
			'lastname' => Utilities::ifBlank(
				$customer->get_shipping_last_name(),
				$customer->get_last_name(),
			),
			'company' => $customer->get_shipping_company(),
			'address1' => $customer->get_shipping_address_1(),
			'address2' =>
				$customer->get_shipping_address_2() !== $customer->get_shipping_address_1()
					? $customer->get_shipping_address_2()
					: '',
			'postcode' => $customer->get_shipping_postcode(),
			'city' => $customer->get_shipping_city(),
			'state' => $customer->get_shipping_state(),
			'country' => $customer->get_shipping_country(),
		];

		// Crea un ID numerico
		$address['id'] = abs(crc32(implode('|', array_filter($address))));

		// Aggiungiamo il Telefono
		$address['phone'] = $customer->get_shipping_phone();

		return $address;
	}

	public function setAsReceivedAll(): int {
		global $wpdb;

		$cnt = 0;

		// Leggiamo i clienti da esportare
		$items = $wpdb->get_results($this->getToReceiveQuery(), ARRAY_A);

		foreach ($items as $item_id) {
			try {
				$this->setAsReceived($item_id);
				$cnt++;
			} catch (\Exception $e) {
				Utilities::logError($e->getMessage());
			}
		}

		return $cnt;
	}
}
