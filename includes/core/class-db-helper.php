<?php
/**
 *
 * @package    ISIGestSyncAPI
 * @subpackage common
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
		if ($wpdb->query($sql) === true) {
			$e = new ISIGestSyncApiDbException(
				$wpdb->error->get_error_message(),
				$wpdb->last_query,
			);
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
		self::execSQL('SET autocommit = 0;');
		self::execSQL('START TRANSACTION');
		try {
			foreach ($sql as $query) {
				if (!self::execSQL($query)) {
					return false;
				}
			}
			self::execSQL('COMMIT');
		} catch (\Exception $e) {
			self::execSQL('ROLLBACK');
			throw $e;
		} finally {
			self::execSQL('SET autocommit = 1;');
		}
		return true;
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
