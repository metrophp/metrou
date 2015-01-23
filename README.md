Metro User Library

Sample Config
```php
_iCanHandle('authenticate', 'metrou/authenticator.php');
_iCanHandle('authorize',    'metrou/authorizer.php::requireLogin');

//events
_iCanHandle('access.denied',        'metrou/login.php::accessDenied');
_iCanHandle('authenticate.success', 'metrou/login.php::authSuccess');
_iCanHandle('authenticate.failure', 'metrou/login.php::authFailure');

//things
_didef('user',           'metrou/user.php');
_didef('session',        'metrou/sessiondb.php');
```
