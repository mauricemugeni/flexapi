<?php
require WPATH . "core/include.php";
$currentPage = "";

if ( is_menu_set('app') != ""){
    $currentPage = WPATH . "modules/index.php";
    set_title("Flex Communications Api");
}
else if(is_menu_set('error') != ""){
    $currentPage = WPATH . "modules/error.php";
    set_title("Flex Communications Api");
}
else if ( !empty($_GET) ) {
	App::redirectTo("?");
}
else{
    $currentPage = WPATH . "modules/index.php";
    if ( App::isLoggedIn() ) {
		set_title("Flex Communications Api");                
	}        
}
if ( App::isAjaxRequest())
	include $currentPage;
else
	require WPATH . "core/template/layout.php";

