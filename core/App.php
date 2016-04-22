<?php

ob_start();

class App {
	/***
	 * This function is responsible for checking if there was an ajax request.
	 */
	public static function isAjaxRequest() {
		if (
			!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
			strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
		) { return true; }
		
		return false;
	}


	/***
	 * A function for starting the session if it has not started
	 */
	public static function startSession() {
		if ( !isset($_SESSION) ) session_start();
	}


	/***
	 * A function for checking if someone has logged into the system
	 */
	public static function isLoggedIn() {
		return isset($_SESSION['username']);
	}


	/***
	 * A function for redirecting
	 */
	public static function redirectTo($page) {
		header( "Location: $page" );
		exit;
	}


	/***
	 * A function for ensuring clean output
	 */
	public static function cleanText($input) {
		return htmlentities(strip_tags($input), ENT_QUOTES, 'UTF-8');
	}


	/***
	 * A function for logging out of the system
	 */
	public function logOut() {
		// Delete the session cookie.
		if (ini_get("session.use_cookies")) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000,
				$params["path"], $params["domain"],
				$params["secure"], $params["httponly"]
			);
		}                
		// Destroy session
		session_unset();
                session_destroy();
		
		// Redirect to the home page
		App::redirectTo("?");
	}
}
