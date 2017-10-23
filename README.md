[![Build Status](https://travis-ci.org/wikimedia/DeadlinkChecker.svg?branch=master)](https://travis-ci.org/wikimedia/DeadlinkChecker)
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


### Usage
Code to determine if a given link on the web is dead or alive.

Sample usage:

##### For checking a single link:

```
$obj = new checkIfDead();
$url = 'https://en.wikipedia.org';
$exec = $obj->isLinkDead( $url );
echo var_export( $exec );
```
Prints:
```
false
```
##### For checking an array of links:
```
$obj = new checkIfDead();
$urls = [ 'https://en.wikipedia.org/nothing', 'https://en.wikipedia.org' ];
$exec = $obj->areLinksDead( $urls );
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

### License
This code is distributed under [GNU GPLv3+](https://www.gnu.org/copyleft/gpl.html)
