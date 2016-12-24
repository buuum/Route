<?php

namespace Buuum;

//https://github.com/mrjgreen/phroute/tree/v3/examples
//https://laravel.com/docs/5.2/routing#route-groups

class Route
{
    /**
     * Constants for common HTTP methods
     */
    const ANY = 'ANY';
    const GET = 'GET';
    const HEAD = 'HEAD';
    const POST = 'POST';
    const PUT = 'PUT';
    const PATCH = 'PATCH';
    const DELETE = 'DELETE';
    const OPTIONS = 'OPTIONS';
    const LINK = 'LINK';

    /**
     * @var string
     */
    private $route;

    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var string
     */
    private $uri;

    /**
     * @var array|callable
     */
    private $handler;

    /**
     * @var array
     */
    private $parameters = [];

    /**
     * @var string
     */
    private $close_tag = '';

    /**
     * @var bool
     */
    private $is_group = false;

    /**
     * @var array
     */
    private $methods = [];

    /**
     * Route constructor.
     * @param $route
     * @param $options
     * @param $handler
     * @param $methods
     * @param string $base_uri
     */
    public function __construct($route, $options, $handler, $methods, $base_uri = "")
    {
        $this->options = $options;
        $this->route = $this->setRoute($route);
        $this->uri = $this->setUri();
        $this->handler = $handler;
        $this->methods = $methods;
    }

    private function setRoute($route)
    {
        if (!empty($this->options['uri_appends'])) {
            $route = '/' . implode('/', $this->options['uri_appends']) . $route;
        }
        return $route;
    }

    /**
     * @return string
     */
    private function getUriRegex()
    {

        $scheme = '{_scheme:[^:]+}';
        if (!empty($this->options['scheme'])) {
            $scheme = $this->options['scheme'];
        }
        $scheme .= '://';

        $host = '{_host:[^/]+}';
        if (!empty($this->options['host'])) {
            $host = $this->options['host'];
        }

        return $this->convertToRegex($scheme . $host . $this->uri);
    }

    /**
     * @return string
     */
    private function getUriRegexPre()
    {
        $scheme = '{_scheme:[^:]+}';
        if (!empty($this->options['scheme'])) {
            $scheme = $this->options['scheme'];
        }
        $scheme .= '://';

        $host = '{_host:[^/]+}';
        if (!empty($this->options['host'])) {
            $host = $this->options['host'];
        }

        return $this->convertToRegex($scheme . $host . $this->setUri(false), false);
    }

