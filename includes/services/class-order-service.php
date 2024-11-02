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
use ISIGestSyncAPI\Core\DbHelper;
use ISIGestSyncAPI\Core\Utilities;
use ISIGestSyncAPI\Core\ISIGestSyncApiException;
use ISIGestSyncAPI\Core\ISIGestSyncApiBadRequestException;
use ISIGestSyncAPI\Core\ISIGestSyncApiNotFoundException;

/**
 * Classe OrderService per la gestione degli ordini.
 *
 * @since 1.0.0
 */
class OrderService extends BaseService {
	/**
	 * @var string Tabella degli ordini WooCommerce
	 */
	private $orders_table;

	/**
	 * @var string Tabella degli items degli ordini
	 */
	private $order_items;

	/**
	 * @var string Tabella dei meta dati degli items
	 */
	private $order_itemmeta;

	/**
	 * @var string Tabella degli indirizzi degli ordini
	 */
	private $order_addresses;

	/**
	 * @var string Tabella dei dati operativi degli ordini
	 */
	private $order_operational_data;

	/**
	 * Costruttore.
	 */
	public function __construct() {
		parent::__construct();

		global $wpdb;

		// Inizializzazione delle tabelle
		$this->orders_table = $wpdb->prefix . 'wc_orders';
		$this->order_items = $wpdb->prefix . 'woocommerce_order_items';
		$this->order_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$this->order_addresses = $wpdb->prefix . 'wc_order_addresses';
		$this->order_operational_data = $wpdb->prefix . 'wc_order_operational_data';
	}

	/**
	 * Recupera i dati di un ordine.
	 *
	 * @param integer $order_id ID dell'ordine.
	 * @return array
	 * @throws ISIGestSyncApiNotFoundException Se l'ordine non viene trovato.
	 */
	public function get($order_id) {
		$order = \wc_get_order($order_id);
		if (!$order) {
			throw new ISIGestSyncApiNotFoundException('Ordine non trovato');
		}

		$data = [
			'id' => $order->get_id(),
			'reference' => $order->get_order_number(),
			'date' => $this->dateToISO($order->get_date_created()->date('Y-m-d H:i:s')),
			'payment' => $this->formatPaymentMethod($order->get_payment_method_title()),
			'shipping_cost' => (float) $order->get_shipping_total(),
			'shipping_cost_wt' =>
				(float) ($order->get_shipping_total() + $order->get_shipping_tax()),
			'discounts' => (float) $order->get_discount_total(),
			'discounts_wt' => (float) ($order->get_discount_total() + $order->get_discount_tax()),
			'total_paid' => (float) $order->get_total(),
			'total' => (float) $order->get_total(),
			'status' => $order->get_status(),
			'status_name' => \wc_get_order_status_name($order->get_status()),
			'source' => $this->determineOrderSource($order),
			'notes' => $order->get_customer_note(),
			'message' => $this->getOrderMessages($order),
			'customer' => $this->getCustomerData($order),
			'address_invoice' => $this->getBillingAddress($order),
			'address_delivery' => $this->getShippingAddress($order),
			'items' => $this->getOrderItems($order),
		];

		// Aggiunge ID marketplace se necessario
		if ($data['source'] === 'amazon') {
			$data['amazon_id'] = $order->get_meta('_amazon_order_id');
		} elseif ($data['source'] === 'ebay') {
			$data['ebay_id'] = $order->get_meta('_ebay_order_id');
		}

		return $data;
	}

	/**
	 * Imposta un ordine come esportato.
	 *
	 * @param integer $order_id ID dell'ordine.
	 * @return boolean
	 */
	public function setAsReceived($order_id) {
		global $wpdb;

		// Recupera l'ordine per ottenere la data di modifica
		$order = wc_get_order($order_id);
		if (!$order) {
			throw new ISIGestSyncApiNotFoundException('Ordine non trovato');
		}

		// Usa la data di modifica dell'ordine
		$modified_date = $order->get_date_modified()
			? $order->get_date_modified()->date('Y-m-d H:i:s')
			: current_time('mysql');

		$result = $wpdb->replace(
			$wpdb->prefix . 'isi_api_export_order',
			[
				'order_id' => (int) $order_id,
				'exported' => 1,
				'exported_at' => $modified_date,
			],
			['%d', '%d', '%s'],
		);
		Utilities::logDbResult($result);

		return $result !== false;
	}

