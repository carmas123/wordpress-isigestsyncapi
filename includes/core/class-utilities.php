<?php
/**
 * Funzioni di utilità per il plugin
 *
 * @package    ISIGestSyncAPI
 * @subpackage Core
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Core;

/**
 * Classe Utilities con funzioni di supporto.
 *
 * @since 1.0.0
 */
class Utilities {
	/**
	 * Verifica se una stringa è vuota.
	 *
	 * @param mixed $str Il valore da verificare.
	 * @return boolean
	 */
	public static function isBlank($str): bool {
		return empty($str) && !is_numeric($str);
	}

	/**
	 * Verifica se una stringa non è vuota.
	 *
	 * @param mixed $str Il valore da verificare.
	 * @return boolean
	 */
	public static function isNotBlank($str): bool {
		return !self::isBlank($str);
	}

	/**
	 * Ritorna il primo valore non vuoto.
	 *
	 * @param mixed $primary   Valore primario.
	 * @param mixed $secondary Valore secondario.
	 * @return mixed
	 */
	public static function ifBlank($primary, $secondary) {
		return self::isBlank($primary) ? $secondary : $primary;
	}

	/**
	 * Prepara una stringa per l'SQL.
	 *
	 * @param mixed $value Il valore da preparare.
	 * @return string
	 */
	public static function toSql($value): string {
		global $wpdb;

		if (is_null($value)) {
			return 'NULL';
		} elseif (is_bool($value)) {
			return $value ? '1' : '0';
		} elseif (is_numeric($value)) {
			return (string) $value;
		} else {
			return "'" . $wpdb->_real_escape($value) . "'";
		}
	}

	/**
	 * Prepara un valore numerico per l'SQL.
	 *
	 * @param mixed $value Il valore da preparare.
	 * @return string
	 */
	public static function toSqlN($value): string {
		return is_null($value) ? 'NULL' : (string) $value;
	}

	/**
	 * Prende i primi n caratteri di una stringa.
	 *
	 * @param string  $str    La stringa.
	 * @param integer $length La lunghezza.
	 * @return string
	 */
	public static function strLeft($str, $length): string {
		return mb_substr($str, 0, $length);
	}

	/**
	 * Verifica se una stringa è HTML.
	 *
	 * @param string $string La stringa da verificare.
	 * @return boolean
	 */
	public static function isHTML($string): bool {
		return $string !== strip_tags($string);
	}

	/**
	 * Genera uno slug da una stringa.
	 *
	 * @param string $text Il testo da convertire.
	 * @return string
	 */
	public static function slugify($text): string {
		// Rimuove accenti
		$text = remove_accents($text);

		// Converte in minuscolo
		$text = strtolower($text);

		// Rimuove caratteri speciali
		$text = preg_replace('/[^a-z0-9\s-]/', '', $text);

		// Converte spazi e altri separatori in trattini
		$text = preg_replace('/[\s-]+/', '-', $text);

		// Rimuove spazi iniziali e finali
		$text = trim($text, '-');

		return $text;
	}

	/**
	 * Pulisce un nome di file.
	 *
	 * @param string $filename Il nome del file.
	 * @return string
	 */
	public static function sanitizeFilename($filename): string {
		// Rimuove accenti
		$filename = remove_accents($filename);

		// Rimuove caratteri speciali
		$filename = preg_replace('/[^a-zA-Z0-9\s._-]/', '', $filename);

		// Sostituisce spazi con trattini
		$filename = preg_replace('/[\s]+/', '-', $filename);

		return $filename;
	}

	/**
	 * Formatta un prezzo secondo le impostazioni di WooCommerce.
	 *
	 * @param float   $price   Il prezzo da formattare.
	 * @param boolean $include_symbol Se includere il simbolo della valuta.
	 * @return string
	 */
	public static function formatPrice($price, $include_symbol = true): string {
		$price = number_format(
			$price,
			wc_get_price_decimals(),
			wc_get_price_decimal_separator(),
			wc_get_price_thousand_separator(),
		);

		if ($include_symbol) {
			$price = sprintf(
				get_woocommerce_price_format(),
				get_woocommerce_currency_symbol(),
				$price,
			);
		}

		return $price;
	}

	public static function logEnabled(): bool {
		return !ConfigHelper::getInstance()->get('enable_debug', false);
	}

