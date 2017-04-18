<?php

namespace Buuum;

use Buuum\Exception\BadRouteException;
use Buuum\Exception\HttpMethodNotAllowedException;
use Buuum\Exception\HttpMethodNotExistException;
use Buuum\Exception\HttpRouteNotFoundException;

class Dispatcher
{

    const ERRORNOTFOUND = 404;
    const ERRORMETHODNOTALLOWED = 405;
    const ERRORCLASSMETHODNOTFOUND = 406;

    /**
     * @var array
     */
    private $route_map = [];

    /**
     * @var mixed
     */
    private $request_url = null;

    /**
     * @var array
     */
    private $url_info = [
        'scheme' => '',
        'host'   => '',
        'path'   => '',
        'query'  => ''
    ];

    /**
     * @var array
     */
    private $last_route = [];

    /**
     * @var HandlerResolverInterface|null
     */
    private $resolver;

    /**
     * Dispatcher constructor.
     * @param array $data
     * @param HandlerResolverInterface|null $resolver
     */
    public function __construct(array $data, HandlerResolverInterface $resolver = null)
    {
        $this->route_map = $data;
        $this->resolver = $resolver;
    }

    /**
     * @param $httpMethod
     * @param $requestUrl
     * @return bool|mixed|null
     * @throws HttpMethodNotAllowedException
     * @throws HttpRouteNotFoundException
     */
    public function dispatchRequest($httpMethod, $requestUrl)
    {
        $requestUrl = $this->setUrlRequest($requestUrl);
        $httpMethod = $this->checkMethod($httpMethod);

        $routes = $this->getRoutes($httpMethod);

        foreach ($routes as $route) {
            $route_pattern = $route['regex'];
            if (preg_match($route_pattern, $requestUrl, $arguments)) {
                return $this->getResponse($route, $arguments);
            }
        }

        return $this->dispatchError($requestUrl);

    }

    /**
     * @param $requestUrl
     * @return array|mixed|null
     * @throws HttpRouteNotFoundException
     */
    public function dispatchError($requestUrl)
    {
        $requestUrl = $this->setUrlRequest($requestUrl);

        // check for error pages //
        foreach ($this->route_map['routes'][Route::ERROR] as $route) {
            $route_pattern = str_replace('?$@', '?@', $route['regex']);
            if (preg_match($route_pattern, $requestUrl, $arguments)) {
                return $this->getResponse($route, $arguments);
            }
        }

        throw new HttpRouteNotFoundException("Not route found");

    }

    /**
     * @param $name
     * @param $options
     * @param $requestUrl
     * @return mixed
     * @throws HttpRouteNotFoundException
     */
    public function getUrlRequest($name, $options = [], $requestUrl = null)
    {
        if ((is_null($requestUrl) && is_null($this->request_url)) || empty($this->route_map['reverse'][$name])) {
            throw new HttpRouteNotFoundException("Route $name not found");
        }

        $requestUrl = $this->setUrlRequest($requestUrl, false);

        if (count($this->route_map['reverse'][$name]) == 1) {

            if (in_array(Route::LINK, $this->route_map['reverse'][$name][0]['methods'])) {
                return $this->route_map['reverse'][$name][0]['uri'];
            }

            preg_match($this->route_map['reverse'][$name][0]['regex_pre'], $requestUrl, $arguments);
            $options = array_merge($options, $arguments);

            return $this->buildUrl($this->route_map['reverse'][$name][0], $options);

        } else {
            foreach ($this->route_map['reverse'][$name] as $item) {
                $route_pattern = $item['regex_pre'];
                if (preg_match($route_pattern, $requestUrl, $arguments)) {

                    if (in_array(Route::LINK, $item['methods'])) {
                        return $item['uri'];
                    }

                    $options = array_merge($options, $arguments);
                    return $this->buildUrl($item, $options);
                }
            }
        }

        throw new BadRouteException('Route doesnt exist');

    }

    /**
     * @return array|bool
     */
    public function getLastPage()
    {
        return (!empty($this->last_route)) ? $this->last_route : false;
    }

    /**
     * @param $controller
     * @param $parameters
     * @return mixed|null
     * @throws HttpMethodNotExistException
     */
    private function callFunction($controller, $parameters)
    {

        if (!is_array($controller) && is_callable($controller)) {
            $parameters = $this->arrangeFuncArgs($controller, $parameters);
            return call_user_func_array($controller, $parameters);
        }

        if (method_exists($class = $controller[0], $method = $controller[1])) {
            $parameters = $this->arrangeMethodArgs($class, $method, $parameters);
            $controller = [$class, $method];
            if ($this->resolver) {
                $controller = $this->resolver->resolve($controller);
            }

            return call_user_func_array($controller, $parameters);
        }

        throw new HttpMethodNotExistException("Not method $method exist in class $class");

    }

    /**
     * @param $function
     * @param $arguments
     * @return array
     */
    private function arrangeFuncArgs($function, $arguments)
    {
        $ref = new \ReflectionFunction($function);
        return $this->arrangeArgs($ref->getParameters(), $arguments);
    }

    /**
     * @param $class
     * @param $method
     * @param $arguments
     * @return array
     */
    private function arrangeMethodArgs($class, $method, $arguments)
    {
        $ref = new \ReflectionMethod($class, $method);
        return $this->arrangeArgs($ref->getParameters(), $arguments);
    }

    /**
     * @param $parameters
     * @param $arguments
     * @return array
     */
    private function arrangeArgs($parameters, $arguments)
    {
        return array_map(
            function (\ReflectionParameter $param) use ($arguments) {
                if (isset($arguments[$param->getName()])) {
                    return $arguments[$param->getName()];
                }
                if ($param->isOptional()) {
                    return $param->getDefaultValue();
                }
                return null;
            },
            $parameters
        );
    }

