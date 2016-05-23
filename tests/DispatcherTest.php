<?php

class DispatcherTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var array
     */
    public static $data;

    /**
     * @var \Buuum\Router
     */
    public static $router;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    public static function setUpBeforeClass()
    {
        $router = new \Buuum\Router();

        $router->filter('notauth', function () {
            return 'not auth';
        });

        $router->filter('auth', function () {
            // CHECK AUTH //
        });

        $router->filter('params', function ($id, $_requesturi) {
            // CHECK AUTH //
            return "filter params $id $_requesturi[scheme]";
        });

        $router->get('/', function () {
            return "controller index";
        })->setName('index');

        $router->any('/any/', function () {
            return "controller any";
        })->setName('any');

        $router->map(['GET', 'POST'], '/map/', function () {
            return "controller map";
        });

        $router->get(['/group/', '/en/group/', '/fr/group/'], function () {
            return "controller group";
        });

        $router->put('/put/', function () {
            return "controller put";
        });

        $router->post('/', function () {
            return 'controller index post';
        });

        $router->get('/items/', function () {
            return 'controller items';
        })->setName('items');

        $router->get('/itemsr/', function ($_requesturi) {
            return "controller itemsr $_requesturi[scheme] $_requesturi[host]";
        });

        $router->group(['prefix' => 'prefixitem'], function (\Buuum\Router $router) {
            $router->get('/itemsr2/', function ($_requesturi, $_prefix) {
                return "controller itemsr2 $_requesturi[scheme] $_requesturi[host] $_prefix";
            });
        });

        $router->get('/itemsr/', function ($_requesturi) {
            return "controller itemsr $_requesturi[scheme] $_requesturi[host]";
        })->setHost('routes2.dev');

        $router->get('/items2', function () {
            return 'controller items';
        })->setName('items2');

        $router->get('/itemsview', function () {
            return 'controller itemsview';
        });

        $router->get('/item/{id:[0-9]+}', function ($id) {
            return 'controller item ' . $id;
        });

        $router->get('/viewitem/{id:[0-9]+}/', function ($id) {
            return 'view item ' . $id;
        })->setName('viewitem')->setScheme('https');


        $router->group(['before' => ['params']], function (\Buuum\Router $router) {
            $router->get('/filterparams/{id:[0-9]+}/', function ($id) {
                return "controller filter params $id";
            });
        });

        $router->group(['before' => ['auth']], function (\Buuum\Router $router) {
            $router->get('/before/', function () {
                return 'controller index with before filter';
            });
        });

        $router->group(['before' => ['notauth']], function (\Buuum\Router $router) {
            $router->get('/before/not/', function () {
                return 'controller index with before filter';
            });
        });


        $router->group(['after' => ['auth']], function (\Buuum\Router $router) {
            $router->get('/after/', function () {
                return 'controller index with after filter';
            });
        });

        $router->group(['after' => ['notauth']], function (\Buuum\Router $router) {
            $router->get('/after/not/', function () {
                return 'controller index with after filter';
            });
        });

        $router->group(['prefix' => 'name-prefix'], function (\Buuum\Router $router) {
            $router->get('/', function () {
                return 'controller prefix name-prefix';
            });

            $router->get('/', function () {
                return 'controller https prefix name-prefix';
            })->setScheme('https');

            $router->get('/', function () {
                return 'controller routes2 prefix name-prefix';
            })->setHost('routes2.dev');

            $router->get('/route1', function () {
                return 'controller prefix name-prefix route1';
            });

            $router->group(['prefix' => 'sub-prefix'], function (\Buuum\Router $router) {
                $router->get('/', function () {
                    return 'controller prefix name-prefix/sub-prefix';
                });

                $router->get('/route1/', function () {
                    return 'controller prefix name-prefix/sub-prefix route1';
                });
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

        });

        $router->get('/home/', function () {
            return 'home without prefix';
        })->setName('home');

        $router->get('/home/{id:[0-9]+}/', function ($id, $_requesturi, $_prefix) {
            return "home without prefix $id $_requesturi[scheme] $_requesturi[host] $_prefix";
        })->setScheme('https');


        self::$router = $router;
    }

    public function getResponses()
    {
        return [
            ['GET', 'http://routes.dev', 'controller index'],

            ['GET', 'http://routes.dev/group/', 'controller group'],
            ['GET', 'http://routes.dev/en/group/', 'controller group'],
            ['GET', 'http://routes.dev/fr/group/', 'controller group'],

            ['PUT', 'http://routes.dev/put/', 'controller put'],
            ['put', 'http://routes.dev/put/', 'controller put'],

            ['GET', 'http://routes.dev/map/', 'controller map'],
            ['POST', 'http://routes.dev/map/', 'controller map'],

            ['get', 'http://routes.dev/any/', 'controller any'],
            ['post', 'http://routes.dev/any/', 'controller any'],
            ['put', 'http://routes.dev/any/', 'controller any'],
            ['patch', 'http://routes.dev/any/', 'controller any'],
            ['options', 'http://routes.dev/any/', 'controller any'],
            ['delete', 'http://routes.dev/any/', 'controller any'],
            ['head', 'http://routes.dev/any/', 'controller any'],

            ['get', 'http://routes.dev', 'controller index'],
            ['POST', 'http://routes.dev', 'controller index post'],
            ['post', 'http://routes.dev', 'controller index post'],
            ['GET', 'http://routes.dev/items', 'controller items'],
            ['GET', 'http://routes.dev/items/', 'controller items'],

            // check _requesturi param
            ['GET', 'http://routes.dev/itemsr/', 'controller itemsr http routes.dev'],
            ['GET', 'http://routes2.dev/itemsr/', 'controller itemsr http routes2.dev'],
            ['GET', 'http://routes.dev/prefixitem/itemsr2/', 'controller itemsr2 http routes.dev prefixitem'],


            ['GET', 'http://routes.dev/itemsview', 'controller itemsview'],
            ['GET', 'http://routes.dev/itemsview/', 'controller itemsview'],
            ['GET', 'http://routes.dev/item/43', 'controller item 43'],
            ['GET', 'http://routes.dev/item/43/', 'controller item 43'],
            ['GET', 'http://routes.dev/name-prefix/', 'controller prefix name-prefix'],
            ['GET', 'https://routes.dev/name-prefix/', 'controller https prefix name-prefix'],
            ['GET', 'http://routes2.dev/name-prefix/', 'controller routes2 prefix name-prefix'],
            ['GET', 'http://routes.dev/name-prefix/route1/', 'controller prefix name-prefix route1'],
            ['GET', 'http://routes.dev/name-prefix/sub-prefix', 'controller prefix name-prefix/sub-prefix'],
            [
                'GET',
                'http://routes.dev/name-prefix/sub-prefix/route1/',
                'controller prefix name-prefix/sub-prefix route1'
            ],
            // filter before //
            ['GET', 'http://routes.dev/before', 'controller index with before filter'],
            ['GET', 'http://routes.dev/before/not/', 'not auth'],
            // filter after //
            ['GET', 'http://routes.dev/after', 'controller index with after filter'],
            ['GET', 'http://routes.dev/after/not/', 'controller index with after filter'],
            // filter params //
            ['GET', 'http://routes.dev/filterparams/43/', 'filter params 43 http'],
            // function params //
            ['GET', 'https://routes.dev/home/23/', 'home without prefix 23 https routes.dev ']
        ];
    }

    /**
     * @dataProvider getResponses
     */
    public function testResponses($method, $urlRequest, $responsetext)
    {
        $dispatcher = new \Buuum\Dispatcher(self::$router->getData());

        $response = $dispatcher->dispatchRequest($method, $urlRequest);
        $this->assertEquals($responsetext, $response);
    }

    /**
     * @expectedException \Buuum\Exception\HttpRouteNotFoundException
     */
    public function testExceptionHttpRouteNotFound()
    {
        $dispatcher = new \Buuum\Dispatcher(self::$router->getData());
        $dispatcher->dispatchRequest('POST', '/items/11');
    }

    /**
     * @expectedException \Buuum\Exception\HttpRouteNotFoundException
     */
    public function test2ExceptionHttpRouteNotFound()
    {
        $dispatcher = new \Buuum\Dispatcher(self::$router->getData());
        $dispatcher->dispatchRequest('GET', '/demo');
    }

    /**
     * @expectedException \Buuum\Exception\HttpMethodNotAllowedException
     */
    public function testHttpMethodNotAllowedException()
    {
        $dispatcher = new \Buuum\Dispatcher(self::$router->getData());
        $dispatcher->dispatchRequest('NOT', '/demo');
    }


    /**
     * @expectedException \Buuum\Exception\BadRouteException
     */
    public function testBadRouteException()
    {
        $dispatcher = new \Buuum\Dispatcher(self::$router->getData());
        $dispatcher->getUrlRequest('viewitem', ['id' => 're'], 'http://routes.dev');
    }

    /**
     * @dataProvider getUrls
     */
    public function test2DynamicUrls($name, $params, $requesturi, $result)
    {
        $dispatcher = new \Buuum\Dispatcher(self::$router->getData());

        $url = $dispatcher->getUrlRequest($name, $params, $requesturi);
        $this->assertEquals($result, $url);
    }

    public function getUrls()
    {
        return [
            ['index', [], 'http://routes.dev', 'http://routes.dev/'],
            ['items2', [], 'http://routes.dev', 'http://routes.dev/items2'],
            ['items', [], 'http://routes.dev', 'http://routes.dev/items/'],
            ['viewitem', ['id' => 65], 'http://routes.dev', 'https://routes.dev/viewitem/65/'],
            ['home', [], 'http://routes.dev/en', 'http://routes.dev/en/'],
            ['home', [], 'http://routes.dev/en/demo', 'http://routes.dev/en/'],
            ['home', [], 'http://routes.dev/es', 'http://routes.dev/es/'],
            ['home', [], 'http://routes.dev/', 'http://routes.dev/home/'],
        ];
    }

    /**
     * @dataProvider getUrls2
     */
    public function test2DynamicUrlsWithEndSlash($name, $params, $requesturi, $result)
    {
        $dispatcher = new \Buuum\Dispatcher(self::$router->getData());

        $url = $dispatcher->getUrlRequest($name, $params, $requesturi);
        $this->assertEquals($result, $url);
    }

    public function getUrls2()
    {
        return [
            ['index', [], 'http://routes.dev', 'http://routes.dev/'],
            ['items2', [], 'http://routes.dev', 'http://routes.dev/items2'],
            ['items', [], 'http://routes.dev', 'http://routes.dev/items/'],
            ['viewitem', ['id' => 65], 'http://routes.dev', 'https://routes.dev/viewitem/65/'],
            ['home', [], 'http://routes.dev/en/', 'http://routes.dev/en/'],
            ['home', [], 'http://routes.dev/en/demo/', 'http://routes.dev/en/'],
            ['home', [], 'http://routes.dev/es/', 'http://routes.dev/es/'],
            ['home', [], 'http://routes.dev/', 'http://routes.dev/home/'],
        ];
    }
}