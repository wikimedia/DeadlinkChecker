# DeadlinkChecker

Maintainer: [Cyberpower678](https://github.com/Cyberpower678)

REQUIRES: PHP 7.3 or higher

This is a PHP library for detecting whether URLs on the internet are alive or dead via cURL. It includes the following features:

* Supports HTTP, HTTPS, FTP, MMS, and RTSP URLs
* Supports TOR
* Supports [internationalized domain names](https://en.wikipedia.org/wiki/Internationalized_domain_name)
* Basic detection for [soft 404s](https://en.wikipedia.org/wiki/HTTP_404#Soft_404_errors)
* For optimized performance, it initially performs a header-only page request (CURLOPT_NOBODY). If that request fails, it then tries to do a normal full body page request.
* Concurrently checks batch of URLs for efficiency

<!--![Build Status](https://travis-ci.org/wikimedia/DeadlinkChecker.svg?branch=master)\-->

### Overview

The checkIfDead library is a PHP library designed for assessing the status of URLs on the web and dark web.  It operates by taking one or more URLs as inputs and concurrently checks them, to enhance response times.

It can handle both properly and improperly formatted URLs and performs basic sanity checking and error correction on malformed inputs.  All inputs are normalized through the sanitizer to ensure the curl library communicates properly with the target.

When left at defaults, the library will emulate a web browser request and follow redirects to its destination.

### Installation

Using composer:
Add the following to the composer.json file for your project:

```
{
  "require": {
     "wikimedia/deadlinkchecker": "dev-master"
  }
}
```

And then run 'composer update'.

Or using git:

```
$ git clone https://github.com/wikimedia/DeadlinkChecker.git
```

### Basic Usage

##### For checking a single link:

```
$deadLinkChecker = new checkIfDead();
$url = 'https://en.wikipedia.org';
$exec = $deadLinkChecker->isLinkDead( $url );
echo var_export( $exec );
```

Prints:

```
false
```

##### For checking an array of links:

```
$deadLinkChecker = new checkIfDead();
$urls = [ 'https://en.wikipedia.org/nothing', 'https://en.wikipedia.org' ];
$exec = $deadLinkChecker->areLinksDead( $urls );
echo var_export( $exec );
```

Prints:

```
array (
  'https://en.wikipedia.org/nothing' => true,
  'https://en.wikipedia.org' => false,
)
```

Note that these functions will return `null` if they are unable to determine whether a link is alive or dead.

### Advanced Usage

You can control how long it takes before page requests timeout by passing parameters to the constructor. To set the header-only page requests to a 10 second timeout and the full page requests to a 20 second timeout, you would use the following:

```
$deadLinkChecker = new checkIfDead( 10, 20 );
```

In addition to controlling query timeouts, a custom user agent can be passed to the library as well like so:

```angular2html
$deadLinkChecker = new checkIfDead( 10, 20, "Custom Agent" );
```

By default, multiple URLs of the same domain are queued sequentially to be respectul to the hosts.  However, this can be disabled so all URLs are queried concurrently as follows:

```angular2html
$deadLinkChecker = new checkIfDead( 10, 20, "Custom Agent", false );
```

You can increase the verbosity of the output to follow what the library is doing as it's doing it.

```angular2html
$deadLinkChecker = new checkIfDead( 10, 20, "Custom Agent", true, true );
```

Finally, because the library supports TOR requests, the environment will need a working SOCKS5 proxy to make the requests.  The library looks for the SOCKS5 proxy using system defaults, but the proxy can be specified manually.

```angular2html
$deadLinkChecker = new checkIfDead( 10, 20, "Custom Agent", true, false, "proxy.host", proxy_port );
```

### Getting details about the last batch of URLs checked

After a batch of URLs have been checked, you can use `$deadLinkChecker->getErrors()` to get the curl errors encountered during the process, and `$deadLinkChecker->getRequestDetails()` to get the curl request details of all URLs checked in the last batch.

### Other functions

To clean up dirty URLs and allow them to be normalized to correctly line with varying HTTP clients:

```angular2html
$deadLinkChecker->sanitizeURL( "https://example.com/", $stripFragment );
```

By default, $stripFragment is false.  When set to true, URL fragments are dropped.

Because PHP has a tendency to fail parsing URLs containing UTF-8 characters, you can use the library's parseURL method.

```angular2html
$deadLinkChecker->parseURL( $url );
```

### License

This code is distributed under [GNU GPLv3+](https://www.gnu.org/copyleft/gpl.html)