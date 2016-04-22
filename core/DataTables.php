<?php

/***
 * This class will assist in the backend processing of data table objects
 */
class DataTables extends Database {
	private $tableName;
	private $dataTypes;
	private $columnNames;
	private $andWhereSql;
	private $innerJoinSql;
	
	public function __construct() {
		$this->tableName = "";
		$this->innerJoinSql = "";
		$this->dataTypes = array();
		$this->columnNames = array();
		$this->andWhereSql = array();
	}
	
	
	public function setTable($params) {
		if ( !is_array($params) )
			throw new Exception("Ensure that the parameters are properly set");
		
		if ( !isset($params['select']) || !isset($params['from']) )
			throw new Exception("Please make sure you specify the columns and tables.");
		
		// Set the table arg
		$stmt = $this->prepareQuery("SELECT ".$params['select']." FROM ".$params['from']);
		if ( $stmt->execute() ) {
			$this->tableName = ltrim ( $this->removeSpaces($params['from']) );
			$this->setDataTypes();
			
			$cols = explode( ",", ltrim ( $this->removeSpaces($params['select']) ) );
			$this->columnNames = array();
			foreach ( $cols as $col ) {
				$col = ltrim($col);
				if ( isset($this->dataTypes[$col]) ) {
					$this->columnNames[] = $col;
				}
			}
		}
		
		// We are here because there is a problem with the query passed
		else {
			throw new Exception("Please ensure that you supply the appropriate tables and columns");
		}
		
		// If the process ended well, allow one to continue
		return $this;
	}
	
	
	public function addInnerJoin($left, $right) {
		if ( isset($this->dataTypes[$left]) && isset($this->dataTypes[$right]) ) {
			if ( $this->innerJoinSql != "" ) $this->innerJoinSql = " AND ";
			
			// Sort data types
			$right = $this->validSearchColumn($right);
			$left = $this->validSearchColumn($left);
			
			// The inner sql
			$this->innerJoinSql .= "$left=$right";
		}
		return $this;
	}
	
	
	public function andWhere($field, $param) {
		if ( $this->isValid($param) && isset($this->dataTypes[$field]) )
			$this->andWhereSql[$field] = $param;
		return $this;
	}
	
	
	public function concatFields($fields, $pos=null) {
		$str = "";
		foreach ( explode( ",", $fields ) as $field ) {
			$field = ltrim($field);
			
			if ( $this->isValid($field) && isset($this->dataTypes[$field]) ) {
				if ( $str != "" ) $str .= ", ' ', ";
				if ( is_null($pos) ) $pos = $field;
				$str .= $field;
			}
		}
		
		// Set the columns to concat
		$key = array_search($pos, $this->columnNames);
		if ( $key !== false ) $this->columnNames[$key] = "CONCAT($str)";
		return $this;
	}
	
	
	// Useful for debugging purposes as it will show you the sql that is being 
	// used to generate the table
	public function getQueryString() {
		return "SELECT " . implode(", ", $this->columnNames) .
		       " FROM " . $this->tableName .
		       " " . $this->getFilter() . // Where
		       " " . $this->getOrder() .  // Order by 
		       " " . $this->getLimit();   // Limit query  
	}
	
	
	// Function called to get the table data
	public function showTable() {
		// paging
		$sLimit = $this->getLimit();
		
		// Page order
		$sOrder = $this->getOrder();
		
		// Filtering
		$sWhere = $this->getFilter();
		
		// Total number of records
		$sql = "SELECT COUNT(".$this->columnNames[0].") AS count FROM ".$this->tableName." ".$this->getFilter("table-filter");
		$stmt = $this->prepareSQL($sql, "table-filter"); $iTotal = 0;
		if ( $stmt->execute() ) {
			$iTotal = $stmt->fetchAll();
			$iTotal = $iTotal[0]["count"];
		}
		
		// Total number of filtered records
		$sql = "SELECT COUNT(".$this->columnNames[0].") AS count FROM ".$this->tableName." $sWhere";
		$stmt = $this->prepareSQL($sql); $iFilteredTotal = 0;
		if ( $stmt->execute() ) {
			$iFilteredTotal = $stmt->fetchAll();
			$iFilteredTotal = $iFilteredTotal[0]["count"];
		}


		/***
		 * Output data
		 */		
		$output = array(
			"sEcho" => isset($_GET['sEcho']) ? intval($_GET['sEcho']): 1,
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iFilteredTotal,
			"aaData" => array()
		);
		
		// Run the query
		$sql = "SELECT ".implode(", ", $this->columnNames).
		       " FROM ".$this->tableName." $sWhere $sOrder $sLimit"; 
		$stmt = $this->prepareSQL($sql);
		$stmt->execute();
		
		// fill the data
		while ($row = $stmt->fetch()) {
			$d = array();
			foreach ( $row as $k=>$v ) if ( is_int( $k ) ) $d[] = $v;
			
			$output['aaData'][] = $d;
		}
		
		// Display the content
		//header("Content-type: text/x-json");
		echo json_encode( $output ); exit();
	}


