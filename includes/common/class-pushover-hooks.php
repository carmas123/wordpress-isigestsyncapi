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
use ISIGestSyncAPI\Services\PushoverService;

/**
 * Aggiungiamo gli hook per le notifiche Pushover
 */
class PushoverHooks {
	/**
	 * Inizializza gli hook di WooCommerce
	 */
	public static function init() {
		if (ConfigHelper::getPushoverNewCustomerEnabled()) {
			// Hook per nuovo cliente
			add_action('woocommerce_created_customer', [self::class, 'handleNewCustomer'], 10, 1);
		}

		// Hook per nuovo ordine
		add_action('woocommerce_new_order', [self::class, 'handleNewOrder'], 10, 1);
	}

	/**
	 * Gestisce la notifica per un nuovo cliente
	 *
	 * @param int $customer_id ID del nuovo cliente
	 */
	public static function handleNewCustomer($customer_id) {
		PushoverService::sendNotificationNewCustomer($customer_id);
	}

	/**
	 * Gestisce la notifica per un nuovo ordine
	 *
	 * @param int $order_id ID del nuovo ordine
	 */
	public static function handleNewOrder($order_id) {
		PushoverService::sendNotificationNewOrder($order_id);
	}
}
