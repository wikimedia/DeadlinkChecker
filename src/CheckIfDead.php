<?php
/**
 * Copyright (c) 2016, Niharika Kohli
 *
 * @license https://www.gnu.org/licenses/gpl.txt
 */

namespace Wikimedia\DeadlinkChecker;

define( 'CHECKIFDEADVERSION', '1.8.1' );

class CheckIfDead {

	/**
	 * Curl timeout for header-only page requests (CURLOPT_NOBODY), in seconds
	 */
	protected $curlTimeoutNoBody;

	/**
	 * Curl timeout for full page requests, in seconds
	 */
	protected $curlTimeoutFull;

	/**
	 * Curl queue for delaying requests going to the same domain.
	 */
	protected $curlQueue;

	/**
	 * UserAgent for the device/browser we are pretending to be
	 */
	// @codingStandardsIgnoreStart Line exceeds 100 characters
	protected $userAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36";

	// @codingStandardsIgnoreEnd

	/**
	 * User defined UserAgent
	 */
	protected $customUserAgent = false;

	/**
	 * UserAgent for the media player we are pretending to be
	 */
	protected $mediaAgent = "Windows-Media-Player/12.0.15063.608";

	/**
	 *  HTTP/RTSP/MMS codes that do not indicate a dead link
	 */
	protected $goodHttpCodes = [
		100, 101, 102, 103,
		200, 201, 202, 203, 204, 205, 206, 207, 208, 226,
		250, 300, 301, 302, 303, 304, 305, 306, 307, 308,
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
	 * Collection of errors encountered that resulted in the URL coming back
	 * dead, indexed by URL
	 */
	protected $errors = [];

	/**
	 * Contains query details of URLs being tested
	 */
	protected $details = [];

	/**
	 * Whether or not to turn queuing on or off
	 */
	protected $queuedTesting = true;

	/**
	 * Whether or not to generate verbose output
	 */
	protected $verbose = false;

	/**
	 * The host to connect to when attempting a TOR connection
	 */
	protected static $socks5Host = "127.0.0.1";

	/**
	 * The port to connect to when attempting a TOR connection
	 */
	protected static $socks5Port = 9050;

	/**
	 * This is a flag that indicates whether the OS environment is configured to use TOR
	 */
	protected static $torEnabled = null;

	/**
	 * Set up the class instance
	 *
	 * @param int $curlTimeoutNoBody Curl timeout for header-only page requests, in seconds
	 * @param int $curlTimeoutFull Curl timeout for full page requests, in seconds
	 * @param string $userAgent A custom user agent to pass in web requests
	 * @param bool $sequentialTests Delay queries on URLs sharing the same domain to avoid blacklisting
	 * @param bool $verbose Generate verbose output
	 */
	public function __construct(
		$curlTimeoutNoBody = 30,
		$curlTimeoutFull = 60,
		$userAgent = false,
		$sequentialTests = true,
		$verbose = false,
		$socks5Host = '127.0.0.1',
		$socks5Port = false
	) {
		$this->curlTimeoutNoBody = (int)$curlTimeoutNoBody;
		$this->curlTimeoutFull = (int)$curlTimeoutFull;
		$this->customUserAgent = $userAgent;
		$this->queuedTesting = (bool)$sequentialTests;
		$this->verbose = (bool)$verbose;

		if ( is_null( self::$torEnabled ) ) {
			// Check to see if we have an environment that supports TOR
			if ( $this->verbose ) {
				echo "Testing for TOR readiness...";
			}

			self::$socks5Host = $socks5Host;
			if ( $socks5Port === false ) {
				// If we are using TOR defaults, check OS to determine which defaults to use.
				if ( substr( php_uname(), 0, 7 ) == "Windows" ) {
					self::$socks5Port = 9150;
				} else {
					self::$socks5Port = 9050;
				}
			} else {
				self::$socks5Port = $socks5Port;
			}

			$testURL = "https://check.torproject.org";

			// Prepare test
			$ch = curl_init();
			// Get appropriate curl options

			$options = $this->getCurlOptions(
				$this->sanitizeURL( $testURL ),
				true,
				true
			);
			// Force Tor settings onto the options
			$options[CURLOPT_PROXY] = self::$socks5Host . ":" . self::$socks5Port;
			$options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5_HOSTNAME;
			$options[CURLOPT_HTTPPROXYTUNNEL] = true;
			curl_setopt_array(
				$ch,
				$options
			);

			$data = curl_exec( $ch );

			if ( strpos( $data, "This browser is configured to use Tor." ) !== false ) {
				self::$torEnabled = true;
			} else {
				self::$torEnabled = false;
			}

			curl_close( $ch );

			if ( $this->verbose ) {
				if ( self::$torEnabled ) {
					echo "Ready\n";
					echo "TOR requests can be made in this environment\n";
				} else {
					echo "Not ready\n";
					echo "TOR requests will be ignored\n";
				}
			}
		}

	}

	/**
	 * Check if a single URL is dead by performing a curl request
	 *
	 * @param string $url URL to check
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
	 * @return array|null Returns null if curl is unable to initialize.
	 *     Otherwise returns an array in which each key is a URL and each value is
	 *     true (dead) or false (alive).
	 */
	public function areLinksDead( $urls ) {
		// Create multiple curl handle
		$multicurl_resource = curl_multi_init();
		if ( $multicurl_resource === false ) {
			return null;
		}
		$deadLinks = [];
		$this->queueRequests( $urls );
		if ( $this->verbose === true ) {
			if ( count( $this->curlQueue ) > 1 ) {
				echo "Delaying one or more links!\n";
			}
		}
		foreach ( $this->curlQueue as $urls ) {
			$curl_instances = [];
			// Array of URLs we want to send in for a full check
			$fullCheckURLs = [];
			// Maps the destination URL to the requested URL in case we followed a redirect
			$fullCheckURLMap = [];

			foreach ( $urls as $id => $url ) {
				if ( $this->getRequestType( $this->sanitizeURL( $url ) ) != "UNSUPPORTED" ) {
					$curl_instances[$id] = curl_init();
					if ( $curl_instances[$id] === false ) {
						return null;
					}
					// Get appropriate curl options
					curl_setopt_array(
						$curl_instances[$id],
						$this->getCurlOptions( $this->sanitizeURL( $url ), false, $this->isOnion( $url ) )
					);
					// Add the instance handle
					curl_multi_add_handle( $multicurl_resource, $curl_instances[$id] );
				} elseif ( $this->verbose === true ) {
					echo "$url is not supported!\n";
				}
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
				if ( isset( $curl_instances[$id] ) ) {
					$this->details[$url] = $headers = curl_getinfo( $curl_instances[$id] );
					$error = curl_errno( $curl_instances[$id] );
					$errormsg = curl_error( $curl_instances[$id] );
					$curlInfo = [
						'http_code' => $headers['http_code'],
						'effective_url' => $headers['url'],
						'curl_error' => $error,
						'curl_error_msg' => $errormsg,
						'url' => $this->sanitizeURL( $url ),
						'rawurl' => $url
					];
					// Remove each of the individual handles
					curl_multi_remove_handle( $multicurl_resource, $curl_instances[$id] );
					$curl_instances[$id] = null;
					// Deduce whether the site is dead or alive
					$deadLinks[$url] = $this->processCurlResults( $curlInfo, false );
					if ( $this->verbose === true ) {
						if ( $deadLinks[$url] === true ) {
							echo "$url is DEAD!\n";
						}
						if ( $deadLinks[$url] === false ) {
							echo "$url is ALIVE!\n";
						}
					}
					// If we got back a null, we should do a full page request
					// We need to use the destination URL as CURL does not pass thru
					// headers when following redirects.  This causes some false positives.
					if ( is_null( $deadLinks[$url] ) ) {
						$fullCheckURLs[] = $headers['url'];
						if ( $url != $headers['url'] ) {
							$fullCheckURLMap[$url] = $headers['url'];
						}
					}
				} else {
					$deadLinks[$url] = null;
					if ( $this->verbose === true ) {
						echo "Something went wrong with $url!\n";
					}
				}
			}
			// Do full page requests for URLs that returned null
			if ( !empty( $fullCheckURLs ) ) {
				if ( $this->verbose === true ) {
					echo "Running a full check on:\n";
					foreach ( $fullCheckURLs as $url ) {
						echo "\t$url\n";
					}
				}
				$results = $this->performFullRequest( $fullCheckURLs );
				if ( $this->verbose === true ) {
					foreach ( $results as $url => $result ) {
						if ( $result === true ) {
							echo "$url is DEAD!\n";
						}
						if ( $result === false ) {
							echo "$url is ALIVE!\n";
						}
					}
				}
				// Merge back results from full requests into our deadlinks array
				$deadLinks = array_merge( $deadLinks, $results );

				// Use map to change destination URL back to the requested URL
				foreach ( $fullCheckURLMap as $requested=>$destination ) {
					$deadLinks[$requested] = $deadLinks[$destination];
					$this->details[$requested] = $this->details[$destination];
					if ( isset( $this->errors[$destination] ) ) {
						$this->errors[$requested] = $this->errors[$destination];
						unset( $this->errors[$destination] );
					}
					unset ( $deadLinks[$destination], $this->details[$destination] );
				}
			}
			if ( count( $this->curlQueue ) > 1 ) {
				sleep( 1 );
			}
		}
		// Close resource
		curl_multi_close( $multicurl_resource );

		return $deadLinks;
	}

	/**
	 * Perform a complete text request, not just for headers
	 *
	 * @param array $urls URLs we are checking
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
				$this->getCurlOptions(
					$this->sanitizeURL( $url, false, true ),
					true,
					$this->isOnion( $url )
				)
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
			$this->details[$url] = $headers = curl_getinfo( $curl_instances[$id] );
			$error = curl_errno( $curl_instances[$id] );
			$errormsg = curl_error( $curl_instances[$id] );
			$curlInfo = [
				'http_code' => $headers['http_code'],
				'effective_url' => $headers['url'],
				'curl_error' => $error,
				'curl_error_msg' => $errormsg,
				'url' => $this->sanitizeURL( $url, false, true ),
				'rawurl' => $url
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
	 * Queue up the URLs creating time-delays between URLs with the same domain.
	 *
	 * @param array $urls All the URLs being tested
	 */
	protected function queueRequests( $urls ) {
		$this->curlQueue = [];
		if ( $this->queuedTesting === false ) {
			$this->curlQueue[] = $urls;

			return;
		}
		foreach ( $urls as $url ) {
			$domain = $this->parseURL( $url )['host'];
			$queuedUrl = false;
			$queueIndex = -1;
			foreach ( $this->curlQueue as $queueIndex => $urlList ) {
				if ( $queuedUrl === false && !isset( $urlList[$domain] ) ) {
					$this->curlQueue[$queueIndex][$domain] = $url;
					$queuedUrl = true;
				}
			}
			if ( $queuedUrl === false ) {
				$this->curlQueue[++$queueIndex][$domain] = $url;
			}
		}
	}

	/**
	 * Get CURL options
	 *
	 * @param $url String URL we are testing against
	 * @param bool $full Is this a request for the full page?
	 * @param bool $tor Is this request being routed through TOR?
	 * @return array Options for curl
	 */
	protected function getCurlOptions( $url, $full = false, $tor = false ) {
		$requestType = $this->getRequestType( $url );
		if ( $requestType == "MMS" ) {
			$url = str_ireplace( "mms://", "rtsp://", $url );
		}
		$options = [
			CURLOPT_URL => $url,
			CURLOPT_HEADER => 1,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_AUTOREFERER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT => $this->curlTimeoutNoBody,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_COOKIEJAR => sys_get_temp_dir() . "checkifdead.cookies.dat"
		];
		if ( $requestType == "RTSP" || $requestType == "MMS" ) {
			$header = [];
			$options[CURLOPT_USERAGENT] = $this->mediaAgent;
		} else {
			// Emulate a web browser request but make it accept more than a web browser
			$header = [
				// @codingStandardsIgnoreStart Line exceeds 100 characters
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
				// @codingStandardsIgnoreEnd
				'Accept-Encoding: gzip, deflate, br',
				'Upgrade-Insecure-Requests: 1',
				'Cache-Control: max-age=0',
				'Connection: keep-alive',
				'Keep-Alive: 300',
				'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
				'Accept-Language: en-US,en;q=0.9,de;q=0.8',
				'Pragma: ',
				'sec-fetch-dest: document',
				'sec-fetch-mode: navigate',
				'sec-fetch-site: none',
				'sec-fetch-user: ?1'
			];
			if ( $this->customUserAgent === false ) {
				$options[CURLOPT_USERAGENT] = $this->userAgent;
			} else {
				$options[CURLOPT_USERAGENT] = $this->customUserAgent;
			}
		}
		if ( $requestType == 'FTP' ) {
			$options[CURLOPT_FTP_USE_EPRT] = 1;
			$options[CURLOPT_FTP_USE_EPSV] = 1;
			$options[CURLOPT_FTPSSLAUTH] = CURLFTPAUTH_DEFAULT;
			$options[CURLOPT_FTP_FILEMETHOD] = CURLFTPMETHOD_SINGLECWD;
			if ( $full ) {
				// Set CURLOPT_USERPWD for anonymous FTP login
				$options[CURLOPT_USERPWD] = "anonymous:anonymous@domain.com";
			}
		}
		if ( $full ) {
			// Extend timeout since we are requesting the full body
			$options[CURLOPT_TIMEOUT] = $this->curlTimeoutFull;
			$options[CURLOPT_HTTPHEADER] = $header;
			if ( $requestType != "MMS" && $requestType != "RTSP" ) {
				$options[CURLOPT_ENCODING] = 'gzip, deflate, br';
			}
			$options[CURLOPT_USERAGENT] = $this->userAgent;
		} else {
			$options[CURLOPT_NOBODY] = 1;
		}

		if ( $tor && self::$torEnabled ) {
			$options[CURLOPT_PROXY] = self::$socks5Host . ":" . self::$socks5Port;
			$options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5_HOSTNAME;
			$options[CURLOPT_HTTPPROXYTUNNEL] = true;

		} else {
			$options[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
		}

		return $options;
	}

	/**
	 * Get request type
	 *
	 * @param $url String URL we are checking against
	 * @return string "FTP", "MMS", "RTSP", "HTTP", or "UNSUPPORTED"
	 */
	protected function getRequestType( $url ) {
		if ( $this->isOnion( $url ) && !self::$torEnabled ) {
			return "UNSUPPORTED";
		}

		switch ( strtolower( parse_url( $url, PHP_URL_SCHEME ) ) ) {
			case "ftp":
				return "FTP";
			case "mms":
				return "MMS";
			case "rtsp":
				return "RTSP";
			case "http":
			case "https":
				return "HTTP";
			default:
				return "UNSUPPORTED";
		}
	}

	/**
	 * Check if TOR is needed to access url
	 *
	 * @param $url String URL we are checking against
	 * @return bool True if it's an Onion URL
	 */
	protected function isOnion( $url ) {
		$domain = strtolower( parse_url( $url, PHP_URL_HOST ) );

		if ( substr( $domain, -6 ) == ".onion" ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Process the returned headers
	 *
	 * @param array $curlInfo Array with values: returned headers, error number, URL checked for
	 * @param bool $full Was this a request for the full page?
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
				$this->errors[$curlInfo['rawurl']] = "RESPONSE CODE: $httpCode";

				return true;
			} else {
				// Some servers don't support NOBODY requests, so if an HTTP error code
				// is returned, we'll check the URL again with a full page request.
				return null;
			}
		}
		// Check for error messages in redirected URL string
		if ( strpos( $effectiveUrlClean, '/404.htm' ) !== false ||
			strpos( $effectiveUrlClean, '/404/' ) !== false ||
			stripos( $effectiveUrlClean, 'notfound' ) !== false
		) {
			if ( $full ) {
				$this->errors[$curlInfo['rawurl']] = "REDIRECT TO 404";

				return true;
			} else {
				// Some servers don't support NOBODY requests, so if redirect to a 404 page
				// is returned, we'll check the URL again with a full page request.
				return null;
			}
		}
		// Check if there was a redirect by comparing final URL with original URL
		if ( $effectiveUrlClean != $this->cleanURL( $curlInfo['url'] ) ) {
			// Check against possible roots
			foreach ( $possibleRoots as $root ) {
				// We found a match with final url and a possible root url
				if ( $root == $effectiveUrlClean ) {
					$this->errors[$curlInfo['rawurl']] = "REDIRECT TO ROOT";

					return true;
				}
			}
		}
		// If there was an error during the CURL process, check if the code
		// returned is a server side problem
		if ( in_array( $curlInfo['curl_error'], $this->curlErrorCodes ) ) {
			$this->errors[$curlInfo['rawurl']] =
				"Curl Error {$curlInfo['curl_error']}: {$curlInfo['curl_error_msg']}";

			return true;
		}
		if ( $httpCode === 0 ) {
			if ( $full ) {
				$this->errors[$curlInfo['rawurl']] = "NO RESPONSE FROM SERVER";

				return true;
			} else {
				// Some servers don't support NOBODY requests, so if redirect to a 404 page
				// is returned, we'll check the URL again with a full page request.
				return null;
			}
		}
		// Check for valid non-error codes for HTTP or FTP
		if ( $requestType != "FTP" && !in_array( $httpCode, $this->goodHttpCodes ) ) {
			$this->errors[$curlInfo['rawurl']] = "HTTP RESPONSE CODE: $httpCode";

			return true;
			// Check for valid non-error codes for FTP
		} elseif ( $requestType == "FTP" && !in_array( $httpCode, $this->goodFtpCodes ) ) {
			$this->errors[$curlInfo['rawurl']] = "FTP RESPONSE CODE: $httpCode";

			return true;
		}

		// Yay, the checks passed, and the site is alive.
		return false;
	}

	/**
	 * Compile an array of "possible" root URLs. With subdomain, without subdomain etc.
	 *
	 * @param string $url Initial url
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
		if ( preg_match( '/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z.]{2,6})$/i', $domain, $regs ) ) {
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
	 * @param string $url URL to sanitize
	 * @param bool $stripFragment Remove the fragment from the URL.
	 * @param bool $preserveQueryEncoding Preserve the whitespace encoding of query strings.
	 * @return string sanitized URL
	 */
	public function sanitizeURL( $url, $stripFragment = false, $preserveQueryEncoding = false ) {
		// The domain is easily decoded by the DNS handler,
		// but the path is what's seen by the respective webservice.
		// We need to encode it as some
		// can't handle decoded characters.
		// Break up the URL first
		$parts = $this->parseURL( $url );
		// Some rare URLs don't like it when %20 is passed in the query and require the +.
		// %20 is the most common usage to represent a whitespace in the query.
		// So convert them to unique values that will survive the encoding/decoding process.
		if ( $preserveQueryEncoding === true && isset( $parts['query'] ) ) {
			$parts['query'] = str_replace( "%20", "CHECKIFDEADHEXSPACE", $parts['query'] );
		}
		// In case the protocol is missing, assume it goes to HTTPS
		if ( !isset( $parts['scheme'] ) ) {
			$url = "https";
		} else {
			$url = strtolower( $parts['scheme'] );
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
				$url .= strtolower( idn_to_ascii( $parts['host'], IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 ) );
			} else {
				$url .= strtolower( $parts['host'] );
			}
			if ( isset( $parts['port'] ) ) {
				// Ports are only needed if not using the defaults for the scheme.
				// Remove port numbers if they are the default.
				switch ( $parts['port'] ) {
					case 80:
						if ( isset( $parts['scheme'] ) &&
							strtolower( $parts['scheme'] ) == "http"
						) {
							break;
						}
					case 443:
						if ( !isset( $parts['scheme'] ) ||
							strtolower( $parts['scheme'] ) == "https"
						) {
							break;
						}
					case 21:
						if ( isset( $parts['scheme'] ) &&
							strtolower( $parts['scheme'] ) == "ftp"
						) {
							break;
						}
					case 554:
						if ( isset( $parts['scheme'] ) &&
							strtolower( $parts['scheme'] ) == "rtsp"
						) {
							break;
						}
					default:
						$url .= ":" . $parts['port'];
				}
			}
		}
		// Make sure path, query, and fragment are properly encoded, and not over-encoded.
		// This avoids possible 400 Bad Response errors.
		$url .= "/";
		if ( isset( $parts['path'] ) && strlen( $parts['path'] ) > 1 ) {
			// There are legal characters that do not need encoding in the path
			// and some webservers cannot handle these being encoded
			// If we only have legal characters, we can skip sanitizing the path
			$legalRegex = '/[^0-9a-zA-Z$\-\_\.\+\!\*\'\(\)\,\~\:\/\[\]\@\;\=\%]/';
			if ( preg_match( $legalRegex, $parts['path'] ) ) {
				// Pluses in the path are legal characters that do not need to be encoded.
				// Some URLs don't like the plus encoded.
				$parts['path'] = str_replace( "+", "CHECKIFDEADPLUSSPACE", $parts['path'] );
				$url .= implode( '/',
					array_map( "rawurlencode",
						explode( '/',
							substr(
								rawurldecode( $parts['path'] ), 1
							)
						)
					)
				);
			} else {
				$url .= substr( $parts['path'], 1 );
			}
		}
		if ( isset( $parts['query'] ) ) {
			$url .= "?";
			// There are legal characters that do not need encoding in the query
			// and some webservers cannot handle these being encoded
			// If we only have legal characters, we can skip sanitizing the query
			$legalRegex = '/[^0-9a-zA-Z$\-\_\.\+\!\*\'\(\)\,\~\:\[\]\@\;\&\=\%]/';
			if ( preg_match( $legalRegex, $parts['query'] ) ) {
				// Encoding the + means a literal plus in the query.
				// A plus means a space otherwise.
				$parts['query'] = str_replace( "+", "CHECKIFDEADPLUSSPACE", $parts['query'] );
				// We have a query string, all queries start with a ?
				// Break apart the query string.  Separate them into all of the arguments passed.
				$parts['query'] = explode( '&', $parts['query'] );
				// We need to encode each argument
				foreach ( $parts['query'] as $index => $argument ) {
					// Make sure we don't inadvertently encode the first instance of "="
					// Otherwise we break the query.
					$parts['query'][$index] = implode( '=',
						array_map( "rawurlencode",
							array_map( "urldecode",
								explode( '=', $parts['query'][$index], 2 )
							)
						)
					);
				}
				// Put the query string back together.
				$parts['query'] = implode( '&', $parts['query'] );
				$url .= $parts['query'];
			} else {
				$url .= $parts['query'];
			}
		}
		if ( $stripFragment === false && isset( $parts['fragment'] ) ) {
			// We don't need to encode the fragment, that's handled client side anyways.
			$url .= "#" . $parts['fragment'];
		}
		$url = str_replace( "CHECKIFDEADPLUSSPACE", "+", $url );
		// Convert our identifiers back into URL elements.
		if ( $preserveQueryEncoding === true ) {
			$url = str_replace( "CHECKIFDEADHEXSPACE", "%20", $url );
		}

		return $url;
	}