    /**
     * @param $url
     */
    private function parseUrl($url)
    {
        $this->url_info = array_merge($this->url_info, parse_url($url));
    }


    /**
     * @param $route
     * @param $options
     * @return mixed
     */
    private function buildUrl($route, $options)
    {
        $url = $this->showUrl($route['reverse'], $route['parameters'], $options);
        return $url;
    }

    /**
     * @param $url
     * @param $parameters
     * @param $options
     * @return mixed
     */
    private function showUrl($url, $parameters, $options)
    {

        preg_match_all("@{([^}]+)}@", $url, $matches);

        if (!empty($matches[0])) {
            foreach ($matches[0] as $n => $match) {
                if ($match == '{_scheme}') {
                    $url = str_replace('{_scheme}', $this->url_info['scheme'], $url);
                } elseif ($match == '{_host}') {
                    $url = str_replace('{_host}', $this->url_info['host'], $url);
                } else {
                    if (!empty($options[$matches[1][$n]])) {
                        if (preg_match('@^' . $parameters[$matches[1][$n]] . '$@', $options[$matches[1][$n]])) {
                            $url = str_replace($match, $options[$matches[1][$n]], $url);
                        } else {
                            $parameter = $matches[1][$n];
                            throw new BadRouteException("The paramater $parameter is invalid");
                        }
                    } else {
                        preg_match_all('@(\[|\(|\)|\])@', $parameters[$matches[1][$n]], $m);
                        if (empty($m[0])) {
                            $url = str_replace($match, $parameters[$matches[1][$n]], $url);
                        } else {
                            throw new BadRouteException('Missing parameters ' . $matches[1][$n]);
                        }
                    }
                }
            }
        }

        return $url;
    }

    /**
     * @param $httpMethod
     * @return bool
     */
    private function issetMethod($httpMethod)
    {

        $httpMethod = strtoupper($httpMethod);
        if (!defined("\\Buuum\\Route::$httpMethod")) {
            return false;
        }
        return true;
    }

    /**
     * @param $httpMethod
     * @return string
     * @throws HttpMethodNotAllowedException
     */
    private function checkMethod($httpMethod)
    {
        $httpMethod = strtoupper($httpMethod);

        if (!$this->issetMethod($httpMethod)) {
            throw new HttpMethodNotAllowedException("Not method $httpMethod allowed");
        }

        return $httpMethod;
    }

    /**
     * @param $requestUrl
     * @param bool $save
     * @return string
     */
    private function setUrlRequest($requestUrl, $save = true)
    {
        if ($save) {
            $this->request_url = $requestUrl;
        } elseif (!$requestUrl) {
            return $this->request_url;
        }

        $this->parseUrl($requestUrl);

        // Strip query string (?a=b) from Request Url
        if (($strpos = strpos($requestUrl, '?')) !== false) {
            $requestUrl = substr($requestUrl, 0, $strpos);
        }

        return $requestUrl;
    }

    /**
     * @param $httpMethod
     * @return array
     */
    private function getRoutes($httpMethod)
    {
        if (!isset($this->route_map['routes'][Route::ANY]) || !is_array($this->route_map['routes'][Route::ANY])) {
            $this->route_map['routes'][Route::ANY] = [];
        }

        if (!isset($this->route_map['routes'][$httpMethod]) || !is_array($this->route_map['routes'][$httpMethod])) {
            $this->route_map['routes'][$httpMethod] = [];
        }

        if (!isset($this->route_map['routes'][Route::ERROR])) {
            $this->route_map['routes'][Route::ERROR] = [];
        }

        return array_merge($this->route_map['routes'][Route::ANY], $this->route_map['routes'][$httpMethod]);
    }

    /**
     * @param $route
     * @param $arguments
     * @return array|mixed|null
     */
    private function executeBefores($route, $arguments)
    {
        if (!empty($route['before'])) {
            foreach ($route['before'] as $before) {
                $response = $this->callFunction($this->route_map['filters'][$before], $arguments);
                if ($response !== null) {
                    var_dump($response);
                    if (!is_array($response)) {
                        return $response;
                    }
                    if (!isset($response['passed']) || !isset($response['response'])) {
                        throw new \InvalidArgumentException("Response should be an array composed by keys 'passed' and 'response'");
                    }
                    if (!$response['passed']) {
                        return $response['response'];
                    } else {
                        if (is_array($response['response'])) {
                            $arguments = array_merge($arguments, $response['response']);
                        }
                    }
                }
            }
        }

        return [
            'arguments' => $arguments
        ];
    }

    /**
     * @param $route
     * @param $arguments
     */
    private function executeAfters($route, $arguments)
    {
        // after filters //
        if (!empty($route['after'])) {
            foreach ($route['after'] as $after) {
                $this->callFunction($this->route_map['filters'][$after], $arguments);
            }
        }
    }

    /**
     * @param $route
     * @param $arguments
     * @return array|mixed|null
     */
    private function getResponse($route, $arguments)
    {
        $arguments['_requesturi'] = $this->url_info;
        $arguments['_prefix'] = $route['prefix'];

        $this->last_route = $route;

        $response = $this->executeBefores($route, $arguments);
        if (!is_array($response)) {
            return $response;
        }
        $arguments = $response['arguments'];
        $response = $this->callFunction($route['handler'], $arguments);
        $this->executeAfters($route, $arguments);

        return $response;
    }
}