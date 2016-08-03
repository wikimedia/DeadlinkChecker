<?php

/**
 * Copyright (c) 2016, Niharika Kohli
 *
 * @license https://www.gnu.org/licenses/gpl.txt
 */
class checkIfDead {

	/*
	 * UserAgent for the device/browser we are pretending to be
	 */
	protected $userAgent = "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36";

	/**
	 *  HTTP codes that do not indicate a dead link
	 */
	protected $goodHttpCodes = [100, 101, 102, 200, 201, 202, 203, 204, 205, 206, 207, 208, 226, 300,
								301, 302, 303, 304, 305, 306, 307, 308, 103];

	/**
	 * FTP codes that do not indicate a dead link
	 */
	protected $goodFtpCodes = [100, 110, 120, 125, 150, 200, 202, 211, 212, 213, 214, 215, 220, 221, 225, 226,
							   227, 228, 229, 230, 231, 232, 234, 250, 257, 300, 331, 332, 350, 600, 631, 633];

	/**
	 * Curl error codes that are problematic and the link should be considered dead
	 */
	protected $curlErrorCodes = [3, 5, 6, 7, 8, 10, 11, 12, 13, 19, 28, 31, 47, 51, 52, 60, 61, 64, 68, 74, 83, 85, 86, 87];

	/**
	 * Check if a single URL is dead by performing a full text curl
	 *
	 * @param $url string URL to check
	 * @return array with params 'error':curl error number and 'result':true(dead)/false(alive)
	 */
	public function checkDeadlink( $url ) {
		$ch = curl_init();
		curl_setopt_array( $ch, $this->getCurlOptions( $url, true, true ) );
		curl_exec( $ch );
		$headers = curl_getinfo( $ch );
		$error = curl_error( $ch );
		$curlInfo = [
			'http_code' => $headers['http_code'],
			'effective_url' => $headers['url'],
			'curl_error' => $error,
			'url' => $url
		];
		$deadVal = $this->processResult( $curlInfo );
		// If processresult gives us back a NULL, we assume it's dead
		if ( is_null( $deadVal ) ) {
			$deadVal = true;
		}
		$result = ['dead' => $deadVal, 'error' => $error];
		return $result;
	}

	/**
	 * Check an array of links
	 *
	 * @param array $urls of URLs we are checking
	 * @return array with params 'error':curl error number and 'result':true(dead)/false(alive) for each element
	 */
	public function checkDeadlinks( $urls ) {
		// Array of URLs we want to send in for a full check
		$fullCheckURLs = [];
		// Create multiple curl handle
		$multicurl_resource = curl_multi_init();
		if ( $multicurl_resource === false ) {
			return false;
		}
		$curl_instances = [];
		$returnArray = [
			'dead' => [],
			'error' => []
		];
		foreach ( $urls as $id => $url ) {
			$curl_instances[$id] = curl_init();
			if ( $curl_instances[$id] === false ) {
				return false;
			}
			//In case the protocol is missing, assume it goes to HTTPS
			if ( is_null( parse_url( $url, PHP_URL_SCHEME ) ) ) {
				$url = "https:$url";
			}
			$method = $this->getRequestType( $url );
			// Get appropriate curl options
			if ( $method == "FTP" ) {
				curl_setopt_array( $curl_instances[$id], $this->getCurlOptions( $url, true, false ) );
			} else {
				curl_setopt_array( $curl_instances[$id], $this->getCurlOptions( $url, false, false ) );
			}
			// Add the instance handle
			curl_multi_add_handle( $multicurl_resource, $curl_instances[$id] );
		}
		// Let's do the CURL operations
		$active = null;
		do {
			$mrc = curl_multi_exec( $multicurl_resource, $active );
		} while ( $mrc == CURLM_CALL_MULTI_PERFORM );
		while ( $active && $mrc == CURLM_OK ) {
			if ( curl_multi_select( $multicurl_resource ) == -1 ) {
				// To prevent CPU spike
				usleep( 100 );
			}
			do {
				$mrc = curl_multi_exec( $multicurl_resource, $active );
			} while ( $mrc == CURLM_CALL_MULTI_PERFORM );
		}
		// Let's process our curl results and extract the useful information
		foreach ( $urls as $id => $url ) {
			$returnArray['errors'][$id] = curl_error( $curl_instances[$id] );
			$headers = curl_getinfo( $curl_instances[$id] );
			$error = curl_errno( $curl_instances[$id] );
			$curlInfo = [
				'http_code' => $headers['http_code'],
				'effective_url' => $headers['url'],
				'curl_error' => $error,
				'url' => $url
			];
			// Remove each of the individual handles
			curl_multi_remove_handle( $multicurl_resource, $curl_instances[$id] );
			// Deduce whether the site is dead or alive
			$returnArray['dead'][$id] = $this->processResult( $curlInfo );
			// If we got back a null, we should do a full request
			if ( is_null( $returnArray['dead'][$id] ) ) {
				$fullCheckURLs[$id] = $url;
			}
		}
		// Close resource
		curl_multi_close( $multicurl_resource );
		// If we have URLs which didn't return anything, we should do a full check on them
		if ( !empty( $fullCheckURLs ) ) {
			$results = $this->performFullRequest( $fullCheckURLs );
			// Merge back results from full request into our $returnArray
			foreach ( $results['dead'] as $id => $result ) {
				$returnArray['dead'][$id] = $this->processResult( $result );;
				$returnArray['error'][$id] = $results['error'][$id];
			}
		}
		return $returnArray;
	}