	/**
	 * Custom parse_url function to support UTF-8 URLs
	 *
	 * @param string $url The URL to parse
	 * @return mixed False on failure, array on success. For example:
	 *     array( 'scheme' => 'https', 'host' => 'hello.com', 'path' => '/en/' ) )
	 */
	public function parseURL( $url ) {
		// Feeding fully encoded URLs will not work.  So let's detect and decode if needed first.
		// This is just idiot proofing.
		// See if the URL is fully encoded by checking if the :// is encoded.
		// This prevents URLs where double encoded values aren't mistakenly decoded breaking the URL.
		if ( preg_match( '/^([a-z0-9+\-.]*)(?:%3A%2F%2F|%3A\/\/|:%2F%2F)/i', $url ) ) {
			// First let's break the fragment out to prevent accidentally mistaking a decoded %23 as a #
			$fragment = parse_url( $url, PHP_URL_FRAGMENT );
			if ( !is_null( $fragment ) ) {
				$url = strstr( $url, "#", true );
			}
			// Decode URL
			$url = rawurldecode( $url );
			// Re-encode the remaining #'s
			$url = str_replace( "#", "%23", $url );
			// Reattach the fragment
			if ( !is_null( $fragment ) ) {
				$url .= "#$fragment";
			}
		}
		// Sometimes the scheme is followed by a single slash instead of a double.
		// Web browsers and archives support this, so we should too.
		if ( preg_match( '/^([a-z0-9+\-.]*:)?\/([^\/].+)/i', $url, $match ) ) {
			$url = $match[1] . "//" . $match[2];
		}
		// Sometimes protocol relative URLs are not formatted correctly
		// This checks to see if the URL starts with :/ or ://
		// We will assume http in these cases
		if ( preg_match( '/^:\/\/?([^\/].+)/i', $url, $match ) ) {
			$url = "http://" . $match[1];
		}
		// If we're missing the scheme and double slashes entirely, assume http.
		// The parse_url function fails without this
		if ( !preg_match( '/(?:[a-z0-9+\-.]*:)?\/\//i', $url ) ) {
			$url = "http://" . $url;
		}
		$encodedUrl = preg_replace_callback(
			'%[^:/@?&=#;]+%sD',
			function ( $matches ) {
				return urlencode( $matches[0] );
			},
			$url
		);
		$parts = parse_url( $encodedUrl );
		// Check if the URL was actually parsed.
		if ( $parts !== false ) {
			foreach ( $parts as $name => $value ) {
				$parts[$name] = urldecode( $value );
			}
		}

		return $parts;
	}

	/**
	 * Remove scheme, 'www', URL fragment, leading forward slashes and trailing slash
	 *
	 * @param string $input
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

	/**
	 * Returns the errors encountered on URLs that resulted in a dead response
	 *
	 * @return array All the errors indexed by URL.
	 */
	public function getErrors() {
		return $this->errors;
	}

	/**
	 * Returns all of the data collected during the curl request.
	 *
	 * @return array All curl statistics gathered during the request.
	 */
	public function getRequestDetails() {
		return $this->details;
	}

	/**
	 * Returns the status of TOR readiness
	 *
	 * @return bool False if the environment doesn't support TOR
	 */
	public static function isTorEnabled() {
		return self::$torEnabled;
	}
}
