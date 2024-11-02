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
			$this->sendJsonUnauthorized();
			exit();
		}

		// Ottieni il percorso della richiesta
		$request_path = $this->getRequestPath();
		$method = $_SERVER['REQUEST_METHOD'];

		Utilities::logDebug("Request $method path: $request_path");

		// Forza l'inizializzazione di WooCommerce
		$this->initializeWooCommerce();

		// Carichiamo le funzioni personaliizzate
		CustomFunctionsManager::getInstance()->loadCustomFunctions();

		try {
			$response = $this->routeRequest($method, $request_path);
			$this->sendJsonData($response);
		} catch (\Exception $e) {
			$this->sendJsonException($e);
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
		$full_path = isset($parts[1]) ? trim($parts[1], '/') : '';

		// Usa parse_url per ottenere solo il path
		return parse_url($full_path, PHP_URL_PATH) ?? '';
	}

	/**
	 * Indirizza la richiesta al giusto handler
	 */
	private function routeRequest($method, $path) {
		$body = $this->getRequestBody();

		switch ("$method $path") {
			case 'GET ping':
				return $this->api_handler->handlePing();

			case 'GET product/receive':
				return $this->api_handler->getProductsToReceive();

			case 'POST product/received':
				return $this->api_handler->postProductReceived($body);

			case 'GET order/receive':
				return $this->api_handler->getOrdersToReceive();

			case 'POST order/received':
				return $this->api_handler->postOrderReceived($body);

			case 'GET product-image':
				$post_id = isset($_GET['post_id']) ? (int) $_GET['post_id'] : null;
				$variation_id = isset($_GET['variant_id']) ? $_GET['variant_id'] : null;
				$id_image = isset($_GET['id_image']) ? $_GET['id_image'] : null;

				return $this->api_handler->getProductImages($post_id, $variation_id);

			case 'DELETE product-image':
				$id_image = isset($_GET['id_image']) ? $_GET['id_image'] : null;

				return $this->api_handler->deleteProductImage($id_image);

			case 'POST product-image':
				return $this->api_handler->handleImage($body);

			case 'POST product-stock':
				return $this->api_handler->updateStock($body);

			case 'POST product':
				return $this->api_handler->createUpdateProduct($body);

			case 'GET product':
				$id = $_GET['id'] ?? null;
				return $this->api_handler->getProduct($id);

			case 'POST image':
				return $this->api_handler->handleImage($body);

			default:
				$this->sendJsonNotFoundRequest();
				exit();
		}
	}

	/**
	 * Verifica l'autenticazione API
	 */
	private function checkApiPermission() {
		$headers = $this->getRequestHeaders();
		$api_key = $headers['x-isigest-authtoken'] ?? '';
		$valid_key = ConfigHelper::getInstance()->get('api_key');

		return $api_key === $valid_key;
	}

	/**
	 * Ottiene gli headers della richiesta
	 */
	private function getRequestHeaders() {
		$headers = [];
		if (function_exists('getallheaders')) {
			// Nel caso getallheaders() sia disponibile
			$rawHeaders = getallheaders();
			foreach ($rawHeaders as $key => $value) {
				$headers[strtolower($key)] = $value;
			}
		} else {
			// Fallback per server che non supportano getallheaders()
			foreach ($_SERVER as $name => $value) {
				if (substr($name, 0, 5) === 'HTTP_') {
					$headerKey = substr($name, 5);
					$headerKey = str_replace('_', ' ', $headerKey);
					$headerKey = strtolower($headerKey);
					$headerKey = str_replace(' ', '-', $headerKey);
					$headers[$headerKey] = $value;
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
	 * Invia una risposta JSON con dati
	 *
	 * @param mixed $data I dati da inviare
	 * @param int|null $startedAt Timestamp di inizio per calcolare la durata
	 */
	protected function sendJsonData($data, ?int $startedAt = null) {
		ob_clean();
		header('Content-Type: application/json; charset=utf-8');
		http_response_code(200);

		$result = new \stdClass();
		$result->success = true;
		if ($data !== null) {
			$result->data = $data;
		}

		if ($startedAt) {
			$result->duration = (int) (round(microtime(true) - $startedAt, 3) * 1000);
		}

		$response = json_encode($result, JSON_UNESCAPED_UNICODE);

		echo $response;
	}

	/**
	 * Invia una risposta di successo senza dati
	 */
	protected function sendJsonSuccess(?int $startedAt = null) {
		$this->sendJsonData(null, $startedAt);
	}

	/**
	 * Invia una risposta di errore
	 *
	 * @param string $error Messaggio di errore
	 * @param int $code Codice HTTP di stato
	 * @param bool $die Se terminare l'esecuzione
	 */
	protected function sendJsonError(string $error, int $code = 400) {
		ob_clean();
		header('Content-Type: application/json; charset=utf-8');
		http_response_code($code);

		$result = new \stdClass();
		$result->success = false;
		$result->code = $code;
		$result->error = $error;

		$response = json_encode($result, JSON_UNESCAPED_UNICODE);

		echo $response;
	}

	/**
	 * Invia una risposta di errore per eccezioni
	 *
	 * @param \Throwable $error L'eccezione da gestire
	 * @param int $code Codice HTTP di stato
	 * @param bool $die Se terminare l'esecuzione
	 */
	protected function sendJsonException(\Throwable $error, int $code = 500, bool $die = true) {
		Utilities::logException($error);

		ob_clean();
		header('Content-Type: application/json; charset=utf-8');
		http_response_code($code);

		$result = new \stdClass();
		$result->success = false;
		$result->code = $code;
		$result->error = $error->getMessage();

		// Gestione eccezioni personalizzate
		if ($error instanceof ISIGestSyncApiException && method_exists($error, 'getDetail')) {
			$result->detail = $error->getDetail();
		}

		$response = json_encode($result, JSON_UNESCAPED_UNICODE);

		if ($die) {
			exit($response);
		}
		echo $response;
	}

	/**
	 * Invia una risposta di non autorizzato (401)
	 */
	protected function sendJsonUnauthorized() {
		Utilities::logWarn('Unauthorized request from ' . Utilities::getClientIp());
		$this->sendJsonError('Unauthorized', 401);
	}

	/**
	 * Invia una risposta di richiesta non valida (400)
	 */
	protected function sendJsonBadRequest() {
		Utilities::logWarn('Bad request from ' . Utilities::getClientIp());
		$this->sendJsonError('Bad request', 400);
	}

	/**
	 * Invia una risposta di richiesta non trovato (404)
	 */
	protected function sendJsonNotFoundRequest() {
		Utilities::logWarn('Not found request from ' . Utilities::getClientIp());
		$this->sendJsonError('Not found', 404);
	}

	function initializeWooCommerce() {
		if (!class_exists('WooCommerce')) {
			include_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
		}

		// Inizializza WC se non è già fatto
		if (!isset($GLOBALS['woocommerce'])) {
			$GLOBALS['woocommerce'] = WC();
		}

		// Inizializza il resto di WC
		$GLOBALS['woocommerce']->init();

		// Forza l'inizializzazione dei post types
		\WC_Post_types::register_post_types();

		// Forza l'inizializzazione delle tassonomie
		\WC_Post_types::register_taxonomies();

		// Forza le azioni
		if (!did_action('woocommerce_init')) {
			do_action('woocommerce_init');
		}

		if (!did_action('woocommerce_after_register_taxonomy')) {
			do_action('woocommerce_after_register_taxonomy');
		}

		if (!did_action('woocommerce_after_register_post_type')) {
			do_action('woocommerce_after_register_post_type');
		}
	}
}
