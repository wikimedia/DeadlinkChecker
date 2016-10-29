<?php

/**
 * Copyright (c) 2016, Niharika Kohli
 *
 * @license https://www.gnu.org/licenses/gpl.txt
 */
namespace Wikimedia\DeadlinkChecker;

class CheckIfDead {

	/**
	 * UserAgent for the device/browser we are pretending to be
	 */
	// @codingStandardsIgnoreStart Line exceeds 100 characters
	protected $userAgent = "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36";
	// @codingStandardsIgnoreEnd

	/**
	 *  HTTP codes that do not indicate a dead link
	 */
	protected $goodHttpCodes = [
		100, 101, 102, 103,
		200, 201, 202, 203, 204, 205, 206, 207, 208, 226,
		300, 301, 302, 303, 304, 305, 306, 307, 308,
	];

	/**
	 * FTP codes that do not indicate a dead link
	 */
	protected $goodFtpCodes = [
		100, 110, 120, 125, 150,
		200, 202, 211, 212, 213, 214, 215, 220, 221, 225,
		226, 227, 228, 229, 230, 231, 232, 234, 250, 257,
		300, 331, 332, 350, 600, 631, 633,
	];

	/**
	 * Curl error codes that are problematic and the link should be considered
	 * dead
	 */
	protected $curlErrorCodes = [
		3, 5, 6, 7, 8, 10, 11, 12, 13, 19, 28, 31, 47,
		51, 52, 60, 61, 64, 68, 74, 83, 85, 86, 87,
	];

	/**
	 * Check if a single URL is dead by performing a full curl request
	 *
	 * @param string $url URL to check
	 *
	 * @return bool|null Returns null if curl is unable to initialize.
	 *     Otherwise returns true (dead) or false (alive).
	 */
	public function isLinkDead( $url ) {
		$deadVal = $this->areLinksDead( [ $url ] );
		$deadVal = $deadVal[$url];

		return $deadVal;
	}

	/**
	 * Check an array of links
	 *
	 * @param array $urls Array of URLs we are checking
	 *
	 * @return array|null Returns null if curl is unable to initialize.
	 *     Otherwise returns an array in which each key is a URL and each value is
	 *     true (dead) or false (alive).
	 */
	public function areLinksDead( $urls ) {
		// Array of URLs we want to send in for a full check
		$fullCheckURLs = [];
		// Create multiple curl handle
		$multicurl_resource = curl_multi_init();
		if ( $multicurl_resource === false ) {
			return null;
		}
		$curl_instances = [];
		$deadLinks = [];
		foreach ( $urls as $id => $url ) {
			$curl_instances[$id] = curl_init();
			if ( $curl_instances[$id] === false ) {
				return null;
			}

			// Get appropriate curl options
			curl_setopt_array(
				$curl_instances[$id],
				$this->getCurlOptions( $this->sanitizeURL( $url ), false )
			);
			// Add the instance handle
			curl_multi_add_handle( $multicurl_resource, $curl_instances[$id] );
		}
		// Let's do the curl operations
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
			$headers = curl_getinfo( $curl_instances[$id] );
			$error = curl_errno( $curl_instances[$id] );
			$curlInfo = [
				'http_code'     => $headers['http_code'],
				'effective_url' => $headers['url'],
				'curl_error'    => $error,
				'url'           => $this->sanitizeURL( $url )
			];
			// Remove each of the individual handles
			curl_multi_remove_handle( $multicurl_resource, $curl_instances[$id] );
			// Deduce whether the site is dead or alive
			$deadLinks[$url] = $this->processCurlResults( $curlInfo, false );
			// If we got back a null, we should do a full page request
			if ( is_null( $deadLinks[$url] ) ) {
				$fullCheckURLs[] = $url;
			}
		}
		// Close resource
		curl_multi_close( $multicurl_resource );
		// Do full page requests for URLs that returned null
		if ( !empty( $fullCheckURLs ) ) {
			$results = $this->performFullRequest( $fullCheckURLs );
			// Merge back results from full requests into our deadlinks array
			$deadLinks = array_merge( $deadLinks, $results );
		}

