#### Installation
Using composer:
```
{
  "require": {
     "Niharika29/DeadlinkChecker": "dev-master"
  }
}
```
Or using git:
```
$ git clone https://github.com/Niharika29/DeadlinkChecker.git
```


#### Documentation
Code to determine if a given link on the web is dead or alive.


Sample usage:

##### Example 1:
```
$obj = new checkIfDead();
$url = 'https://en.wikipedia.org';
$exec = $obj->isLinkDead( $url );
var_dump( $exec[$url] );
```
Prints:
```
false
```
##### Example 2:
```
$obj = new checkIfDead();
$urls = [ 'https://en.wikipedia.org/nothing', 'https://en.wikipedia.org' ]
$exec = $obj->areLinksDead( $urls );
var_dump( $exec )
```
Returns:
```
[
  'https://en.wikipedia.org/nothing' => false
  'https://en.wikipedia.org' => true
]
```

#### License
This code is distributed under [GNU GPLv3+](https://www.gnu.org/copyleft/gpl.html)
