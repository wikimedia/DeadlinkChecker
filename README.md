# DeadlinkChecker

Maintainer: [Cyberpower678](https://github.com/Cyberpower678)

REQUIRES: PHP 7.3 or higher

This is a PHP library for detecting whether URLs on the internet are alive or dead via cURL. It includes the following features:
* Supports HTTP, HTTPS, FTP, MMS, and RTSP URLs
* Supports TOR
* Supports [internationalized domain names](https://en.wikipedia.org/wiki/Internationalized_domain_name)
* Correctly reports [soft 404s](https://en.wikipedia.org/wiki/HTTP_404#Soft_404_errors) as dead (in most cases)
* For optimized performance, it initially performs a header-only page request (CURLOPT_NOBODY). If that request fails, it then tries to do a normal full body page request.

<!--[![Build Status](https://travis-ci.org/wikimedia/DeadlinkChecker.svg?branch=master)](https://travis-ci.org/wikimedia/DeadlinkChecker)-->
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

### License
This code is distributed under [GNU GPLv3+](https://www.gnu.org/copyleft/gpl.html)
