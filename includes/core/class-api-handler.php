<?php
/**
 * Gestione delle operazioni API
 *
 * @package    ISIGestSyncAPI
 * @subpackage Core
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Core;

use ISIGestSyncAPI\Services\ProductService;
use ISIGestSyncAPI\Services\StockService;
use ISIGestSyncAPI\Services\ImageService;
use ISIGestSyncAPI\Services\OrderService;

/**
 * Classe ApiHandler per la gestione delle operazioni API.
 *
 * @since 1.0.0
 */
class ApiHandler {
	/**
	 * Service per la gestione dei prodotti.
	 *
	 * @var ProductService
	 */
	private $product_service;

	/**
	 * Service per la gestione dello stock.
	 *
	 * @var StockService
	 */
	private $stock_service;

	/**
	 * Service per la gestione delle immagini.
	 *
	 * @var ImageService
	 */
	private $image_service;

	/**
	 * Service per la gestione degli prodotti.
	 *
	 * @var OrderService
	 */
	private $order_service;

	/**
	 * Modalità di riferimento per la sincronizzazione.
	 *
	 * @var boolean
	 */
	private $reference_mode;

	/**
	 * Inizializza l'handler API.
	 */
	public function __construct() {
		$this->product_service = new ProductService();
		$this->stock_service = new StockService();
		$this->image_service = new ImageService();
		$this->order_service = new OrderService();
		$this->reference_mode = ConfigHelper::getInstance()->get('products_reference_mode', false);
	}

	/**
	 * Crea o aggiorna un prodotto.
	 *
	 * @param array $request I dati della richiesta.
	 * @return array
	 * @throws ISIGestSyncApiException Se la richiesta non è valida.
	 */
	public function createUpdateProduct($body) {
		try {
			if (!isset($body)) {
				throw new ISIGestSyncApiBadRequestException('Body non valido');
			}

			// Verifica parametro "isigest" con le informazioni
			if (
				!isset($body['isigest']) ||
				!is_array($body['isigest']) ||
				empty($body['isigest'])
			) {
				throw new ISIGestSyncApiBadRequestException(
					'Richiesta non valida: mancano i dati ISIGest',
				);
			}

			// Verifica SKU
			if (!isset($body['sku']) || empty($body['sku'])) {
				throw new ISIGestSyncApiBadRequestException(
					'Richiesta non valida: SKU non specificato',
				);
			}

			// Se in modalità reference, non permettiamo la creazione/aggiornamento
			if ($this->reference_mode) {
				throw new ISIGestSyncApiBadRequestException(
					'Operazione non permessa in modalità reference',
				);
			}

			return $this->product_service->createOrUpdate($body);
		} catch (ISIGestSyncApiException $e) {
			throw $e;
		} catch (\Exception $e) {
			throw new ISIGestSyncApiException($e->getMessage());
		}
	}

	/**
	 * Recupera i dati di un prodotto.
	 *
	 * @param int $product_id I dati della richiesta.
	 * @return array
	 * @throws ISIGestSyncApiException Se il prodotto non viene trovato.
	 */
	public function getProduct($product_id) {
		if (!$product_id) {
			throw new ISIGestSyncApiBadRequestException('ID prodotto non valido');
		}
		try {
			return $this->product_service->get($product_id);
		} catch (ISIGestSyncApiException $e) {
			throw $e;
		} catch (\Exception $e) {
			throw new ISIGestSyncApiException($e->getMessage());
		}
	}

	// PINGO - PONG
	public function handlePing() {
		return ['pong' => 'pong'];
	}

	/**
	 * Recupera i prodotti da ricevere.
	 *
	 * @return array
	 * @throws ISIGestSyncApiException Se si verifica un errore durante il recupero.
	 */
	public function getProductsToReceive() {
		try {
			return $this->product_service->getProductsToReceive();
		} catch (\Exception $e) {
			throw new ISIGestSyncApiException($e->getMessage());
		}
	}

	/**
	 * Conferma la ricezione di un prodotto.
	 *
	 * @param array $request I dati della richiesta.
	 * @return bool
	 * @throws ISIGestSyncApiException Se la richiesta non è valida.
	 */
	public function postProductReceived($request) {
		try {
			if (!isset($request['body'])) {
				throw new ISIGestSyncApiBadRequestException('Body non valido');
			}

			$body = $request['body'];

			if (!isset($body['id']) || empty($body['id'])) {
				throw new ISIGestSyncApiBadRequestException('ID prodotto non specificato');
			}

			return $this->product_service->setProductAsReceived($body);
		} catch (ISIGestSyncApiException $e) {
			throw $e;
		} catch (\Exception $e) {
			throw new ISIGestSyncApiException($e->getMessage());
		}
	}