	/**
	 * Perform a complete text request, not just for headers
	 *
	 * @param array $urls URLs we are checking
	 * @return array with params 'error':curl error number and 'result':true(dead)/false(alive) for each element
	 */
	protected function performFullRequest( $urls ) {
		// Create multiple curl handle
		$multicurl_resource = curl_multi_init();
		if ( $multicurl_resource === false ) {
			return false;
		}
		$curl_instances = [];
		$returnArray = [
			'dead' => [],
			'error' => []
		];
		foreach ( $urls as $id => $url ) {
			$curl_instances[$id] = curl_init();
			if ( $curl_instances[$id] === false ) {
				return false;
			}
			//In case the protocol is missing, assume it goes to HTTPS
			if ( is_null( parse_url( $url, PHP_URL_SCHEME ) ) ) {
				$url = "https:$url";
			}
			$method = $this->getRequestType( $url );
			// Get appropriate curl options
			if ( $method == "FTP" ) {
				curl_setopt_array( $curl_instances[$id], $this->getCurlOptions( $url, true, true ) );
			} else {
				curl_setopt_array( $curl_instances[$id], $this->getCurlOptions( $url, false, true ) );
			}
			// Add the instance handle
			curl_multi_add_handle( $multicurl_resource, $curl_instances[$id] );
		}
		// Let's do the CURL operations
		$active = null;
		do {
			$mrc = curl_multi_exec( $multicurl_resource, $active );
		} while ( $mrc == CURLM_CALL_MULTI_PERFORM );
		while ( $active && $mrc == CURLM_OK ) {
			if ( curl_multi_select( $multicurl_resource ) == -1 ) {
				// To prevent CPU spike
				usleep( 100 );
			}
			do {
				$mrc = curl_multi_exec( $multicurl_resource, $active );
			} while ( $mrc == CURLM_CALL_MULTI_PERFORM );
		}
		// Let's process our curl results and extract the useful information
		foreach ( $urls as $id => $url ) {
			$returnArray['error'][$id] = curl_error( $curl_instances[$id] );
			$headers = curl_getinfo( $curl_instances[$id] );
			$error = curl_errno( $curl_instances[$id] );
			$curlInfo = [
				'http_code' => $headers['http_code'],
				'effective_url' => $headers['url'],
				'curl_error' => $error,
				'url' => $url
			];
			// Remove each of the individual handles
			curl_multi_remove_handle( $multicurl_resource, $curl_instances[$id] );
			$returnArray['dead'][$id] = $this->processResult( $curlInfo );
			// If we get back a null with full request too, we mark it as a dead link to be on the safe side
			if ( is_null( $returnArray['dead'][$id] ) ) {
				$returnArray['dead'][$id] = true;
			}
		}
		// Close resource
		curl_multi_close( $multicurl_resource );
		return $returnArray;
	}

	/**
	 * Get CURL options
	 *
	 * @param $url String URL we are testing against
	 * @param bool $ftp Is this an FTP request?
	 * @param bool $full Is this a request for full body or just header?
	 * @return array Options for curl
	 */
	public function getCurlOptions( $url, $ftp = false, $full = false ) {
		$options = [
			CURLOPT_URL => $url,
			CURLOPT_HEADER => 1,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_USERAGENT => $this->userAgent
		];
		if ( $ftp ) {
			$options[CURLOPT_FTP_USE_EPRT] = 1;
			$options[CURLOPT_FTP_USE_EPSV] = 1;
			$options[CURLOPT_FTPSSLAUTH] = CURLFTPAUTH_DEFAULT;
			$options[CURLOPT_FTP_FILEMETHOD] = CURLFTPMETHOD_SINGLECWD;
		}
		if ( $full ) {
			$options[CURLOPT_USERPWD] = "anonymous:anonymous@domain.com";
			$options[CURLOPT_TIMEOUT] = 60;
		} else {
			$options[CURLOPT_NOBODY] = 1;
		}
		return $options;
	}

