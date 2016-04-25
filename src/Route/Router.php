<?php

namespace Buuum;

use Buuum\Exception\BadRouteException;

class Router
{
    /**
     * @var array
     */
    private $group_options = [];

    /**
     * Collection of routes
     *
     * @var RouteCollection
     */
    private $collection;

    /**
     * Router constructor.
     * @param string $base_uri
     */
    public function __construct($base_uri = "")
    {
        if (!is_string($base_uri)) {
            throw new BadRouteException("Base URI must be a string value");
        }
        $this->collection = new RouteCollection($base_uri);
    }

    /**
     * @param array $options
     * @param \Closure $callback
     */
    public function group(array $options, \Closure $callback)
    {
        $this->setGroupOptions($options);

        if (is_callable($callback)) {
            $callback($this);
            $this->resetGroupOptions();
        }
    }

    /**
     * @param string $httpMethod Valid Http method
     * @param string $route defined url
     * @param array|callable $controller handler for route
     * @return Route
     */
    public function map($httpMethod, $route, $controller)
    {
        return $this->collection->addRoute($httpMethod, $route, $this->getGroupOptions(), $controller);
    }

    /**
     * @param string $route defined url
     * @param array|callable $controller handler for route
     * @return Route
     */
    public function any($route, $controller)
    {
        return $this->map(Route::ANY, $route, $controller);
    }

    /**
     * @param string $route defined url
     * @param array|callable $controller handler for route
     * @return Route
     */
    public function get($route, $controller)
    {
        return $this->map(Route::GET, $route, $controller);
    }

    /**
     * @param string $route defined url
     * @param array|callable $controller handler for route
     * @return Route
     */
    public function head($route, $controller)
    {
        return $this->map(Route::HEAD, $route, $controller);
    }

    /**
     * @param string $route defined url
     * @param array|callable $controller handler for route
     * @return Route
     */
    public function post($route, $controller)
    {
        return $this->map(Route::POST, $route, $controller);
    }

    /**
     * @param string $route defined url
     * @param array|callable $controller handler for route
     * @return Route
     */
    public function put($route, $controller)
    {
        return $this->map(Route::PUT, $route, $controller);
    }

    /**
     * @param string $route defined url
     * @param array|callable $controller handler for route
     * @return Route
     */
    public function patch($route, $controller)
    {
        return $this->map(Route::PATCH, $route, $controller);
    }

    /**
     * @param string $route defined url
     * @param array|callable $controller handler for route
     * @return Route
     */
    public function delete($route, $controller)
    {
        return $this->map(Route::DELETE, $route, $controller);
    }

    /**
     * @param string $route defined url
     * @param array|callable $controller handler for route
     * @return Route
     */
    public function options($route, $controller)
    {
        return $this->map(Route::OPTIONS, $route, $controller);
    }

    /**
     * @param string $name
     * @param array|callable $handler
     */
    public function filter($name, $handler)
    {
        $this->collection->addfilter($name, $handler);
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->collection->getArrayMap();
    }

    /**
     * @param $options
     */
    private function setGroupOptions($options)
    {
        if (!empty($this->group_options)) {
            $this->group_options[] = array_merge_recursive(end($this->group_options), $options);
        } else {
            $this->group_options[] = $options;
        }
    }

    /**
     *
     */
    private function resetGroupOptions()
    {
        array_pop($this->group_options);
    }

    /**
     * @return array
     */
    private function getGroupOptions()
    {
        $options = end($this->group_options);
        return (is_array($options)) ? $options : [];
    }

}