<?php
/**
 * Gestione delle offerte dei prodotti
 *
 * @package    ISIGestSyncAPI
 * @subpackage Services
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Services;

/**
 * Classe ProductOffersHandler per la gestione delle offerte dei prodotti.
 *
 * @since 1.0.0
 */
class ProductOffersHandler extends BaseService {
	/**
	 * Costruttore.
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Crea o aggiorna un'offerta.
	 *
	 * @param array $offer_data Dati dell'offerta.
	 * @return void
	 */
	private function createOrUpdateOffer($offer_data) {
		// Calcoliamo lo sconto
		$discount = $this->calculateDiscount(
			$offer_data['regular_price'],
			$offer_data['sale_price'],
		);

		if ($discount <= 0) {
			return;
		}

		// Verifichiamo se esiste già un'offerta
		$existing_offer = $this->findExistingOffer(
			$offer_data['product_id'],
			$offer_data['variation_id'],
		);

		if ($existing_offer) {
			$this->updateExistingOffer($existing_offer, $offer_data, $discount);
		} else {
			$this->createNewOffer($offer_data, $discount);
		}

		// Aggiorniamo il prezzo scontato nel prodotto
		$this->updateProductSalePrice($offer_data);
	}

	/**
	 * Calcola lo sconto tra due prezzi.
	 *
	 * @param float $regular_price Prezzo regolare.
	 * @param float $sale_price    Prezzo scontato.
	 * @return float
	 */
	private function calculateDiscount($regular_price, $sale_price) {
		$discount = $regular_price - $sale_price;

		// Applichiamo regole di arrotondamento se configurate
		if ($this->config->get('products_round_net_price')) {
			$discount = round($discount, 2);
		}

		return max(0, $discount);
	}

	/**
	 * Trova un'offerta esistente.
	 *
	 * @param integer $product_id   ID del prodotto.
	 * @param integer $variation_id ID della variante.
	 * @return object|null
	 */
	private function findExistingOffer($product_id, $variation_id) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}isi_api_product_offers 
			WHERE product_id = %d AND variation_id = %d",
				$product_id,
				$variation_id,
			),
		);
	}

	/**
	 * Aggiorna un'offerta esistente.
	 *
	 * @param object $existing_offer Offerta esistente.
	 * @param array  $offer_data    Nuovi dati dell'offerta.
	 * @param float  $discount      Sconto calcolato.
	 * @return void
	 */
	private function updateExistingOffer($existing_offer, $offer_data, $discount) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'isi_api_product_offers',
			[
				'discount_amount' => $discount,
				'min_quantity' => $offer_data['min_quantity'],
				'date_from' => $offer_data['date_from'],
				'date_to' => $offer_data['date_to'],
				'updated_at' => current_time('mysql'),
			],
			['id' => $existing_offer->id],
		);

		// Creiamo anche l'offerta in WooCommerce se necessario
		$this->syncWithWooCommerceOffer($offer_data, $discount);
	}

	/**
	 * Crea una nuova offerta.
	 *
	 * @param array $offer_data Dati dell'offerta.
	 * @param float $discount   Sconto calcolato.
	 * @return void
	 */
	private function createNewOffer($offer_data, $discount) {
		global $wpdb;

		$wpdb->insert($wpdb->prefix . 'isi_api_product_offers', [
			'product_id' => $offer_data['product_id'],
			'variation_id' => $offer_data['variation_id'],
			'discount_amount' => $discount,
			'min_quantity' => $offer_data['min_quantity'],
			'date_from' => $offer_data['date_from'],
			'date_to' => $offer_data['date_to'],
			'created_at' => current_time('mysql'),
			'updated_at' => current_time('mysql'),
		]);

		// Creiamo anche l'offerta in WooCommerce
		$this->syncWithWooCommerceOffer($offer_data, $discount);
	}

	/**
	 * Sincronizza l'offerta con WooCommerce.
	 *
	 * @param array $offer_data Dati dell'offerta.
	 * @param float $discount   Sconto calcolato.
	 * @return void
	 */
	private function syncWithWooCommerceOffer($offer_data, $discount) {
		// Creiamo o aggiorniamo il prezzo specifico in WooCommerce
		$sale_price_data = [
			'price' => -1, // -1 indica che usiamo reduction
			'reduction_type' => 'amount',
			'reduction' => $discount,
			'reduction_tax' => 0,
			'from_quantity' => $offer_data['min_quantity'],
			'from' => $offer_data['date_from'],
			'to' => $offer_data['date_to'],
		];

		if (class_exists('WC_Product_Variable') && $offer_data['variation_id']) {
			$variation = wc_get_product($offer_data['variation_id']);
			if ($variation) {
				$variation->set_sale_price($offer_data['sale_price']);
				$variation->set_date_on_sale_from($offer_data['date_from']);
				$variation->set_date_on_sale_to($offer_data['date_to']);
				$variation->save();
			}
		} else {
			$product = wc_get_product($offer_data['product_id']);
			if ($product) {
				$product->set_sale_price($offer_data['sale_price']);
				$product->set_date_on_sale_from($offer_data['date_from']);
				$product->set_date_on_sale_to($offer_data['date_to']);
				$product->save();
			}
		}
	}

	/**
	 * Rimuove un'offerta.
	 *
	 * @param integer $product_id   ID del prodotto.
	 * @param integer $variation_id ID della variante.
	 * @return boolean
	 */
	public function removeOffer($product_id, $variation_id = 0) {
		global $wpdb;

		$result = $wpdb->delete($wpdb->prefix . 'isi_api_product_offers', [
			'product_id' => $product_id,
			'variation_id' => $variation_id,
		]);

		// Rimuoviamo anche da WooCommerce
		if ($variation_id) {
			$variation = wc_get_product($variation_id);
			if ($variation) {
				$variation->set_sale_price('');
				$variation->set_date_on_sale_from(null);
				$variation->set_date_on_sale_to(null);
				$variation->save();
			}
		} else {
			$product = wc_get_product($product_id);
			if ($product) {
				$product->set_sale_price('');
				$product->set_date_on_sale_from(null);
				$product->set_date_on_sale_to(null);
				$product->save();
			}
		}

		return $result !== false;
	}

	/**
	 * Ottiene il prezzo base di un prodotto o variante.
	 *
	 * @param integer $product_id   ID del prodotto.
	 * @param integer $variation_id ID della variante (opzionale).
	 * @return float
	 */
	private function getBasePrice($product_id, $variation_id = 0) {
		if ($variation_id) {
			$variation = wc_get_product($variation_id);
			if ($variation) {
				return (float) $variation->get_regular_price();
			}
		}

		$product = wc_get_product($product_id);
		return $product ? (float) $product->get_regular_price() : 0;
	}

	/**
	 * Aggiorna il prezzo scontato nel prodotto.
	 *
	 * @param array $offer_data Dati dell'offerta.
	 * @return void
	 */
	private function updateProductSalePrice($offer_data) {
		if ($offer_data['variation_id']) {
			$variation = wc_get_product($offer_data['variation_id']);
			if ($variation) {
				$variation->set_sale_price($offer_data['sale_price']);
				$variation->save();
			}
		} else {
			$product = wc_get_product($offer_data['product_id']);
			if ($product) {
				$product->set_sale_price($offer_data['sale_price']);
				$product->save();
			}
		}
	}
}
