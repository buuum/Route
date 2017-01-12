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

    private $uri_appends = [];
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

        //$options['prefix'] = $this->getPrefix($options);
        //$this->setPrefixs($options['prefix']);

        $options['prefix'] = $this->parsePrefix($options);
        $options['uri_appends'] = $this->uri_appends;
        $this->uri_appends = [];
        $this->setHosts($options);

        $options = array_merge($options, ['base_uri' => $this->base_uri]);
        $group_routes = (!is_array($routes)) ? [$routes] : $routes;
        $is_group = count($group_routes) > 1;

        foreach ($group_routes as $route) {
            $_route = new Route($route, $options, $handler, $httpMethod);
            if ($is_group) {
                $_route->setGroup();
            }
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
        //if (!empty($options['host']) && !in_array($options['host'], $this->hosts)) {
        //    $this->hosts[] = $options['host'];
        //}
        if (!empty($options['host'])) {
            if (is_array($options['host'])) {
                foreach ($options['host'] as $host) {
                    if (!in_array($host, $this->hosts)) {
                        $this->hosts[] = $host;
                    }
                }
            } elseif (!in_array($options['host'], $this->hosts)) {
                $this->hosts[] = $options['host'];
            }
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

    private function parsePrefix($options)
    {
        if (!empty($options['prefix'])) {
            $prefix_parts = $options['prefix'];
            if (!is_array($options['prefix'])) {
                $prefix_parts = explode('/', $options['prefix']);
            }

            $prefix = [];
            foreach ($prefix_parts as $pre) {
                if (strpos($pre, '{') !== false) {
                    $this->uri_appends[] = $pre;
                } else {
                    $prefix[] = $pre;
                }
            }

            $prefix = implode('/', $prefix);
            $this->setPrefixs("/" . $prefix . "/");
            return $prefix;
        }

        return '';
    }

    /**
     * @param $options
     * @return string
     */
    private function getPrefix($options)
    {
        $prefix = "";
        if (!empty($options['prefix'])) {

            $prefix_parts = $options['prefix'];
            if (!is_array($options['prefix'])) {
                $prefix_parts = explode('/', $options['prefix']);
            }

            $prefix = [];
            foreach ($prefix_parts as $pre) {
                if (strpos($pre, '{') !== false) {
                    $this->uri_appends[] = $pre;
                } else {
                    $prefix[] = $pre;
                }
            }

            //$prefix = $options['prefix'];
            //if (is_array($options['prefix'])) {
            $prefix = implode('/', $prefix);
            //}
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
            //$prefixs = [];
            $large = [];
            $parameters = [];
            foreach ($array_routes[$method] as $key => $row) {
                $schemes[$key] = $row['scheme'];
                $hosts[$key] = $row['host'];
                //$prefixs[$key] = $row['prefix'];
                $parameters[$key] = count($row['parameters']);
                $large[$key] = count(explode('/', $row['uri']));
            }

            //array_multisort($schemes, SORT_DESC, $hosts, SORT_DESC, $prefixs, SORT_DESC, $array_routes[$method]);
            array_multisort($schemes, SORT_DESC, $hosts, SORT_DESC, $large, SORT_DESC, $parameters, SORT_ASC,
                $array_routes[$method]);
        }

        return $array_routes;
    }
}