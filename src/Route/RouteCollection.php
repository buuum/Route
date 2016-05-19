<?php

namespace Buuum;

class RouteCollection
{

    /**
     * @var string
     */
    private $base_uri;

    /**
     * @var array
     */
    private $routes = [];

    /**
     * @var array
     */
    private $hosts = [];

    /**
     * @var array
     */
    private $prefixs = [];

    /**
     * @var array
     */
    private $filters = [];

    /**
     * @var Route[]
     */
    private $last_group = [];

    /**
     * @var array
     */
    protected $patternMatchers = [
        'number'        => '[0-9]+',
        'word'          => '[a-zA-Z]+',
        'alphanum_dash' => '[a-zA-Z0-9-_]+',
        'slug'          => '[a-z0-9-]+'
    ];

    /**
     * RouteCollection constructor.
     * @param string $base_uri
     */
    public function __construct($base_uri = "")
    {
        $this->base_uri = $base_uri;
    }

    /**
     * @param $httpMethod
     * @param $routes
     * @param $options
     * @param $handler
     * @return Route
     */
    public function addRoute($httpMethod, $routes, $options, $handler)
    {
        if (!is_array($httpMethod)) {
            $httpMethod = array($httpMethod);
        }

        $this->last_group = [];

        $this->setPrefixs($this->getPrefix($options));
        $this->setHosts($options);

        $options = array_merge($options, ['base_uri' => $this->base_uri]);

        $group_routes = (!is_array($routes))? [$routes] : $routes;

        foreach ($group_routes as $route) {
            $_route = new Route($route, $options, $handler);
            $this->last_group[] = $_route;

            foreach ($httpMethod as $method) {
                $this->routes[$method][] = $_route;
            }
        }

        return $this;
    }

    /**
     * @param $name
     * @return $this
     */
    public function setName($name)
    {
        foreach ($this->last_group as $route) {
            $route->setName($name);
        }
        return $this;
    }

    /**
     * @param $host
     * @return $this
     */
    public function setHost($host)
    {
        foreach ($this->last_group as $route) {
            $route->setHost($host);
        }
        return $this;
    }

    /**
     * @param $scheme
     * @return $this
     */
    public function setScheme($scheme)
    {
        foreach ($this->last_group as $route) {
            $route->setScheme($scheme);
        }
        return $this;
    }

    /**
     * @param $name
     * @param $handler
     */
    public function addFilter($name, $handler)
    {
        $this->filters[$name] = $handler;
    }

    /**
     * @return array
     */
    public function getArrayMap()
    {

        return [
            'base_uri' => $this->base_uri,
            'prefixs'  => $this->prefixs,
            'routes'   => $this->getArrayRoutes(),
            'reverse'  => $this->getArrayReverse(),
            'filters'  => $this->filters
        ];
    }

    /**
     * @param $options
     */
    private function setHosts($options)
    {
        if (!empty($options['host']) && !in_array($options['host'], $this->hosts)) {
            $this->hosts[] = $options['host'];
        }
    }

    /**
     * @param $prefix
     */
    private function setPrefixs($prefix)
    {
        if (!in_array($prefix, $this->prefixs)) {
            $this->prefixs[] = $prefix;
            usort($this->prefixs, function ($a, $b) {
                return strlen($b) - strlen($a);
            });
        }
    }

    /**
     * @param $options
     * @return string
     */
    private function getPrefix($options)
    {
        $prefix = "";
        if (!empty($options['prefix'])) {
            $prefix = $options['prefix'];
            if (is_array($options['prefix'])) {
                $prefix = implode('/', $options['prefix']);
            }
            $prefix = '/' . $prefix . '/';
        }
        return $prefix;
    }

    /**
     * @return array
     */
    private function getArrayReverse()
    {
        $reverse_routes = [];
        foreach ($this->routes as $methord => $routes) {
            /** @var Route $route */
            foreach ($routes as $route) {
                if (!empty($route->getName())) {
                    $reverse_routes[$route->getName()][] = $route->getData();
                }
            }
        }
        return $this->orderRoutes($reverse_routes);
    }


    /**
     * @return array
     */
    private function getArrayRoutes()
    {
        $array_routes = [];
        foreach ($this->routes as $method => $routes) {
            /** @var Route $route */
            foreach ($routes as $route) {
                $array_routes[$method][] = $route->getData();
            }
        }
        return $this->orderRoutes($array_routes);
    }


    /**
     * @param $array_routes
     * @return array
     */
    private function orderRoutes($array_routes)
    {
        foreach ($array_routes as $method => $routes) {

            $schemes = [];
            $hosts = [];
            $prefixs = [];
            foreach ($array_routes[$method] as $key => $row) {
                $schemes[$key] = $row['scheme'];
                $hosts[$key] = $row['host'];
                $prefixs[$key] = $row['prefix'];
            }

            array_multisort($schemes, SORT_DESC, $hosts, SORT_DESC, $prefixs, SORT_DESC, $array_routes[$method]);
        }

        return $array_routes;
    }
}