	/**
	 * Log di un errore o messaggio.
	 *
	 * @param mixed  $message Il messaggio.
	 * @param string $type    Il tipo di messaggio (error, warning, info).
	 * @return void
	 */
	public static function log($message, $type = 'info'): void {
		if (!self::logEnabled() || $type === 'error') {
			return;
		}

		if (!is_string($message)) {
			$message = print_r($message, true);
		}

		$log_file = WP_CONTENT_DIR . '/isigestsyncapi-debug.log';
		$date = date('Y-m-d H:i:s');
		$formatted_message = sprintf("[%s] [%s] %s\n", $date, strtoupper($type), $message);

		error_log($formatted_message, 3, $log_file);
	}

	/**
	 * Log di un errore.
	 *
	 * @param mixed  $message Il messaggio.
	 * @return void
	 */
	public static function logError($message): void {
		self::log($message, 'error');
	}

	/**
	 * Verifica se è attiva la modalità debug.
	 *
	 * @return boolean
	 */
	public static function isDebug(): bool {
		return defined('WP_DEBUG') && WP_DEBUG;
	}

	/**
	 * Pulisce una stringa per renderla compatibile con il campo nome.
	 *
	 * @param string|null $name Il nome da pulire.
	 * @return string|null
	 */
	public static function cleanProductName(?string $name): ?string {
		if ($name === null) {
			return null;
		}

		$invalid_chars = ['<', '>', ';', '=', '#', '{', '}', 'º', '&nbsp'];
		return str_replace($invalid_chars, ' ', $name);
	}

	/**
	 * Ottiene l'indirizzo IP del client.
	 *
	 * @return string
	 */
	public static function getClientIp(): string {
		$ip = '';

		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return sanitize_text_field($ip);
	}

	/**
	 * Genera un ID univoco.
	 *
	 * @param string $prefix Prefisso per l'ID.
	 * @return string
	 */
	public static function generateUniqueId($prefix = ''): string {
		$unique = uniqid($prefix);
		$random = bin2hex(random_bytes(8));
		return $unique . '_' . $random;
	}

	/**
	 * Verifica se una URL è valida.
	 *
	 * @param string $url L'URL da verificare.
	 * @return boolean
	 */
	public static function isValidUrl($url): bool {
		return filter_var($url, FILTER_VALIDATE_URL) !== false;
	}

	/**
	 * Esegue una richiesta HTTP.
	 *
	 * @param string $url     L'URL della richiesta.
	 * @param array  $args    Argomenti per la richiesta.
	 * @param string $method  Il metodo HTTP (GET, POST, etc.).
	 * @return array|\WP_Error
	 */
	public static function httpRequest($url, $args = [], $method = 'GET') {
		$default_args = [
			'method' => $method,
			'timeout' => 30,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking' => true,
			'headers' => [
				'User-Agent' => 'ISIGestSyncAPI/1.0.0',
			],
		];

		$args = wp_parse_args($args, $default_args);
		return wp_remote_request($url, $args);
	}

	/**
	 * Pulisce la cache di un singolo prodotto WooCommerce
	 *
	 * @param int $product_id ID del prodotto
	 * @return void
	 */
	public static function cleanProductCache($product_id) {
		// Rimuove la cache del prodotto
		wp_cache_delete($product_id, 'posts');
		wp_cache_delete('product-' . $product_id, 'products');

		// Pulisce anche la cache dei metadati
		wp_cache_delete($product_id, 'post_meta');

		// Pulisce la cache specifica di WooCommerce
		wc_delete_product_transients($product_id);

		// Pulisce anche la cache del data store
		\WC_Cache_Helper::invalidate_cache_group('product_' . $product_id);

		// Se stai usando object cache, questa assicura che venga ricaricato
		clean_post_cache($product_id);
	}

	/**
	 * Pulisce la cache di più prodotti WooCommerce
	 *
	 * @param array $product_ids Array di ID dei prodotti
	 * @return void
	 */
	public static function cleanMultipleProductsCache($product_ids) {
		foreach ($product_ids as $product_id) {
			self::cleanProductCache($product_id);
		}

		// Pulisce anche la cache generale dei prodotti
		wp_cache_delete('wc_products_onsale', 'products');
		wp_cache_delete('wc_featured_products', 'products');

		// Invalida la cache delle query dei termini
		delete_transient('wc_term_counts');

		// Pulisce la cache delle variazioni
		\WC_Cache_Helper::invalidate_cache_group('products');
	}
}
