<?php
/**
 * Gestione degli ordini
 *
 * @package    ISIGestSyncAPI
 * @subpackage Services
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Services;

use ISIGestSyncAPI\Core\ConfigHelper;
use ISIGestSyncAPI\Core\Utilities;
use ISIGestSyncAPI\Core\ISIGestSyncApiNotFoundException;

/**
 * Classe OrderService per la gestione degli ordini.
 *
 * @since 1.0.0
 */
class OrderService extends BaseService {
	/**
	 * Costruttore.
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Recupera i dati di un ordine.
	 *
	 * @param integer $order_id ID dell'ordine.
	 * @return array|null
	 * @throws ISIGestSyncApiNotFoundException Se l'ordine non viene trovato.
	 */
	public function get($order_id) {
		try {
			$order = wc_get_order($order_id);
			if (!$order) {
				throw new ISIGestSyncApiNotFoundException('Ordine non trovato');
			}

			// Verifichiamo se il plugin Sweezy è attivo (Swipo)
			$sweezy_plugin_enabled = is_plugin_active('sweezy-transactions/sweezy-transaction.php');
			// Gestiamo i totali Sweezy
			$sweezy_total = $sweezy_plugin_enabled ? $order->get_meta('total_sweezy') : 0;

			// Verifichiamo se l'ordine è pagato con Sweezy
			$order_paid_with_sweezy = $sweezy_plugin_enabled && $sweezy_total > 0;

			// Leggiamo gli articoli dell'ordine
			$items = $this->getOrderItems($order, $order_paid_with_sweezy);

			// Verifichiamo che ci sia almeno una riga valida altrimenti non possiamo esportare l'ordine
			if (empty($items)) {
				throw new ISIGestSyncApiNotFoundException(
					'Nessuna riga valida per l\'ordine ' . $order_id,
				);
			}

			// Totale pagato
			$total_paid = $order_paid_with_sweezy
				? (float) $sweezy_total
				: (float) $order->get_total();

			$data = [
				'id' => $order->get_id(),
				'reference' => $order->get_order_number(),
				'date' => Utilities::dateToISO($order->get_date_created()->date('Y-m-d H:i:s')),
				'payment' => $order_paid_with_sweezy
					? 'Sweezy'
					: $this->formatPaymentMethod($order->get_payment_method_title()),
				'shipping_cost' => (float) $order->get_shipping_total(),
				'shipping_cost_wt' =>
					(float) ($order->get_shipping_total() + $order->get_shipping_tax()),
				'discounts' => (float) $order->get_discount_total(),
				'discounts_wt' =>
					(float) ($order->get_discount_total() + $order->get_discount_tax()),
				'total_paid' => $total_paid,
				'total' => (float) $total_paid,
				'status' => $order->get_status(),
				'status_name' => \wc_get_order_status_name($order->get_status()),
				'source' => $this->determineOrderSource($order),
				'notes' => $order->get_customer_note(),
				'message' => $this->getOrderMessages($order),
				'customer' => $this->getCustomerData($order),
				'address_invoice' => self::billingAddressToData($order),
				'address_shipping' => self::shippingAddressToData($order),
				'items' => $this->getOrderItems($order, $sweezy_plugin_enabled),
			];

			// Aggiunge ID marketplace se necessario
			if ($data['source'] === 'amazon') {
				$data['amazon_id'] = $order->get_meta('_amazon_order_id');
			} elseif ($data['source'] === 'ebay') {
				$data['ebay_id'] = $order->get_meta('_ebay_order_id');
			}

			return $data;
		} catch (\Exception $e) {
			self::setAsReceived($order_id, $e);
			return null;
		}
	}