    /**
     * @return mixed
     */
    private function getUriReverse()
    {

        $scheme = '{_scheme:[^:]+}';
        if (!empty($this->options['scheme'])) {
            $scheme = $this->options['scheme'];
        }
        $scheme .= '://';

        $host = '{_host:[^/]+}';
        if (!empty($this->options['host'])) {
            $host = $this->options['host'];
        }

        return $this->setParameters($scheme . $host . $this->uri);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param $host
     * @return $this
     */
    public function setHost($host)
    {
        $this->options['host'] = $host;
        return $this;
    }

    /**
     * @param $scheme
     * @return $this
     */
    public function setScheme($scheme)
    {
        $this->options['scheme'] = $scheme;
        return $this;
    }

    /**
     * @return mixed
     */
    private function getHost()
    {
        if (!empty($this->options['host'])) {
            return $this->options['host'];
        }

        return '';
    }

    /**
     *
     */
    public function setGroup()
    {
        $this->is_group = true;
    }

    /**
     * @param $name
     * @param $value
     */
    private function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    /**
     * @return array|bool
     */
    private function getBefores()
    {
        if (empty($this->options['before'])) {
            return false;
        } else {
            return (!is_array($this->options["before"])) ? array($this->options["before"]) : $this->options["before"];
        }
    }

    /**
     * @return array|bool
     */
    private function getAfters()
    {
        if (empty($this->options['after'])) {
            return false;
        } else {
            return (!is_array($this->options["after"])) ? array($this->options["after"]) : $this->options["after"];
        }
    }

    /**
     * @return array|callable
     */
    private function getHandler()
    {
        return $this->handler;
    }

    /**
     * @return string
     */
    private function getScheme()
    {
        return (!empty($this->options['scheme'])) ? $this->options['scheme'] : '';
    }

    /**
     * @return array
     */
    public function getData()
    {
        return [
            'after'      => $this->getAfters(),
            'before'     => $this->getBefores(),
            'name'       => $this->getName(),
            'uri'        => $this->uri,
            'regex'      => $this->getUriRegex(),
            'regex_pre'  => $this->getUriRegexPre(),
            'reverse'    => $this->getUriReverse() . $this->close_tag,
            'parameters' => $this->parameters,
            'handler'    => $this->getHandler(),
            'host'       => $this->getHost(),
            'prefix'     => $this->getPrefix(),
            'scheme'     => $this->getScheme(),
            'methods'    => $this->methods
        ];
    }

    /**
     * @param $content
     * @return mixed
     */
    private function safeRegex($content)
    {
        $f = array('(', ')');
        $r = array('\(', '\)');
        return str_replace($f, $r, $content);
    }


    private function getUriAppend()
    {
        if (!empty($this->options['uri_appends'])) {
            return implode('/', $this->options['uri_appends']);
        }
        return '';
    }

    /**
     * @return string
     */
    private function getPrefix()
    {
        if (!empty($this->options['prefix'])) {
            return $this->options['prefix'];
        }
        return '';
        //$prefix = "";
        //if (!empty($this->options['prefix'])) {
        //    $prefix = $this->options['prefix'];
        //    if (is_array($this->options['prefix'])) {
        //        $prefix = implode('/', $this->options['prefix']);
        //    }
        //}
        //return $prefix;
    }


    /**
     * @param $route
     * @param bool $full_regex
     * @return string
     */
    private function convertToRegex($route, $full_regex = true)
    {
        $postfix = ($full_regex) ? '[/]?$@' : '[/]?@';

        $route = $this->setParameters($route);
        return '@^' . preg_replace_callback("@{([^}]+)}@", function ($match) {
            return $this->regexParameter($match[0]);
        }, $route) . $postfix;
    }

    /**
     * @param $route
     * @return mixed
     */
    private function setParameters($route)
    {

        $parse_route = $route;
        $regex = "@{([^:]+)([^}]+)}@";
        preg_match_all($regex, $route, $m);
        if (!empty($m[0])) {
            foreach ($m[0] as $i => $match) {
                if (substr($m[2][$i], 0, 1) == ':') {
                    $name = $m[1][$i];
                    $this->setParameter($m[1][$i], substr($m[2][$i], 1));
                } else {
                    $name = $m[1][$i] . $m[2][$i];
                    $this->setParameter($m[1][$i] . $m[2][$i], '');
                }
                $parse_route = str_replace($match, '{' . $name . '}', $parse_route);
            }
        }
        return $parse_route;
    }

    /**
     * @param $name
     * @return string
     */
    private function regexParameter($name)
    {
        $name = str_replace(array('{', '}'), array('', ''), $name);
        $pattern = !empty($this->parameters[$name]) ? $this->parameters[$name] : "[^/]+";
        return '(?<' . $name . '>' . $pattern . ')';
    }


    /**
     * @param bool $full_uri
     * @return mixed
     */
    private function setUri($full_uri = true)
    {
        $uri = '';
        if (!empty($this->options['base_uri'])) {
            $uri .= $this->options['base_uri'];
        }
        if (!empty($this->getPrefix())) {
            $uri .= '/' . $this->getPrefix();
        }

        //if (!empty($this->getUriAppend())) {
        //    $uri .= '/' . $this->getUriAppend();
        //}

        if (substr($this->route, -1) == '/') {
            $this->close_tag = '/';
            $this->route = substr($this->route, 0, -1);
        }

        if ($this->is_group) {
            $prefix_group = explode('/', $this->uri);
            if (count($prefix_group) > 2) {
                $prefix_group = $prefix_group[1];
                if ($prefix_group != '') {
                    $uri .= '/' . $prefix_group;
                }
            }
        }

        if ($full_uri) {
            return $this->safeRegex($uri . $this->route);
        } else {
            $end = (!empty($this->route)) ? '/' : '';
            return $this->safeRegex($uri . $end);
        }
    }

}