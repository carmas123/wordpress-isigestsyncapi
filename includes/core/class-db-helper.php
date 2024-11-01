<?php
/**
 *
 * @package    ISIGestSyncAPI
 * @subpackage core
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Core;

class DbHelper {
	/**
	 * Executes a SQL query and returns the result.
	 *
	 * @param string $sql The SQL query to execute.
	 * @return bool Returns true if the query is executed successfully, otherwise false.
	 * @throws ISIGestSyncApiDbException If an error occurs during the execution of the query.
	 */
	public static function execSQL($sql) {
		global $wpdb;
		if ($wpdb->query($sql) === false) {
			$e = new ISIGestSyncApiDbException($wpdb->last_error, $wpdb->last_query);
			throw $e;
		}

		return true;
	}

	/**
	 * Executes an array of SQL queries.
	 *
	 * This function iterates through the provided array of SQL queries and executes each one.
	 * It returns true if all queries are executed successfully, otherwise false.
	 *
	 * @param array $sql An array of SQL queries.
	 * @return bool True if all queries are executed successfully, otherwise false.
	 */
	public static function execSQLs(array $sql) {
		foreach ($sql as $query) {
			if (!self::execSQL($query)) {
				return false;
			}
		}
		return true;
	}

	// Esecuzione comandi in Transazione
	public static function execSQLsInTransaction(array $sql): bool {
		global $wpdb;

		// Disabilita momentaneamente la visualizzazione degli errori
		$show_errors = $wpdb->show_errors;
		$wpdb->show_errors = false;

		// Salva il valore corrente di autocommit
		$autocommit = $wpdb->get_var('SELECT @@autocommit');

		try {
			// Inizia la transazione
			$wpdb->query('SET autocommit = 0');
			$wpdb->query('START TRANSACTION');

			foreach ($sql as $index => $query) {
				$result = $wpdb->query($query);

				if ($result === false) {
					throw new \Exception(
						sprintf(
							'Query SQL fallita (#%d): %s. Errore: %s',
							$index,
							$query,
							$wpdb->last_error,
						),
					);
				}
			}

			// Commit della transazione
			$wpdb->query('COMMIT');
			return true;
		} catch (\Exception $e) {
			// Rollback in caso di errore
			$wpdb->query('ROLLBACK');

			// Log dell'errore
			Utilities::logError($e);

			throw $e;
		} finally {
			// Ripristina autocommit al suo valore originale
			$wpdb->query("SET autocommit = $autocommit");

			// Ripristina la visualizzazione degli errori
			$wpdb->show_errors = $show_errors;
		}
	}

	public static function startTransaction(): bool {
		return self::execSQLs(['SET autocommit = 0;', 'START TRANSACTION']);
	}
	public static function commitTransaction(): bool {
		return self::execSQLs(['COMMIT', 'SET autocommit = 1;']);
	}
	public static function rollbackTransaction(): bool {
		return self::execSQLs(['ROLLBACK', 'SET autocommit = 1;']);
	}
}