	/**
	 * Imposta un ordine come esportato.
	 *
	 * @param integer $order_id ID dell'ordine.
	 * @param string|\Exception|null $error
	 * @return boolean
	 */
	public function setAsReceived($order_id, $error = null) {
		global $wpdb;

		$error_message = null;
		if ($error instanceof \Exception) {
			$error_message = $error->getMessage();
		} elseif (is_string($error)) {
			$error_message = $error;
		}

		Utilities::logDebug("Impostazione ordine come esportato: {$order_id}");

		// Usa la data di modifica dell'ordine
		$modified_date = current_time('mysql');

		$result = $wpdb->replace(
			$wpdb->prefix . 'isi_api_export_order',
			[
				'order_id' => (int) $order_id,
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

	private function getToReceiveQuery(): string {
		global $wpdb;
		$cot = Utilities::wcCustomOrderTableIsEnabled();

		// Impostiamo la tabella
		$orders_table = $cot ? $wpdb->prefix . 'wc_orders' : $wpdb->posts;

		// Ottieni gli stati esportabili
		$exportable_statuses = ConfigHelper::getExportableOrderStatuses();

		// Se non ci sono stati esportabili, restituisci una query che non restituirà risultati
		if (empty($exportable_statuses)) {
			return "SELECT DISTINCT o.`id` AS id FROM {$orders_table} o WHERE 1=0";
		}

		// Prepara gli stati per la query aggiungendo il prefisso 'wc-'
		$formatted_statuses = array_map(function ($status) {
			return "'" . esc_sql($status) . "'";
		}, $exportable_statuses);

		// Unisci gli stati con la virgola per la clausola IN
		$status_list = implode(', ', $formatted_statuses);

		if (Utilities::wcCustomOrderTableIsEnabled()) {
			// Selezioniamo gli ordini dalla tabella wc_orders (Nuova Gestione)
			return "SELECT DISTINCT o.`id` AS id
				FROM {$orders_table} o
				LEFT JOIN {$wpdb->prefix}isi_api_export_order e ON o.`id` = e.`order_id`
				WHERE o.status IN ({$status_list})
				AND (
					e.order_id IS NULL 
					OR e.is_exported = 0 
				)";
		} else {
			// Selezioniamo gli ordini dai Post (Vecchia Gestione)
			return "SELECT DISTINCT o.`ID` AS id
				FROM {$orders_table} o
				LEFT JOIN {$wpdb->prefix}isi_api_export_order e ON o.`ID` = e.`order_id`
				WHERE o.post_status IN ({$status_list})
				AND (
					e.order_id IS NULL 
					OR e.is_exported = 0 
				)";
		}
	}

	/**
	 * Recupera tutti gli ordini da esportare.
	 *
	 * @return array
	 */
	public function getToReceive() {
		global $wpdb;

		$export_bankwire = (bool) get_option('isigest_export_bankwire', false);
		$export_check = (bool) get_option('isigest_export_check', false);

		// Leggiamo gli ordini da esportare
		$orders = $wpdb->get_results($this->getToReceiveQuery(), ARRAY_A);

		$result = [];
		foreach ($orders as $order) {
			try {
				$wc_order = wc_get_order($order['id']);
				if (
					$wc_order &&
					$this->shouldExportOrder($wc_order, $export_bankwire, $export_check)
				) {
					$o = $this->get($wc_order->get_id());
					if (is_array($o) && !empty($o)) {
						$result[] = $o;
					}
				}
			} catch (\Exception $e) {
				Utilities::logError($e->getMessage());
			}
		}

		return $result;
	}

	/**
	 * Recupera gli articoli dell'ordine.
	 *
	 * @param \WC_Order $order
	 * @return array
	 */
	private function getOrderItems($order, $order_is_paid_with_sweezy) {
		$items = [];

		foreach ($order->get_items() as $item) {
			try {
				$product = $item->get_product();
			} catch (\Exception $e) {
				Utilities::logError($e->getMessage());
				return [];
			}

			if (!$product) {
				continue;
			}

			if (ProductService::isVariation($product)) {
				$post_id = $product->get_parent_id();
				$variant_id = $product->get_id();
			} else {
				$post_id = $product->get_id();
				$variant_id = 0;
			}

			// Quantità
			$quantity = (float) $item->get_quantity();

			// Impostiamo i totali
			$total = $quantity ? (float) $item->get_total() : 0;
			$total_wt = $quantity ? (float) ($item->get_total() + $item->get_total_tax()) : 0;

			// Calcoliamo i prezzi unitari
			$price = $quantity ? (float) $item->get_total() / $item->get_quantity() : 0;
			$price_wt = $quantity
				? (float) ($item->get_total() + $item->get_total_tax()) / $item->get_quantity()
				: 0;

			// Calcoliamo la Percentuale IVA
			$tax_percentage = 0.0;
			$tax_class = $item->get_tax_class();
			$tax_rates = \WC_Tax::get_rates($tax_class);
			if (!empty($tax_rates)) {
				$first_rate = reset($tax_rates);
				$tax_percentage = (float) $first_rate['rate'];
			}

			// Nel caso in cui c'è la gestione Sweezy, leggiamo i meta data dell'item
			if ($order_is_paid_with_sweezy) {
				// Leggiamo il prezzo unitario Sweezy, perchè quando un ordine è pagato con Sweezy
				// allora nell'ordine viene originale di WooCommerce c'è 0 perchè il prezzo viene gestito
				// tramite un metacampo
				$price = (float) $item->get_meta('Prezzo Unitario Sweezy');

				// Calcoliamo il prezzo IVA inclusa
				$price_wt = $price * (1 + $tax_percentage / 100);

				// Ricalcoliamo i totali arrotondati
				$total = round($price * $quantity, 2);
				$total_wt = round($price_wt * $quantity, 2);
			}

			$items[] = [
				'post_id' => $post_id,
				'variant_id' => $variant_id,
				'product_name' => $item->get_name(),
				'isigest_code' => $product->get_sku(),
				'product' => [
					'weight' => (float) $product->get_weight(),
					'name' => $product->get_name(),
					'description' => $product->get_description(),
					'description_short' => $product->get_short_description(),
					'width' => (float) $product->get_width(),
					'height' => (float) $product->get_height(),
					'depth' => (float) $product->get_length(),
					'tax_rate' => $this->getProductTaxRate($product),
				],
				'quantity' => $quantity,
				'price' => $price,
				'price_wt' => $price_wt,
				'total' => $total,
				'total_wt' => $total_wt,
				'tax_rate' => $tax_percentage,
			];
		}

		return $items;
	}

	/**
	 * Verifica se un ordine deve essere esportato.
	 *
	 * @param \WC_Order $order
	 * @param bool $export_bankwire
	 * @param bool $export_check
	 * @return bool
	 */
	private function shouldExportOrder($order, $export_bankwire, $export_check) {
		if (
			!$order ||
			$order instanceof WC_Order_Refund ||
			$order->get_type() === 'shop_order_refund'
		) {
			// È un reso oppure ordine non valido
			return false;
		}

		$payment_method = $order->get_payment_method();

		if ($payment_method === 'bacs' && !$export_bankwire) {
			return false;
		}

		if ($payment_method === 'cheque' && !$export_check) {
			return false;
		}

		return true;
	}

	/**
	 * Recupera l'indirizzo di fatturazione.
	 *
	 * @param \WC_Order $order
	 * @return array
	 */
	public static function billingAddressToData($order) {
		// Recuperiamo i dati dell'indirizzo di fatturazione
		$address = [
			'firstname' => $order->get_billing_first_name(),
			'lastname' => $order->get_billing_last_name(),
			'company' => $order->get_billing_company(),
			'address1' => $order->get_billing_address_1(),
			'address2' =>
				$order->get_billing_address_2() !== $order->get_billing_address_1()
					? $order->get_billing_address_2()
					: '',
			'postcode' => $order->get_billing_postcode(),
			'city' => $order->get_billing_city(),
			'state' => $order->get_billing_state(),
			'country' => $order->get_billing_country(),
		];

		// Crea un ID numerico
		$address['id'] = abs(crc32(implode('|', array_filter($address))));

		// Aggiungiamo il Telefono
		$address['phone'] = $order->get_billing_phone();

		return $address;
	}

	/**
	 * Recupera l'indirizzo di spedizione.
	 *
	 * @param \WC_Order $order
	 * @return array
	 */
	public static function shippingAddressToData($order) {
		$address = [
			'firstname' => $order->get_shipping_first_name(),
			'lastname' => $order->get_shipping_last_name(),
			'company' => $order->get_shipping_company(),
			'address1' => $order->get_shipping_address_1(),
			'address2' =>
				$order->get_shipping_address_2() !== $order->get_shipping_address_1()
					? $order->get_shipping_address_2()
					: '',
			'postcode' => $order->get_shipping_postcode(),
			'city' => $order->get_shipping_city(),
			'state' => $order->get_shipping_state(),
			'country' => $order->get_shipping_country(),
		];

		// Crea un ID numerico
		$address['id'] = abs(crc32(implode('|', array_filter($address))));

		// Aggiungiamo il Telefono
		$address['phone'] = $order->get_shipping_phone();

		return $address;
	}

	/**
	 * Recupera i dati del cliente.
	 *
	 * @param \WC_Order $order
	 * @return array
	 */
	private function getCustomerData($order) {
		return [
			'id' => $order->get_customer_id(),
			'email' => $order->get_billing_email(),
		];
	}

	/**
	 * Recupera l'indirizzo di fatturazione.
	 *
	 * @param \WC_Order $order
	 * @return array
	 */
	private function getBillingAddress($order) {
		$address = [
			'company' => $order->get_billing_company(),
			'address1' => $order->get_billing_address_1(),
			'address2' => $order->get_billing_address_2(),
			'postcode' => $order->get_billing_postcode(),
			'city' => $order->get_billing_city(),
			'state' => $order->get_billing_state(),
			'country' => $order->get_billing_country(),
		];

		// Crea un ID numerico
		$address['id'] = abs(crc32(implode('|', array_filter($address))));

		// Aggiungiamo il Telefono
		$address['phone'] = $order->get_billing_phone();

		return $address;
	}

	/**
	 * Recupera l'indirizzo di spedizione.
	 *
	 * @param \WC_Order $order
	 * @return array
	 */
	private function getShippingAddress($order) {
		$address = [
			'company' => $order->get_shipping_company(),
			'address1' => $order->get_shipping_address_1(),
			'address2' => $order->get_shipping_address_2(),
			'postcode' => $order->get_shipping_postcode(),
			'city' => $order->get_shipping_city(),
			'state' => $order->get_shipping_state(),
			'country' => $order->get_shipping_country(),
		];

		// Crea un ID numerico
		$address['id'] = abs(crc32(implode('|', array_filter($address))));

		// Aggiungiamo il Telefono
		$address['phone'] = $order->get_shipping_phone();

		return $address;
	}

	/**
	 * Determina l'origine dell'ordine.
	 *
	 * @param \WC_Order $order
	 * @return string
	 */
	private function determineOrderSource($order) {
		if ($order->get_meta('_amazon_order_id')) {
			return 'amazon';
		}

		if ($order->get_meta('_ebay_order_id')) {
			return 'ebay';
		}

		if ($order->get_meta('_isigest_app_order')) {
			return 'app';
		}

		return 'web';
	}

	/**
	 * Formatta il metodo di pagamento.
	 *
	 * @param string $payment_method
	 * @return string
	 */
	private function formatPaymentMethod($payment_method) {
		if (strpos($payment_method, 'Amazon') === 0) {
			return 'Amazon MarketPlace';
		}
		return $payment_method;
	}

	/**
	 * Recupera i messaggi dell'ordine.
	 *
	 * @param \WC_Order $order
	 * @return string
	 */
	private function getOrderMessages($order) {
		$comments = $order->get_customer_order_notes();
		return !empty($comments) ? reset($comments)->comment_content : '';
	}

	public function setAsReceivedAll(): int {
		global $wpdb;

		$cnt = 0;

		// Leggiamo gli ordini da esportare
		$items = $wpdb->get_results($this->getToReceiveQuery(), ARRAY_A);

		Utilities::logDebug('Impostando ordini come esportati: ' . count($items));

		foreach ($items as $item) {
			try {
				$this->setAsReceived($item['id']);
				$cnt++;
			} catch (\Exception $e) {
				Utilities::logError($e->getMessage());
			}
		}

		return $cnt;
	}
}
