<?php
namespace ISIGestSyncAPI\Core;

class Router {
	private $api_handler;

	public function __construct() {
		$this->api_handler = new ApiHandler();

		// Hook per intercettare le richieste prima che WordPress le processi
		add_action('init', [$this, 'handleCustomEndpoint'], 0);
	}

	/**
	 * Gestisce gli endpoint personalizzati
	 */
	public function handleCustomEndpoint() {
		// Verifica se è una richiesta per il nostro endpoint
		if (!$this->isApiRequest()) {
			return;
		}

		// Verifica l'autenticazione
		if (!$this->checkApiPermission()) {
			$this->sendResponse(
				[
					'error' => 'Unauthorized',
					'message' => 'Invalid API Key',
				],
				401,
			);
			exit();
		}

		// Ottieni il percorso della richiesta
		$request_path = $this->getRequestPath();
		$method = $_SERVER['REQUEST_METHOD'];

		try {
			$response = $this->routeRequest($method, $request_path);
			$this->sendResponse($response);
		} catch (\Exception $e) {
			$this->sendResponse(
				[
					'error' => 'Error',
					'message' => $e->getMessage(),
				],
				500,
			);
		}
		exit();
	}

	/**
	 * Verifica se la richiesta è per la nostra API
	 */
	private function isApiRequest() {
		$request_uri = trim($_SERVER['REQUEST_URI'], '/');
		return strpos($request_uri, 'isigestsyncapi/') !== false;
	}

	/**
	 * Ottiene il percorso della richiesta
	 */
	private function getRequestPath() {
		$request_uri = trim($_SERVER['REQUEST_URI'], '/');
		$parts = explode('isigestsyncapi/', $request_uri);
		return isset($parts[1]) ? trim($parts[1], '/') : '';
	}

	/**
	 * Indirizza la richiesta al giusto handler
	 */
	private function routeRequest($method, $path) {
		$body = $this->getRequestBody();

		switch ("$method $path") {
			case 'POST product':
				return $this->api_handler->createUpdateProduct($body);

			case 'GET product':
				$id = isset($_GET['id']) ? $_GET['id'] : null;
				return $this->api_handler->getProduct($id);

			case 'GET product/receive':
				return $this->api_handler->getProductsToReceive();

			case 'POST product/received':
				return $this->api_handler->postProductReceived($body);

			case 'POST stock':
				return $this->api_handler->updateStock($body);

			case 'POST image':
				return $this->api_handler->handleImage($body);

			default:
				$this->sendResponse(
					[
						'error' => 'Not Found',
						'message' => 'Endpoint not found',
					],
					404,
				);
				exit();
		}
	}

	/**
	 * Verifica l'autenticazione API
	 */
	private function checkApiPermission() {
		$headers = $this->getRequestHeaders();
		$api_key = isset($headers['X-ISIGest-AuthToken']) ? $headers['X-ISIGest-AuthToken'] : '';
		$valid_key = ConfigHelper::getInstance()->get('API_KEY');

		return $api_key === $valid_key;
	}

	/**
	 * Ottiene gli headers della richiesta
	 */
	private function getRequestHeaders() {
		$headers = [];
		if (function_exists('getallheaders')) {
			$headers = getallheaders();
		} else {
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
		}
		return $headers;
	}

	/**
	 * Ottiene il body della richiesta
	 */
	private function getRequestBody() {
		$input = file_get_contents('php://input');
		return json_decode($input, true) ?: [];
	}

	/**
	 * Invia la risposta API
	 */
	private function sendResponse($data, $status = 200) {
		http_response_code($status);
		header('Content-Type: application/json');
		echo json_encode($data);
		exit();
	}
}