	/**
	 * Recupera tutti gli ordini da esportare.
	 *
	 * @return array
	 */
	public function getOrdersToReceive() {
		global $wpdb;

		$export_bankwire = (bool) get_option('isigest_export_bankwire', false);
		$export_check = (bool) get_option('isigest_export_check', false);

		// Query modificata per considerare la data di modifica dell'ordine
		$orders = $wpdb->get_results("
            SELECT DISTINCT p.`id` 
            FROM {$this->orders_table} p
            LEFT JOIN {$wpdb->prefix}isi_api_export_order e ON p.`id` = e.`order_id`
            WHERE 
            AND p.post_status IN ('wc-processing', 'wc-completed')
            AND (
                e.order_id IS NULL 
                OR e.exported = 0 
                OR p.post_modified > e.exported_at
            )
        ");

		$result = [];
		foreach ($orders as $order) {
			try {
				$wc_order = wc_get_order($order->id);
				if (
					$wc_order &&
					$this->shouldExportOrder($wc_order, $export_bankwire, $export_check)
				) {
					$result[] = $this->get($order->ID);
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
	private function getOrderItems($order) {
		$items = [];

		foreach ($order->get_items() as $item) {
			$product = $item->get_product();
			if (!$product) {
				continue;
			}

			$items[] = [
				'product_id' => $product->get_id(),
				'product_attribute_id' => $product->get_id(),
				'product_name' => $item->get_name(),
				'product' => [
					'reference' => $product->get_sku(),
					'ean' => $product->get_meta('_ean'),
					'upc' => $product->get_meta('_upc'),
					'weight' => (float) $product->get_weight(),
					'name' => $product->get_name(),
					'description' => $product->get_description(),
					'description_short' => $product->get_short_description(),
					'width' => (float) $product->get_width(),
					'height' => (float) $product->get_height(),
					'depth' => (float) $product->get_length(),
					'tax_rate' => $this->getProductTaxRate($product),
					// 'brand' => $this->getProductBrand($product),
				],
				'quantity' => (float) $item->get_quantity(),
				'price' => (float) $product->get_regular_price(),
				'price_wt' => (float) wc_get_price_including_tax($product),
				'total' => (float) $item->get_total(),
				'total_wt' => (float) ($item->get_total() + $item->get_total_tax()),
			];
		}

		return $items;
	}

	/**
	 * Recupera l'aliquota IVA del prodotto.
	 *
	 * @param \WC_Product $product
	 * @return float
	 */
	private function getProductTaxRate($product) {
		$tax_rates = \WC_Tax::get_rates($product->get_tax_class());
		if (!empty($tax_rates)) {
			$first_rate = reset($tax_rates);
			return (float) $first_rate['rate'];
		}
		return 0.0;
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
	 * Recupera i dati del cliente.
	 *
	 * @param \WC_Order $order
	 * @return array
	 */
	private function getCustomerData($order) {
		return [
			'id' => $order->get_customer_id(),
			'email' => $order->get_billing_email(),
			'firstname' => $order->get_billing_first_name(),
			'lastname' => $order->get_billing_last_name(),
			'company' => $order->get_billing_company(),
		];
	}

	/**
	 * Recupera l'indirizzo di fatturazione.
	 *
	 * @param \WC_Order $order
	 * @return array
	 */
	private function getBillingAddress($order) {
		return [
			'company' => $order->get_billing_company(),
			'address1' => $order->get_billing_address_1(),
			'address2' => $order->get_billing_address_2(),
			'postcode' => $order->get_billing_postcode(),
			'city' => $order->get_billing_city(),
			'state' => $order->get_billing_state(),
			'country' => $order->get_billing_country(),
			'phone' => $order->get_billing_phone(),
		];
	}

	/**
	 * Recupera l'indirizzo di spedizione.
	 *
	 * @param \WC_Order $order
	 * @return array
	 */
	private function getShippingAddress($order) {
		return [
			'company' => $order->get_shipping_company(),
			'address1' => $order->get_shipping_address_1(),
			'address2' => $order->get_shipping_address_2(),
			'postcode' => $order->get_shipping_postcode(),
			'city' => $order->get_shipping_city(),
			'state' => $order->get_shipping_state(),
			'country' => $order->get_shipping_country(),
			'phone' => $order->get_meta('_shipping_phone'),
		];
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

	/**
	 * Converte una data in formato ISO.
	 *
	 * @param string $date
	 * @return string
	 */
	private function dateToISO($date) {
		return date('c', strtotime($date));
	}
}