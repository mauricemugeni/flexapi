<?php

// Define the constant that will be used to reference the root directory
define("WPATH", dirname(__FILE__).DIRECTORY_SEPARATOR);

// Indicate whether or not errors will be displayed 
define('SHOW_RUNTIME_ERRORS', true);

// Call the error handle
require_once WPATH . "core/error-handle.php";

// Call the controller
require_once WPATH . "controller.php";
?>



