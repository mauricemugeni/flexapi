<?php
/**
 * This file contains functions that are responsible for capturing all errors
 * that occur during code execution. This service should be disabled in 
 * production servers.
 *
 * @author Ken Gichia (sandukulangu@gmail.com)
 */

// Define the errors to show
error_reporting(E_ALL);

// This setting should be set to false in production servers
if (!defined('SHOW_RUNTIME_ERRORS')) define('SHOW_RUNTIME_ERRORS', true);

// The depth of the arg dump
if (!defined('SHOW_RUNTIME_ERROR_DEPTH')) define('SHOW_RUNTIME_ERROR_DEPTH', 30);

/**
 * This function works just like var_dump. Its purpose is to view the contents
 * of a variable in production servers where xdebug is not installed.
 *
 * @param mixed   $var
 * @param int     $depth = 0
 * @param string  $key = null
 */
function argDump($var, $depth=0, $key=null) {
	// Wrap everything with a pre tag
	if ( $depth == 0 )
		echo "\n<pre style=\"text-align:left; font-size:12px; font-family:'Lucida Console', Monaco, monospace;\">";
	
	// Set the padding for the arg
	$pad = ""; $p = '<font color="#888a85">=&gt;</font>';
	for( $apad=0; $apad<$depth; $apad++ ) $pad .= "  ";
	
	// When dumping an array
	$apad = $pad;
	if ( !is_null($key) ) $pad .= "'$key' $p ";
	
	// Boolean dump
	if ( is_bool($var) && $var==true ) {
		echo "\n$pad<small>boolean</small> <font color=\"#75507b\">true</font>";
	}
	else if ( is_bool($var) && $var==false ) {
		echo "\n$pad<small>boolean</small> <font color=\"#75507b\">false</font>";
	}
	
	// Integer dump
	else if ( is_int($var) ) {
		echo "\n$pad<small>integer</small> <font color=\"#4e9a06\">$var</font>";
	}
	
	// Floating point dump
	else if ( is_double($var) ) {
		echo "\n$pad<small>double</small> <font color=\"#f57900\">$var</font>";
	}
	
	// String dump
	else if ( is_string($var) ) {
		echo "\n$pad<small>string</small> <font color=\"#cc0000\">'".htmlentities($var, ENT_QUOTES)."'</font>";
	}
	
	// Array dump
	else if ( is_array($var) && empty($var) ) {
		echo "\n$pad<b>array</b> <font color=\"#888a85\"><i>empty</i></font>";
	}
	else if ( is_array($var) && !empty($var) ) {
		if ( $depth > 0 ) { $pad .= "\n$apad  "; $depth++; }
		echo "\n$pad<b>array</b> <i>(size=".count($var).")</i>";
		
		// If the depth has exceeded what can be shown
		if ( ($depth+1) < SHOW_RUNTIME_ERROR_DEPTH ) {
			foreach ( $var as $k=>$val ) argDump($val, ($depth+1), $k);
		}
		else {
			echo "\n$apad    <i>...</i>";
		}
	}
	
	// Object dump
	else if (is_object($var)) {
		echo "\n$pad<b>object</b><i>(" .get_class($var).')</i>';
		
		echo "\n$apad  <font color=\"#957d47\">public methods</font>";
		$items = get_class_methods($var);
		if ( count($items) > 0 && ($depth+2) < SHOW_RUNTIME_ERROR_DEPTH ) {
			foreach ( $items as $val )
				echo "\n$apad    <small>$val()</small>";
		}
		else if ( count($items) == 0 && ($depth+2) < SHOW_RUNTIME_ERROR_DEPTH ) {
			echo "\n$apad    <font color=\"#888a85\"><i>none</i></font>";
		}
		else if ( count($items) >= 0 && ($depth+2) >= SHOW_RUNTIME_ERROR_DEPTH ) {
			echo "\n$apad    <i>...</i>";
		}
		
		echo "\n$apad  <font color=\"#957d47\">public properties</font>";
		$items = get_object_vars($var);
		if ( count($items) > 0 && ($depth+2) < SHOW_RUNTIME_ERROR_DEPTH ) {
			foreach ( $items as $k=>$val ) 
				argDump($val, ($depth+2), $k);
		}
		else if ( count($items) == 0 && ($depth+2) < SHOW_RUNTIME_ERROR_DEPTH ) {
			echo "\n$apad    <font color=\"#888a85\"><i>none</i></font>";
		}
		else if ( count($items) >= 0 && ($depth+2) >= SHOW_RUNTIME_ERROR_DEPTH ) {
			echo "\n$apad    <i>...</i>";
		}
	}
	
	// Resource dump
	else if (is_resource($var)) {
		echo "\n$pad<b>resource</b><i>(" .get_resource_type($var).')</i>';
	}
	
	// Null dump
	else if ( is_null($var) ) {
		echo "\n$pad<font color=\"#3465a4\">null</font>";
	}
	
	// Unknown type dump
	else {
		echo "\n$pad<small>unknown</small> Unknown Type";
	}
	
	// Wrap everything with a pre tag
	if ( $depth == 0 ) echo "\n</pre>";
}

/**
 * If we are to show errors in the specified server
 */
if ( SHOW_RUNTIME_ERRORS ) {
	/**
	 * Enable one to capture and display all errors and warnings as supported by
	 * the version of PHP installed.
	 */
	register_shutdown_function( function () {
		$error = error_get_last();
		if ( !empty($error) ) {
			argDump($error);
		}
		session_write_close(); exit;
	});


	/**
	 * Enable one to capture and display all exceptions thrown during code
	 * execution.
	 */
	set_exception_handler ( function ($e) {
		$error['message'] = $e->getMessage();
		$error['file'] = $e->getFile();
		$error['line'] = $e->getLine();
		$error['trace'] = $e->getTrace();
	
		argDump($error);
		session_write_close(); exit;
	});
}
