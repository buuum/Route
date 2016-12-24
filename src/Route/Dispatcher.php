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
     * Dispatcher constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->route_map = $data;
    }

    /**
     * @param $httpMethod
     * @param $requestUrl
     * @param HandlerResolverInterface|null $resolver
     * @return bool|mixed|null
     * @throws HttpMethodNotAllowedException
     * @throws HttpRouteNotFoundException
     */
    public function dispatchRequest($httpMethod, $requestUrl, HandlerResolverInterface $resolver = null)
    {
        $httpMethod = strtoupper($httpMethod);

        $this->request_url = $requestUrl;
        $this->parseUrl($requestUrl);
        if (!$this->issetMethod($httpMethod)) {
            if ($resolver && $resolver->parseErrors()) {
                return $resolver->resolveErrors(self::ERRORMETHODNOTALLOWED, $this->url_info);
            } else {
                throw new HttpMethodNotAllowedException("Not method $httpMethod allowed");
            }
        }
        $this->checkBaseURI();

        // Strip query string (?a=b) from Request Url
        if (($strpos = strpos($requestUrl, '?')) !== false) {
            $requestUrl = substr($requestUrl, 0, $strpos);
        }

        if (!isset($this->route_map['routes'][Route::ANY]) || !is_array($this->route_map['routes'][Route::ANY])) {
            $this->route_map['routes'][Route::ANY] = [];
        }
        if (!isset($this->route_map['routes'][$httpMethod]) ||
            !is_array($this->route_map['routes'][$httpMethod])
        ) {
            $this->route_map['routes'][$httpMethod] = [];
        }
        $routes = array_merge($this->route_map['routes'][Route::ANY], $this->route_map['routes'][$httpMethod]);
        $flag = false;

        foreach ($routes as $route) {

            $route_pattern = $route['regex'];

            if (preg_match($route_pattern, $requestUrl, $arguments)) {

                $arguments['_requesturi'] = $this->url_info;
                $arguments['_prefix'] = $route['prefix'];

                $this->last_route = $route;

                // before filters //
                if (!empty($route['before'])) {
                    foreach ($route['before'] as $before) {
                        $response = $this->callFunction($this->route_map['filters'][$before], $arguments, null,
                            $resolver);
                        if ($response !== null) {
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

                $response = $this->callFunction($route['handler'], $arguments, null, $resolver);

                // after filters //
                if (!empty($route['after'])) {
                    foreach ($route['after'] as $after) {
                        $response = $this->callFunction($this->route_map['filters'][$after], $arguments, $response,
                            $resolver);
                    }
                }

                return $response;

            }
        }

        if (!$flag) {
            if ($resolver && $resolver->parseErrors()) {
                return $resolver->resolveErrors(self::ERRORNOTFOUND, $this->url_info);
            } else {
                throw new HttpRouteNotFoundException("Not route found");
            }
        }

        return true;

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
        if (is_null($requestUrl) && is_null($this->request_url)) {
            throw new HttpRouteNotFoundException("Route $name not found");
        }
        $requestUrl = ($requestUrl) ?: $this->request_url;

        $this->parseUrl($requestUrl);
        $this->checkBaseURI();

        if (empty($this->route_map['reverse'][$name])) {
            throw new HttpRouteNotFoundException("Route $name not found");
        }

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
     * @param null $_response
     * @param HandlerResolverInterface|null $resolver
     * @return mixed|null
     * @throws HttpMethodNotExistException
     */
    private function callFunction(
        $controller,
        $parameters,
        $_response = null,
        HandlerResolverInterface $resolver = null
    ) {

        if (!is_array($controller) && is_callable($controller)) {
            $parameters = $this->arrangeFuncArgs($controller, $parameters);
            $response = call_user_func_array($controller, $parameters);
        } else {
            if (method_exists($class = $controller[0], $method = $controller[1])) {
                $parameters = $this->arrangeMethodArgs($class, $method, $parameters);
                if ($resolver) {
                    $response = call_user_func_array($resolver->resolve($controller), $parameters);
                } else {
                    $response = call_user_func_array([$class, $method], $parameters);
                }
            } else {
                if ($resolver && $resolver->parseErrors()) {
                    return $resolver->resolveErrors(self::ERRORCLASSMETHODNOTFOUND, $this->url_info);
                } else {
                    throw new HttpMethodNotExistException("Not method $method exist in class $class");
                }
            }
        }

        return ($_response) ? $_response : $response;
    }

    /**
     * @throws HttpRouteNotFoundException
     */
    private function checkBaseURI()
    {
        if (substr($this->url_info['path'], 0, strlen($this->route_map['base_uri'])) != $this->route_map['base_uri']) {
            throw new HttpRouteNotFoundException(self::ERRORNOTFOUND);
        }
    }

    /**
     * @param $function
     * @param $arguments
     * @return array
     */
    private function arrangeFuncArgs($function, $arguments)
    {
        $ref = new \ReflectionFunction($function);
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
            $ref->getParameters()
        );
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
            $ref->getParameters()
        );
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
}