	/**
	 * Get request type
	 *
	 * @param $url String URL we are checking against
	 * @return string "FTP" or "HTTP"
	 */
	public function getRequestType( $url ) {
		if ( strtolower( parse_url( $url, PHP_URL_SCHEME ) ) == "ftp" ) {
			return "FTP";
		} else {
			return "HTTP";
		}
	}

	/**
	 * Process the returned headers
	 *
	 * @param array $curlInfo with values: Returned headers, Error number, Url checked for
	 * @return bool true if dead; false if not
	 */
	protected function processResult( $curlInfo ) {
		//Determine if we are using FTP or HTTP
		$method = $this->getRequestType( $curlInfo['url'] );
		// Get HTTP code returned
		$httpCode = $curlInfo['http_code'];
		// Get final URL
		$effectiveUrl = $curlInfo['effective_url'];
		// Clean final url, removing scheme, 'www', and trailing slash
		$effectiveUrlClean = $this->cleanUrl( $effectiveUrl );
		// Get an array of possible root urls
		$possibleRoots = $this->getDomainRoots( $curlInfo['url'] );
		if ( $httpCode >= 400 && $httpCode < 600 ) {
			// Some servers don't support NOBODY requests, so if an HTTP error code is returned,
			// we check the URL again with a full page request
			return null;
		}
		// Check for error messages in redirected URL string
		if ( strpos( $effectiveUrlClean, '404.htm' ) !== false ||
			 strpos( $effectiveUrlClean, '/404/' ) !== false ||
			 stripos( $effectiveUrlClean, 'notfound' ) !== false
		) {
			return true;
		}
		// Check if there was a redirect by comparing final URL with original URL
		if ( $effectiveUrlClean != $this->cleanUrl( $curlInfo['url'] ) ) {
			// Check against possible roots
			foreach ( $possibleRoots as $root ) {
				// We found a match with final url and a possible root url
				if ( $root == $effectiveUrlClean ) {
					return true;
				}
			}
		}
		//If there was an error during the CURL process, check if the code returned is a server side problem
		if ( in_array( $curlInfo['curl_error'], $this->curlErrorCodes ) ) {
			return true;
		}
		//Check for valid non-error codes for HTTP or FTP
		if ( $method == "HTTP" && !in_array( $httpCode, $this->goodHttpCodes ) ) {
			return true;
			//Check for valid non-error codes for FTP
		} elseif ( $method == "FTP" && !in_array( $httpCode, $this->goodFtpCodes ) ) {
			return true;
		}
		//Yay, the checks passed, and the site is alive.
		return false;
	}

	/**
	 * Compile an array of "possible" root URLs. With subdomain, without subdomain etc.
	 *
	 * @param string $url Initial url
	 * @return array Possible root domains (strings)
	 */
	private function getDomainRoots( $url ) {
		$roots = [];
		$pieces = parse_url( $url );
		if ( !isset( $pieces['host'], $pieces['host'] ) ) {
			return [];
		}
		$roots[] = $pieces['host'];
		$roots[] = $pieces['host'] . '/';
		$domain = isset( $pieces['host'] ) ? $pieces['host'] : '';
		if ( preg_match( '/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs ) ) {
			$roots[] = $regs['domain'];
			$roots[] = $regs['domain'] . '/';
		}
		$parts = explode( '.', $pieces['host'] );
		if ( count( $parts ) >= 3 ) {
			$roots[] = implode( '.', array_slice( $parts, -2 ) );
			$roots[] = implode( '.', array_slice( $parts, -2 ) ) . '/';
		}
		return $roots;
	}

	/**
	 * Remove scheme, 'www', URL fragment, leading forward slashes and trailing slash
	 *
	 * @param string $input
	 * @return string Cleaned url string
	 */
	private function cleanUrl( $input ) {
		// scheme and www
		$url = preg_replace( '/^((https?:|ftp:)?(\/\/))?(www\.)?/', '', $input );
		// fragment
		$url = preg_replace( '/#.*/', '', $url );
		// trailing slash
		$url = preg_replace( '{/$}', '', $url );
		return $url;
	}
}