		return $deadLinks;
	}

	/**
	 * Perform a complete text request, not just for headers
	 *
	 * @param array $urls URLs we are checking
	 *
	 * @return array with params 'error':curl error number and
	 *   'result':true(dead)/false(alive) for each element
	 */
	protected function performFullRequest( $urls ) {
		// Create multiple curl handle
		$multicurl_resource = curl_multi_init();
		if ( $multicurl_resource === false ) {
			return false;
		}
		$curl_instances = [];
		$deadlinks = [];
		foreach ( $urls as $id => $url ) {
			$curl_instances[$id] = curl_init();
			if ( $curl_instances[$id] === false ) {
				return false;
			}
			// Get appropriate curl options
			curl_setopt_array(
				$curl_instances[$id],
				$this->getCurlOptions( $this->sanitizeURL( $url ), true )
			);
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
			$headers = curl_getinfo( $curl_instances[$id] );
			$error = curl_errno( $curl_instances[$id] );
			$curlInfo = [
				'http_code'     => $headers['http_code'],
				'effective_url' => $headers['url'],
				'curl_error'    => $error,
				'url'           => $this->sanitizeURL( $url )
			];
			// Remove each of the individual handles
			curl_multi_remove_handle( $multicurl_resource, $curl_instances[$id] );
			$deadlinks[$url] = $this->processCurlResults( $curlInfo, true );
		}
		// Close resource
		curl_multi_close( $multicurl_resource );

		return $deadlinks;
	}

	/**
	 * Get CURL options
	 *
	 * @param $url String URL we are testing against
	 * @param bool $full Is this a request for the full page?
	 *
	 * @return array Options for curl
	 */
	protected function getCurlOptions( $url, $full = false ) {
		$header = [
			// @codingStandardsIgnoreStart Line exceeds 100 characters
			'Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5',
			// @codingStandardsIgnoreEnd
			'Cache-Control: max-age=0',
			'Connection: keep-alive',
			'Keep-Alive: 300',
			'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
			'Accept-Language: en-us,en;q=0.5',
			'Pragma: '
		];
		$options = [
			CURLOPT_URL            => $url,
			CURLOPT_HEADER         => 1,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_AUTOREFERER    => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_USERAGENT      => $this->userAgent,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_COOKIEJAR      => sys_get_temp_dir() . "checkifdead.cookies.dat"
		];

		$requestType = $this->getRequestType( $url );
		if ( $requestType == 'FTP' ) {
			$options[CURLOPT_FTP_USE_EPRT] = 1;
			$options[CURLOPT_FTP_USE_EPSV] = 1;
			$options[CURLOPT_FTPSSLAUTH] = CURLFTPAUTH_DEFAULT;
			$options[CURLOPT_FTP_FILEMETHOD] = CURLFTPMETHOD_SINGLECWD;
		}
		if ( $full ) {
			// Set CURLOPT_USERPWD for anonymous FTP login
			$options[CURLOPT_USERPWD] = "anonymous:anonymous@domain.com";
			// Extend timeout since we are requesting the full body
			$options[CURLOPT_TIMEOUT] = 60;
			$options[CURLOPT_HTTPHEADER] = $header;
			$options[CURLOPT_ENCODING] = '';
		} else {
			$options[CURLOPT_NOBODY] = 1;
		}

		return $options;
	}

	/**
	 * Get request type
	 *
	 * @param $url String URL we are checking against
	 *
	 * @return string "FTP" or "HTTP"
	 */
	protected function getRequestType( $url ) {
		if ( strtolower( parse_url( $url, PHP_URL_SCHEME ) ) == "ftp" ) {
			return "FTP";
		} else {
			return "HTTP";
		}
	}

	/**
	 * Process the returned headers
	 *
	 * @param array $curlInfo Array with values: returned headers, error number, URL checked for
	 * @param bool $full Was this a request for the full page?
	 *
	 * @return bool|null Returns true if dead, false if alive, null if uncertain
	 */
	protected function processCurlResults( $curlInfo, $full = false ) {
		// Determine if we are using FTP or HTTP
		$requestType = $this->getRequestType( $curlInfo['url'] );
		// Get HTTP code returned
		$httpCode = $curlInfo['http_code'];
		// Get final URL
		$effectiveUrl = $curlInfo['effective_url'];
		// Clean final url, removing scheme, 'www', and trailing slash
		$effectiveUrlClean = $this->cleanURL( $effectiveUrl );
		// Get an array of possible root urls
		$possibleRoots = $this->getDomainRoots( $curlInfo['url'] );
		if ( $httpCode >= 400 && $httpCode < 600 ) {
			if ( $full ) {
				return true;
			} else {
				// Some servers don't support NOBODY requests, so if an HTTP error code
				// is returned, we'll check the URL again with a full page request.
				return null;
			}
		}
		// Check for error messages in redirected URL string
		if ( strpos( $effectiveUrlClean, '404.htm' ) !== false ||
		     strpos( $effectiveUrlClean, '/404/' ) !== false ||
		     stripos( $effectiveUrlClean, 'notfound' ) !== false
		) {
			return true;
		}
		// Check if there was a redirect by comparing final URL with original URL
		if ( $effectiveUrlClean != $this->cleanURL( $curlInfo['url'] ) ) {
			// Check against possible roots
			foreach ( $possibleRoots as $root ) {
				// We found a match with final url and a possible root url
				if ( $root == $effectiveUrlClean ) {
					return true;
				}
			}
		}
		// If there was an error during the CURL process, check if the code
		// returned is a server side problem
		if ( in_array( $curlInfo['curl_error'], $this->curlErrorCodes ) ) {
			return true;
		}
		// Check for valid non-error codes for HTTP or FTP
		if ( $requestType == "HTTP" && !in_array( $httpCode, $this->goodHttpCodes ) ) {
			return true;
			// Check for valid non-error codes for FTP
		} elseif ( $requestType == "FTP" && !in_array( $httpCode, $this->goodFtpCodes ) ) {
			return true;
		}

		// Yay, the checks passed, and the site is alive.
		return false;
	}

	/**
	 * Compile an array of "possible" root URLs. With subdomain, without subdomain etc.
	 *
	 * @param string $url Initial url
	 *
	 * @return array Possible root domains (strings)
	 */
	protected function getDomainRoots( $url ) {
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
	 * Properly encode the URL to ensure the receiving webservice understands the request.
	 *
	 * @param $url URL to sanitize
	 *
	 * @return string sanitized URL
	 */
	public function sanitizeURL( $url ) {
		// The domain is easily decoded by the DNS handler,
		// but the path is what's seen by the respective webservice.
		// We need to encode it as some
		// can't handle decoded characters.

		// Break up the URL first
		$parts = $this->parseURL( $url );

		// In case the protocol is missing, assume it goes to HTTPS
		if ( !isset( $parts['scheme'] ) ) {
			$url = "https";
		} else {
			$url = $parts['scheme'];
		}
		// Move on to the domain
		$url .= "://";
		// Add username and password if present
		if ( isset( $parts['user'] ) ) {
			$url .= $parts['user'];
			if ( isset( $parts['pass'] ) ) {
				$url .= ":" . $parts['pass'];
			}
			$url .= "@";
		}
		// Add host
		if ( isset( $parts['host'] ) ) {
			// Properly encode the host.  It can't be UTF-8.
			// See https://en.wikipedia.org/wiki/Internationalized_domain_name.
			if ( function_exists( 'idn_to_ascii' ) ) {
				$url .= idn_to_ascii( $parts['host'] );
			} else {
				$url .= $parts['host'];
			}
			if ( isset( $parts['port'] ) ) {
				$url .= ":" . $parts['port'];
			}
		}
		// Make sure path, query, and fragment are properly encoded, and not overencoded.
		// This avoids possible 400 Bad Response errors.
		$url .= "/";
		if ( isset( $parts['path'] ) && strlen( $parts['path'] ) > 1 ) {
			$url .= implode( '/',
			                 array_map( "rawurlencode",
			                            explode( '/',
			                                     substr(
				                                     urldecode( $parts['path'] ), 1
			                                     )
			                            )
			                 )
			);
		}
		if ( isset( $parts['query'] ) ) {
			// We have a query string, all queries start with a ?
			$url .= "?";
			// Break apart the query string.  Separate them into all of the arguments passed.
			$parts['query'] = explode( '&', $parts['query'] );
			// We need to encode each argument
			foreach ( $parts['query'] as $index => $argument ) {
				// Make sure we don't inadvertently encode the first instance of "="
				// Otherwise we break the query.
				$parts['query'][$index] = implode( '=',
				                                   array_map( "urlencode",
				                                              explode( '=', $parts['query'][$index], 2 )
				                                   )
				);
			}
			// Put the query string back together.
			$parts['query'] = implode( '&', $parts['query'] );
			$url .= $parts['query'];
		}
		if ( isset( $parts['fragment'] ) ) {
			// We don't need to encode the fragment, that's handled client side anyways.
			$url .= "#" . $parts['fragment'];
		}

		return $url;
	}

	/**
	 * Custom parse_url function to support UTF-8 URLs
	 *
	 * @param string $url The URL to parse
	 *
	 * @return mixed False on failure, array on success. For example:
	 *     array( 'scheme' => 'https', 'host' => 'hello.com', 'path' => '/en/' ) )
	 */
	public function parseURL( $url ) {
		$encodedUrl = preg_replace_callback(
			'%[^:/@?&=#]+%usD',
			function ( $matches ) {
				return urlencode( $matches[0] );
			},
			$url
		);

		$parts = parse_url( $encodedUrl );
		foreach ( $parts as $name => $value ) {
			$parts[$name] = urldecode( $value );
		}

		return $parts;
	}

	/**
	 * Remove scheme, 'www', URL fragment, leading forward slashes and trailing slash
	 *
	 * @param string $input
	 *
	 * @return string Cleaned url string
	 */
	public function cleanURL( $input ) {
		// scheme and www
		$url = preg_replace( '/^((https?:|ftp:)?(\/\/))?(www\.)?/', '', $input );
		// fragment
		$url = preg_replace( '/#.*/', '', $url );
		// trailing slash
		$url = preg_replace( '{/$}', '', $url );

		return $url;
	}
}
