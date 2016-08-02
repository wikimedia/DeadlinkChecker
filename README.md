Code to determine if a given link on the web is dead or alive.

Sample usage:
```
$obj = new checkIfDead();
$url = 'https://en.wikipedia.org';
$exec = $obj->checkDeadlink( $url );
$result = $exec['result']; // true/false
$error = $exec['error'];   // Error code we got back from curl, if any
```

