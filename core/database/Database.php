<?php

/**
 * This class responsible for managing the database connection
 *
 */

class Database {
	private static $dbConn;
	
	public static function setUp($config) {
		/* Connect to the database */
		self::$dbConn = new PDO(
			$config['dsn'], $config['username'], $config['password'],
			array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION )
		);
	}


	/***
	 * Run a query. It is your responsibility to limit the query before executing
	 * it using this function.
	 */
	public function executeQuery($sql, $params=null) {
		$stmt = self::$dbConn->prepare($sql);
		
		$result = null;
		if ( is_null($params) )
			$result = $stmt->execute();
		else
			$result = $stmt->execute($params);

		if ($result && $stmt->columnCount()>0)
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		return $result;
	}


	/***
	 * Prepare a statement. Allow one to bind parameters safely before one
	 * executes the query.
	 */
	public function prepareQuery($sql) {
		return self::$dbConn->prepare($sql);
	}


	/***
	 * Get the current database driver.
	 */
	public function getDriver() {
		return self::$dbConn->getAttribute(PDO::ATTR_DRIVER_NAME);
	}


	/***
	 * Checks whether a particular column in a table contains a prescribed value
	 */
	public function uniqueColumnValue($table, $field, $value) {
		// If the table or field columns contain inappropriate values, 
		// do not process these values
		$testF = preg_replace('/[^a-zA-Z0-9_]+/S', '', $field);
		$testT = preg_replace('/[^a-zA-Z0-9_]+/S', '', $table);
		if ( $testF != $field || $testT != $table ) return false;
		
		// Get the data
		$stmt = self::$dbConn->prepare("SELECT COUNT($field) AS count FROM $table WHERE $field=:param");
		$stmt->bindValue("param", $value); $stmt->execute();
		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		// Show whether it is unique
		return ((int)$data[0]['count'] == 0);
	}


	/**
	 * Allow one to execute a transaction safely
	 */
	public function executeTransaction($transactions) {
		$status = true;
		try {
			// First of all, let's begin a transaction
			self::$dbConn->beginTransaction();

			// A set of queries. If one fails, an exception should be thrown
			foreach ($transactions as $transaction) {
				$stmt = null;
				if ( is_array($transaction) ) {
					$stmt = self::$dbConn->prepare($transaction['sql']);
					$stmt->execute($transaction['params']);
				}
				else {
					$stmt = self::$dbConn->prepare($transaction);
					$stmt->execute();
				}
			}

			// If we arrive here, it means that no exception was thrown
			// i.e. no query has failed and we can commit the transaction
			self::$dbConn->commit();
		} catch (Exception $e) {
			// An exception has been thrown
			// We must rollback the transaction
			self::$dbConn->rollBack();

			// Show that all is not well
			$status = false;
		}

		// End the transaction mode
		return $status;
	}
}
?>