	// Private functions to assist in data display
	private function getLimit() {
		$sLimit = "";
		if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' ) {
			if ( $this->getDriver() == "pgsql" ) {
				$sLimit = intval( $_GET['iDisplayStart'] ) / intval( $_GET['iDisplayLength'] );
				
				$sLimit = "LIMIT ".intval( $_GET['iDisplayLength'] )
				        . " OFFSET ".($sLimit*intval( $_GET['iDisplayLength'] ));
			}
			else if ( $this->getDriver() == "mysql" ) {
				$sLimit = "LIMIT ".intval( $_GET['iDisplayStart'] ).", ".
					intval( $_GET['iDisplayLength'] );
			}
		}
		
		return $sLimit;
	}
	
	private function getOrder() {
		$sOrder = "";
		if ( isset( $_GET['iSortCol_0'] ) ) {
			$sOrder = "ORDER BY  ";
			for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ ) {
				if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" ) {
					$sOrder .= $this->columnNames[ intval( $_GET['iSortCol_'.$i] ) ]." ".
						($_GET['sSortDir_'.$i]==='asc' ? 'ASC' : 'DESC') .", ";
				}
			}
		
			$sOrder = substr_replace( $sOrder, "", -2 );
			if ( $sOrder == "ORDER BY" ) $sOrder = "";
		}
		
		return $sOrder;
	}
	
	private function getFilter($setting="all") {
		$sWhere = "";
		if ( $setting != "table-filter" ) {
			if ( isset($_GET['sSearch']) && $_GET['sSearch'] != "" ) {
				for ( $i=0 ; $i<count($this->columnNames) ; $i++ ) {
					if ( $sWhere != "" ) $sWhere .= " OR ";
					$sWhere .= $this->validSearchColumn($i)." LIKE :sSearch";
				}
				$sWhere = "WHERE ($sWhere) ";
			} 
		
			/* Individual column filtering */
			for ( $i=0 ; $i<count($this->columnNames) ; $i++ ) {
				if ( isset($_GET['bSearchable_'.$i]) && $_GET['bSearchable_'.$i] == "true" && $_GET['sSearch_'.$i] != '' ) {
					if ( $sWhere == "" ) $sWhere = "WHERE ";
					else $sWhere .= " AND ";
					$sWhere .= $this->validSearchColumn($i)." LIKE :sSearch_$i ";
				}
			}
		}
		
		/* Add predefined values in the AND statement */
		$i = 0;
		foreach ( $this->andWhereSql as $col=>$val ) {
			if ( $sWhere == "" ) $sWhere = "WHERE ";
			else $sWhere .= " AND ";
			$sWhere .= $this->validSearchColumn($col) . " LIKE :sPredefAnd_$i";
			$i++;
		}
		
		/* Inner join implementation */
		if ( $this->innerJoinSql != "" ) {
			if ( $sWhere == "" ) $sWhere = "WHERE ";
			else $sWhere .= " AND ";
			$sWhere .= $this->innerJoinSql;
		}
		
		return $sWhere;
	}
	
	private function removeSpaces($str) {
		return preg_replace('/[\s]+/S', ' ', $str);
	}
	
	private function prepareSQL($sql, $setting="all") {
		//echo "<pre style='text-align:left'>sql: $sql</pre>";
		$stmt = $this->prepareQuery($sql);
		
		// Bind parameters
		if ( $setting != "table-filter" ) {
			if ( isset($_GET['sSearch']) && $_GET['sSearch'] != "" ) {
				$str = "%{$_GET['sSearch']}%";
				$stmt->bindValue( 'sSearch', $str );
			}
		
			for ( $i=0 ; $i<count($this->columnNames) ; $i++ ) {
				if ( isset($_GET['bSearchable_'.$i]) && $_GET['bSearchable_'.$i] == "true" && $_GET['sSearch_'.$i] != '' ) {
					$str = "%".$_GET["sSearch_$i"]."%";
					$stmt->bindValue( "sSearch_$i", $str );
				}
			}
		}
		
		/* Add predefined values in the AND statement */
		$i = 0;
		foreach ( $this->andWhereSql as $val ) {
			$stmt->bindValue("sPredefAnd_$i", $val);
			$i++;
		}
		
		return $stmt;
	}
	
	private function validSearchColumn($index) {
		if ( is_int($index) ) $index = $this->columnNames[$index];
		
		// Filter as requested
		$dataType = isset($this->dataTypes[$index]) ? $this->dataTypes[$index]: "";
		
		// Get the valid search column string
		if ( $this->getDriver()=="pgsql" && in_array( $dataType, array('bigint', 'boolean', 'datetime', 'datetimetz', 'date', 'decimal', 'integer', 'smallint', 'time', 'float') ) ) {
			return "$index::text";
		}
		
		return "$index";
	}
	
	private function isValid($param) {
		if ( !is_string($param) ) return false;
		$param = $this->removeSpaces($param);
		return (!is_null($param) || !empty($param));
	}
	
	private function setDataTypes() {
		// Get the string to search the table
		$schema = $this->tableName; $tbl = array();
		if ( strpos($this->tableName, ",") !== false ) {
			$schema = "";
			foreach ( explode(",", $this->tableName) as $val ) {
				$t = explode(" ", ltrim($val));
				$tbl[$t[0]] = $t[1];
				
				if ( $schema != "" ) $schema .= "', '";
				$schema .= $t[0];
			}
		}
		else {
			$val = explode(" ", $schema);
			$schema = $val[0];
			$tbl[$val[0]] = $val[1];
		}
		
		// The schema
		$schema = $this->getSchema( "'$schema'" );
			
		// Set the appropriate data types
		foreach ( $schema as $row ) {
			// Set the datatype
			$val = $tbl[$row['table_name']].".".$row['column_name'];
				
			if ( $this->getDriver() == "pgsql" )
				$this->dataTypes[$val] = $this->pgSqlDataType($row['data_type']);
		}
	}
	
	private function getSchema($tables) {
		$sql = "";
		if ( $this->getDriver() == "pgsql" )
			$sql = "SELECT table_name, column_name, data_type FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME IN ($tables)";
		
		return $this->executeQuery( $sql );
	}
	
	private function pgSqlDataType($dataType) {
		if ( $dataType=="bigint" || $dataType=="bigserial" )
			$dataType = "bigint";
					
		else if ( $dataType=="bit" || $dataType=="bit varying" )
			$dataType = "integer";
						
		else if ( $dataType=="character" || $dataType=="character varying" )
			$dataType = "string";
						
		else if ( $dataType=="real" || $dataType=="double precision" )
			$dataType = "float";
						
		else if ( $dataType=="money" || $dataType=="numeric" )
			$dataType = "decimal";
					
		else if ( $dataType=="smallint" || $dataType=="serial" )
			$dataType = "smallint";
						
		else if ( $dataType=="timestamp" )
			$dataType = "datetime";
						
		else if ( $dataType=="boolean" || $dataType=="time" || $dataType=="date" || $dataType=="integer" )
			$dataType = $dataType;
					
		else $dataType = "string";
		
		return $dataType;
	}
}
