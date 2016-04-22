<?php

class TemplateResource {
	private static $templateResource;
	
	public static function setResource($key, $val) {
		$key = strtolower($key);
		
		if (!in_array($key, array("js", "css", "title"))) return;

		if (in_array($key, array("js", "css"))) {
			if (is_null(self::$templateResource) || empty(self::$templateResource))
				self::$templateResource = array();

			if (!isset(self::$templateResource[$key]))
				self::$templateResource[$key] = array();

			foreach($val as $i=>$item) {
				if ( !in_array($item, self::$templateResource[$key]) )
					self::$templateResource[$key][] = $item;
			}
		}
		else {
			self::$templateResource[$key] = $val;
		}
	}

	public static function unsetResource($key, $val) {
		$key = strtolower($key);
		
		if (!in_array($key, array("js", "css", "title"))) return;

		foreach ( $val as $item ) {
			if(($index = array_search($item, self::$templateResource[$key])) !== false) {
				unset(self::$templateResource[$key][$index]);
			}
		}
	}

	public static function getResource($key) {
		if (is_null(self::$templateResource) || empty(self::$templateResource))
				self::$templateResource = array();
				
		// Ensure there is something to work with
		if ( isset(self::$templateResource[$key]) )
			return self::$templateResource[$key];
		return null;
	}
}

function set_css($css) {
	if (isset($css) && is_array($css)) 
		TemplateResource::setResource('css', $css);
}

function unset_css($css) {
	if (isset($css) && is_array($css)) 
		TemplateResource::unsetResource('css', $css);
}

function set_js($js) {
	if (isset($js) && is_array($js)) 
		TemplateResource::setResource('js', $js);
}

function unset_js($js) {
	if (isset($js) && is_array($js)) 
		TemplateResource::unsetResource('js', $js);
}

function set_title($title) {
	if (isset($title) && is_string($title)) 
		TemplateResource::setResource('title', $title);
}

function is_menu_set($item) {
	if ( $item=="home" && empty($_GET) ) 
		return ' class="current-page"';
	else if ( isset( $_GET[$item] ) )
		return ' class="current-page"';
	return '';
}


function exit_app() {
	// Ensure that the session is saved
	session_write_close();

	// Exit the app
	exit;
}