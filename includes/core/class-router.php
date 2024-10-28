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

		// Forza l'inizializzazione di WooCommerce
		$this->initializeWooCommerce();

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
				$this->sendJsonNotFoundRequest();
				exit();
		}
	}

	/**
	 * Verifica l'autenticazione API
	 */
	private function checkApiPermission() {
		$headers = $this->getRequestHeaders();
		$api_key = isset($headers['X-Isigest-Authtoken']) ? $headers['X-Isigest-Authtoken'] : '';
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
		$this->sendJsonError('Unauthorized', 401);
	}

	/**
	 * Invia una risposta di richiesta non valida (400)
	 */
	protected function sendJsonBadRequest() {
		$this->sendJsonError('Bad request', 400);
	}

	/**
	 * Invia una risposta di richiesta non trovato (404)
	 */
	protected function sendJsonNotFoundRequest() {
		$this->sendJsonError('Not found', 404);
	}

	/**
	 * Invia una risposta di risorsa bloccata (423)
	 */
	protected function sendJsonAlreadyRunning() {
		$this->sendJsonError('Importazione già in esecuzione', 423);
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
