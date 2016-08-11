### Installation
Using composer:
Add the following to your composer.json:
```
{
  "require": {
     "niharika29/deadlinkchecker": "dev-master"
  }
}
```
And then run 'composer update'.

Or using git:
```
$ git clone https://github.com/Niharika29/DeadlinkChecker.git
```


### Documentation
Code to determine if a given link on the web is dead or alive.

Sample usage:

##### For checking a single link:

```
$obj = new checkIfDead();
$url = 'https://en.wikipedia.org';
$exec = $obj->isLinkDead( $url );
print_r( $exec );
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
print_r( $exec );
```
Returns:
```
[
  'https://en.wikipedia.org/nothing' => false,
  'https://en.wikipedia.org' => true
]
```

### License
This code is distributed under [GNU GPLv3+](https://www.gnu.org/copyleft/gpl.html)
