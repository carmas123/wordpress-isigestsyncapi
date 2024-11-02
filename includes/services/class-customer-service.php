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

use ISIGestSyncAPI\Core\ConfigHelper;
use ISIGestSyncAPI\Core\DbHelper;
use ISIGestSyncAPI\Core\Utilities;
use ISIGestSyncAPI\Core\ISIGestSyncApiException;
use ISIGestSyncAPI\Core\ISIGestSyncApiBadRequestException;
use ISIGestSyncAPI\Core\ISIGestSyncApiNotFoundException;

/**
 * Classe CustomerService per la gestione dei clienti.
 *
 * @since 1.0.0
 */
class CustomerService extends BaseService {
	/**
	 * @var string Tabella di lookup dei clienti WooCommerce
	 */
	private $customer_lookup_table;

	/**
	 * Costruttore.
	 */
	public function __construct() {
		parent::__construct();

		global $wpdb;

		// Inizializzazione della tabella di lookup
		$this->customer_lookup_table = $wpdb->prefix . 'wc_customer_lookup';
	}

	/**
	 * Recupera i dati di un cliente.
	 *
	 * @param integer $customer_id ID del cliente.
	 * @return array
	 * @throws ISIGestSyncApiNotFoundException Se il cliente non viene trovato.
	 */
	public function get($customer_id) {
		$customer = new \WC_Customer($customer_id);
		if (!$customer->get_id()) {
			throw new ISIGestSyncApiNotFoundException('Cliente non trovato');
		}

		return [
			'id' => $customer->get_id(),
			'email' => $customer->get_email(),
			'firstname' => $customer->get_first_name(),
			'lastname' => $customer->get_last_name(),
			'company' => $customer->get_billing_company(),
			'date_created' => Utilities::dateToISO(
				$customer->get_date_created()->date('Y-m-d H:i:s'),
			),
			'date_last_order' => $this->getLastOrderDate($customer_id),
			'billing_address' => $this->getBillingAddress($customer),
			'shipping_address' => $this->getShippingAddress($customer),
		];
	}

	/**
	 * Recupera tutti i clienti da esportare.
	 *
	 * @return array
	 */
	public function getCustomersToReceive() {
		global $wpdb;

		// Query per recuperare i clienti modificati o non ancora esportati usando la tabella di lookup
		$customers = $wpdb->get_results("
            SELECT DISTINCT cl.customer_id 
            FROM {$this->customer_lookup_table} cl
            LEFT JOIN {$wpdb->prefix}isi_api_export_customer e ON cl.customer_id = e.customer_id
            WHERE (
                e.customer_id IS NULL 
                OR e.is_exported = 0 
                OR cl.date_last_active <> e.exported_at
            )
        ");

		$result = [];
		foreach ($customers as $customer) {
			try {
				$customer_data = $this->get($customer->customer_id);
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
	 * @return boolean
	 */
	public function setAsReceived($customer_id) {
		global $wpdb;

		// Recupera la data dell'ultimo ordine dalla tabella di lookup
		$last_active = $wpdb->get_var(
			$wpdb->prepare(
				"
            SELECT date_last_active 
            FROM {$this->customer_lookup_table} 
            WHERE customer_id = %d
        ",
				$customer_id,
			),
		);

		if (!$last_active) {
			throw new ISIGestSyncApiNotFoundException('Cliente non trovato');
		}

		$result = $wpdb->replace(
			$wpdb->prefix . 'isi_api_export_customer',
			[
				'customer_id' => (int) $customer_id,
				'exported' => 1,
				'exported_at' => $last_active,
			],
			['%d', '%d', '%s'],
		);
		Utilities::logDbResult($result);

		return $result !== false;
	}

	/**
	 * Recupera la data dell'ultimo ordine del cliente.
	 *
	 * @param integer $customer_id
	 * @return string|null
	 */
	private function getLastOrderDate($customer_id) {
		global $wpdb;

		$last_order_date = $wpdb->get_var(
			$wpdb->prepare(
				"
            SELECT date_last_active 
            FROM {$this->customer_lookup_table} 
            WHERE customer_id = %d
        ",
				$customer_id,
			),
		);

		return $last_order_date ? Utilities::dateToISO($last_order_date) : null;
	}

	/**
	 * Recupera l'indirizzo di fatturazione.
	 *
	 * @param \WC_Customer $customer
	 * @return array
	 */
	private function getBillingAddress($customer) {
		$address = [
			'company' => $customer->get_billing_company(),
			'address1' => $customer->get_billing_address_1(),
			'address2' => $customer->get_billing_address_2(),
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
	private function getShippingAddress($customer) {
		$address = [
			'company' => $customer->get_shipping_company(),
			'address1' => $customer->get_shipping_address_1(),
			'address2' => $customer->get_shipping_address_2(),
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
}
