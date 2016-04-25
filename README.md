Buuum - Fast request router for PHP
=======================================

[![Build Status](https://api.travis-ci.org/alfonsmartinez/Buuum.png)](http://travis-ci.org/alfonsmartinez/Buuum)
[![Packagist](https://img.shields.io/packagist/v/Buuum/Buuum.svg?maxAge=2592000)](https://packagist.org/packages/Buuum/Buuum)
[![license](https://img.shields.io/github/license/mashape/apistatus.svg?maxAge=2592000)](#license)

## Simple and extremely flexible PHP router class, with support for route parameters, restful, filters and reverse routing.

## Getting started

You need PHP >= 5.5 to use Buuum.

- [Install Buuum Route](#install)
- [Rewrite all requests to Route](#rewrite-requests)
- [Map your routes](#map-your-routes)
- [Match requests](#match-requests)

## Install

### System Requirements

You need PHP >= 5.5.0 to use Buuum\Buuum but the latest stable version of PHP is recommended.

### Composer

Buuum is available on Packagist and can be installed using Composer:

```
composer require buuum/route
```

### Manually

You may use your own autoloader as long as it follows PSR-0 or PSR-4 standards. Just put src directory contents in your vendor directory.


## Rewrite requests

To use Buuum, you will need to rewrite all requests to a single file.
There are various ways to go about this, but here are examples for Apache and Nginx.

### Apache .htaccess

```htaccess
Options +FollowSymLinks
RewriteEngine On
RewriteRule ^(.*)$ index.php [NC,L]
```

### Nginx nginx.conf

```nginx
server {
    listen 80;
    server_name mydevsite.dev;
    root /var/www/mydevsite/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        # NOTE: You should have "cgi.fix_pathinfo = 0;" in php.ini

        # With php5-fpm:
        fastcgi_pass unix:/var/run/php5-fpm.sock;
        fastcgi_index index.php;
        include fastcgi.conf;
        fastcgi_intercept_errors on;
    }
}
```

## Map your routes

```php

use Buuum\Router;

$router = new Router('/examples');

$router->filter('auth', function ($_requesturi) {
    //return 'hola auth';
    var_dump($_requesturi);
});

$router->get('/', function () {
    return 'hello world';
});

$router->get('/part/', function () {
    return 'hello world part';
})->setName('part');

$router->get('/part/{id:[0-9]+}/', function ($id) {
    return 'hello world part' . $id;
})->setName('partid');

$router->group(['before' => ['auth']], function (\Buuum\Router $router) {
    $router->get('/testbefore/', function () {
        return 'hello test before';
    });
});

$router->group(['prefix' => 'en'], function (\Buuum\Router $router) {

    $router->get('/', function () {
        return 'home with prefix en';
    })->setName('home');

});

$router->group(['prefix' => 'es'], function (\Buuum\Router $router) {

    $router->get('/', function () {
        return 'home with prefix es';
    })->setName('home');

    $router->group(['prefix' => 'admin'], function (Router $router) {
        $router->get('/', function () {
            return 'hola 2 prefix';
        })->setName('prefix2');
    });

});

$router->get('/home/', function () {
    return 'home without prefix';
})->setName('home');

```


## Match requests

```php

$data = $router->getData();
$dispatcher = new \Buuum\Dispatcher($data);

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();

try {
    echo $dispatcher->dispatchRequest($request->getMethod(), $request->getUri());
} catch (\Buuum\Exception\HttpRouteNotFoundException $e) {
    echo 'route not found';
} catch (\Buuum\Exception\HttpMethodNotAllowedException $e) {
    echo 'not method allowed';
} catch (\Exception $e) {
    echo 'error 500';
}

```

### Dispatch url

```php

$data = $router->getData();
$dispatcher = new \Buuum\Dispatcher($data);

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();

echo $dispatcher->getUrlRequest('home', [], $request->getUri());
echo "\n";
echo $dispatcher->getUrlRequest('prefix2', [], $request->getUri());
echo "\n";
echo $dispatcher->getUrlRequest('partid', ['id' => 555], $request->getUri();
echo "\n";
```

## TODO

    - add requestinterface optional (guzzle / psr7)
    - default pregmatches
    - add cache route collection
    - add cache dispatcher
    
## LICENSE

The MIT License (MIT)

Copyright (c) 2016 alfonsmartinez

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.