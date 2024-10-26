<?php
/**
 * Gestione del routing delle richieste API
 *
 * @package    ISIGestSyncAPI
 * @subpackage Core
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Core;

use WP_Error;

/**
 * Classe Router per la gestione delle richieste API.
 *
 * @since 1.0.0
 */
class Router {
	/**
	 * Handler delle API.
	 *
	 * @var ApiHandler
	 */
	private $api_handler;

	/**
	 * Inizializza il router.
	 */
	public function __construct() {
		$this->api_handler = new ApiHandler();
	}

	/**
	 * Gestisce la richiesta API in arrivo.
	 *
	 * @return void
	 */
	public function handleRequest() {
		try {
			// Verifica l'autenticazione
			if (!$this->checkAuthentication()) {
				$this->sendResponse(['error' => 'Non autorizzato'], 401);
				return;
			}

			$request_uri = trim($_SERVER['REQUEST_URI'], '/');
			$request_method = $_SERVER['REQUEST_METHOD'];

			// Estrae il percorso dopo 'isigestsyncapi/'
			$path_parts = explode('isigestsyncapi/', $request_uri);
			if (count($path_parts) < 2) {
				$this->sendResponse(['error' => 'Endpoint non valido'], 404);
				return;
			}

			$endpoint = trim($path_parts[1], '/');
			$endpoint_parts = explode('/', $endpoint);
			$base_endpoint = $endpoint_parts[0];

			// Prepara i dati della richiesta
			$request_data = [
				'method' => $request_method,
				'params' => $endpoint_parts,
				'body' => $this->getRequestBody(),
				'headers' => $this->getRequestHeaders(),
			];

			// Gestisce la richiesta in base all'endpoint
			$response = $this->handleEndpoint($base_endpoint, $request_data);

			$this->sendResponse($response);
		} catch (ISIGestSyncApiException $e) {
			$this->sendResponse(
				[
					'error' => $e->getMessage(),
					'type' => 'warning',
				],
				400,
			);
		} catch (\Exception $e) {
			$this->sendResponse(
				[
					'error' => $e->getMessage(),
					'type' => 'error',
				],
				500,
			);
		}
	}

	/**
	 * Gestisce gli endpoint specifici.
	 *
	 * @param string $endpoint     L'endpoint richiesto.
	 * @param array  $request_data I dati della richiesta.
	 * @return mixed
	 * @throws ISIGestSyncApiException Se l'endpoint non è valido.
	 */
	private function handleEndpoint($endpoint, $request_data) {
		switch ($endpoint) {
			case 'product':
				if ($request_data['method'] === 'GET') {
					if (
						isset($request_data['params'][1]) &&
						$request_data['params'][1] === 'receive'
					) {
						return $this->api_handler->getProductsToReceive();
					}
					return $this->api_handler->getProduct($request_data);
				} elseif ($request_data['method'] === 'POST') {
					if (
						isset($request_data['params'][1]) &&
						$request_data['params'][1] === 'received'
					) {
						return $this->api_handler->postProductReceived($request_data);
					}
					return $this->api_handler->createUpdateProduct($request_data);
				}
				break;

			case 'stock':
				if ($request_data['method'] === 'POST') {
					return $this->api_handler->updateStock($request_data);
				}
				break;

			case 'image':
				if ($request_data['method'] === 'POST') {
					return $this->api_handler->handleImage($request_data);
				}
				break;
		}

		throw new ISIGestSyncApiException('Endpoint non valido o metodo non supportato');
	}

	/**
	 * Verifica l'autenticazione della richiesta.
	 *
	 * @return boolean
	 */
	private function checkAuthentication() {
		$headers = $this->getRequestHeaders();
		$api_key = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : '';

		return $api_key === ConfigHelper::getInstance()->get('api_key');
	}

	/**
	 * Ottiene il corpo della richiesta.
	 *
	 * @return array
	 */
	private function getRequestBody() {
		$input = file_get_contents('php://input');
		return json_decode($input, true) ?: [];
	}

	/**
	 * Ottiene gli headers della richiesta.
	 *
	 * @return array
	 */
	private function getRequestHeaders() {
		if (function_exists('getallheaders')) {
			return getallheaders();
		}

		$headers = [];
		foreach ($_SERVER as $name => $value) {
			if (substr($name, 0, 5) === 'HTTP_') {
				$headers[
					str_replace(
						' ',
						'-',
						ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))),
					)
				] = $value;
			}
		}
		return $headers;
	}

	/**
	 * Invia la risposta API.
	 *
	 * @param mixed   $data   I dati da inviare.
	 * @param integer $status Il codice di stato HTTP.
	 * @return void
	 */
	private function sendResponse($data, $status = 200) {
		status_header($status);
		header('Content-Type: application/json');
		echo wp_json_encode($data);
		exit();
	}
}
