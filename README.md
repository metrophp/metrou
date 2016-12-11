Metro User Library

Sample Config
```php

_didef('user',           'metrou/user.php');
_didef('session',        'metrou/sessiondb.php');

_didef('authorizer', 'metrou/authorizer.php',
   	array('metrou', '/login', '/dologin', '/logout', '/dologout', '/register')
);
$authorizer = _make('authorizer');
_connect('authorize',            $authorizer);
_connect('authenticate',        'metrou/authenticator.php');

//events
_connect('access.denied',        'metrou/login.php::accessDenied');
_connect('authenticate.success', 'metrou/login.php::authSuccess');
_connect('authenticate.failure', 'metrou/login.php::authFailure');

```
