<?php
/**
 * Definizione delle eccezioni personalizzate
 *
 * @package    ISIGestSyncAPI
 * @subpackage Core
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Core;

/**
 * Eccezione base per il plugin.
 *
 * @since 1.0.0
 */
class ISIGestSyncApiException extends \Exception {
	/**
	 * Tipo di errore.
	 *
	 * @var string
	 */
	protected $type = 'error';

	/**
	 * Costruttore.
	 *
	 * @param string     $message  Il messaggio di errore.
	 * @param int        $code     Il codice di errore.
	 * @param \Exception $previous L'eccezione precedente.
	 */
	public function __construct($message = '', $code = 0, \Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}

	/**
	 * Ottiene il tipo di errore.
	 *
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Converte l'eccezione in array.
	 *
	 * @return array
	 */
	public function toArray() {
		return [
			'error' => $this->getMessage(),
			'type' => $this->getType(),
			'code' => $this->getCode(),
		];
	}
}

/**
 * Eccezione per richieste non valide.
 *
 * @since 1.0.0
 */
class ISIGestSyncApiBadRequestException extends ISIGestSyncApiException {
	/**
	 * Tipo di errore.
	 *
	 * @var string
	 */
	protected $type = 'bad_request';

	/**
	 * Costruttore.
	 *
	 * @param string     $message  Il messaggio di errore.
	 * @param int        $code     Il codice di errore.
	 * @param \Exception $previous L'eccezione precedente.
	 */
	public function __construct(
		$message = 'Richiesta non valida',
		$code = 400,
		\Exception $previous = null
	) {
		parent::__construct($message, $code, $previous);
	}
}

/**
 * Eccezione per avvisi non bloccanti.
 *
 * @since 1.0.0
 */
class ISIGestSyncApiWarningException extends ISIGestSyncApiException {
	/**
	 * Tipo di errore.
	 *
	 * @var string
	 */
	protected $type = 'warning';

	/**
	 * Costruttore.
	 *
	 * @param string     $message  Il messaggio di errore.
	 * @param int        $code     Il codice di errore.
	 * @param \Exception $previous L'eccezione precedente.
	 */
	public function __construct($message = 'Avviso', $code = 200, \Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}

/**
 * Eccezione per errori di autenticazione.
 *
 * @since 1.0.0
 */
class ISIGestSyncApiAuthException extends ISIGestSyncApiException {
	/**
	 * Tipo di errore.
	 *
	 * @var string
	 */
	protected $type = 'auth_error';

	/**
	 * Costruttore.
	 *
	 * @param string     $message  Il messaggio di errore.
	 * @param int        $code     Il codice di errore.
	 * @param \Exception $previous L'eccezione precedente.
	 */
	public function __construct(
		$message = 'Errore di autenticazione',
		$code = 401,
		\Exception $previous = null
	) {
		parent::__construct($message, $code, $previous);
	}
}

/**
 * Eccezione per errori di validazione.
 *
 * @since 1.0.0
 */
class ISIGestSyncApiValidationException extends ISIGestSyncApiException {
	/**
	 * Tipo di errore.
	 *
	 * @var string
	 */
	protected $type = 'validation_error';

	/**
	 * Errori di validazione.
	 *
	 * @var array
	 */
	protected $validation_errors = [];

	/**
	 * Costruttore.
	 *
	 * @param string     $message           Il messaggio di errore.
	 * @param array      $validation_errors Gli errori di validazione.
	 * @param int        $code             Il codice di errore.
	 * @param \Exception $previous          L'eccezione precedente.
	 */
	public function __construct(
		$message = 'Errori di validazione',
		array $validation_errors = [],
		$code = 422,
		\Exception $previous = null
	) {
		$this->validation_errors = $validation_errors;
		parent::__construct($message, $code, $previous);
	}

	/**
	 * Ottiene gli errori di validazione.
	 *
	 * @return array
	 */
	public function getValidationErrors() {
		return $this->validation_errors;
	}

	/**
	 * Converte l'eccezione in array.
	 *
	 * @return array
	 */
	public function toArray() {
		return array_merge(parent::toArray(), [
			'validation_errors' => $this->getValidationErrors(),
		]);
	}
}

/**
 * Eccezione per elementi non trovati.
 *
 * @since 1.0.0
 */
class ISIGestSyncApiNotFoundException extends ISIGestSyncApiException {
	/**
	 * Tipo di errore.
	 *
	 * @var string
	 */
	protected $type = 'not_found';

	/**
	 * Costruttore.
	 *
	 * @param string     $message  Il messaggio di errore.
	 * @param int        $code     Il codice di errore.
	 * @param \Exception $previous L'eccezione precedente.
	 */
	public function __construct(
		$message = 'Risorsa non trovata',
		$code = 404,
		\Exception $previous = null
	) {
		parent::__construct($message, $code, $previous);
	}
}

class ISIGestSyncApiDbException extends ISIGestSyncApiException {
	/**
	 * @var string|null $sql SQL query string that caused the exception
	 */
	public $sql;

	/**
	 * Constructor.
	 *
	 * @param string|null $message The Exception message to throw.
	 * @param string|null $sql The SQL query string that caused the exception.
	 */
	public function __construct($message, $sql) {
		// Controllo del tipo per assicurare che $sql sia una stringa o null
		if (!is_null($sql) && !is_string($sql)) {
			throw new \InvalidArgumentException(
				sprintf(
					'Il parametro $sql deve essere di tipo stringa o null, %s ricevuto.',
					gettype($sql),
				),
			);
		}

		$this->sql = $sql;
		parent::__construct($message);
	}
}