	/**
	 * Recupera gli ordini da ricevere.
	 *
	 * @return array
	 * @throws ISIGestSyncApiException Se si verifica un errore durante il recupero.
	 */
	public function getOrdersToReceive() {
		try {
			return $this->order_service->getOrdersToReceive();
		} catch (\Exception $e) {
			throw new ISIGestSyncApiException($e->getMessage());
		}
	}

	/**
	 * Conferma la ricezione di un ordine.
	 *
	 * @param array $request I dati della richiesta.
	 * @return bool
	 * @throws ISIGestSyncApiException Se la richiesta non è valida.
	 */
	public function postOrderReceived($request) {
		try {
			if (!isset($request['body'])) {
				throw new ISIGestSyncApiBadRequestException('Body non valido');
			}

			$body = $request['body'];

			if (!isset($body['id']) || empty($body['id'])) {
				throw new ISIGestSyncApiBadRequestException('ID ordine non specificato');
			}

			return $this->order_service->setAsReceived($body);
		} catch (ISIGestSyncApiException $e) {
			throw $e;
		} catch (\Exception $e) {
			throw new ISIGestSyncApiException($e->getMessage());
		}
	}

	/**
	 * Aggiorna lo stock di un prodotto.
	 *
	 * @param array $request I dati della richiesta.
	 * @return array
	 * @throws ISIGestSyncApiException Se la richiesta non è valida.
	 */
	public function updateStock($body) {
		try {
			if (!isset($body) || !is_array($body) || empty($body)) {
				throw new ISIGestSyncApiBadRequestException('Body non valido');
			}

			// Verifica SKU
			if (!isset($body['sku']) || empty($body['sku'])) {
				throw new ISIGestSyncApiBadRequestException(
					'Richiesta non valida: SKU non specificato',
				);
			}

			return $this->stock_service->updateStock($body, $this->reference_mode);
		} catch (ISIGestSyncApiException $e) {
			throw $e;
		} catch (\Exception $e) {
			throw new ISIGestSyncApiException($e->getMessage());
		}
	}

	/**
	 * Recupera i prodotti da ricevere.
	 *
	 * @return array
	 * @throws ISIGestSyncApiException Se si verifica un errore durante il recupero.
	 */
	public function getProductImages($post_id, $variation_id) {
		try {
			return $this->image_service->getProductImages($post_id, $variation_id);
		} catch (\Exception $e) {
			throw new ISIGestSyncApiException($e->getMessage());
		}
	}

	public function deleteProductImage($id_image) {
		try {
			return $this->image_service->removeImage($id_image);
		} catch (\Exception $e) {
			throw new ISIGestSyncApiException($e->getMessage());
		}
	}

	/**
	 * Gestisce l'upload di un'immagine per un prodotto.
	 *
	 * @param array $request I dati della richiesta.
	 * @return array Dati dell'immagine creata.
	 * @throws ISIGestSyncApiException Se la richiesta non è valida.
	 */
	public function handleImage($request) {
		try {
			if (!isset($request['post_id'])) {
				throw new ISIGestSyncApiBadRequestException('Body non valido');
			}

			// Verifica SKU e URL immagine
			if (
				!isset($request['attachment']) ||
				empty($request['attachment']) ||
				!isset($request['filename']) ||
				empty($request['filename'])
			) {
				throw new ISIGestSyncApiBadRequestException(
					'Richiesta non valida: dati immagine non specificati',
				);
			}

			// Se in modalità reference, non permettiamo l'upload di immagini
			if ($this->reference_mode) {
				throw new ISIGestSyncApiBadRequestException(
					'Operazione non permessa in modalità reference',
				);
			}

			return $this->image_service->handleImageUpload(
				(int) $request['post_id'],
				$request['variant_id'],
				$request['filename'],
				$request['attachment'],
				(bool) $request['cover'],
			);
		} catch (ISIGestSyncApiException $e) {
			throw $e;
		} catch (\Exception $e) {
			throw new ISIGestSyncApiException($e->getMessage());
		}
	}
}
