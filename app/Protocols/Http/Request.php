<?php

namespace app\Protocols\Http;


class Request
{

    /**
     * Http buffer
     * @var string
     */
    protected $buffer = null;

    /**
     * Request data.
     * @var null
     */
    protected $data = null;

    /**
     * Enable cache
     * @var bool
     */
    protected static $enableCache = true;

    /**
     * Get cache
     * @var array
     */
    protected static $getCache = array();

    /**
     * Request constructor.
     * @param $buffer
     */
    public function __construct($buffer)
    {
        $this->buffer = $buffer;
    }

    /**
     * @param null $name
     * @param null $default
     * @return mixed|null
     */
    public function get($name = null, $default = null)
    {
        if (!isset($this->data['get'])) {
            $this->parseGet();
        }
        if (null === $name) {
            return $this->data['get'];
        }
        return $this
    }


    protected function parseGet()
    {
        $queryString = $this->queryString();
        $this->data['get'] = array();
        if ($queryString === '') {
            return;
        }

        $cacheable = static::$enableCache && !isset($queryString[1024]);//偏移量
        if ($cacheable && isset(static::$getCache[$queryString])) {
            $this->data['get'] = static::$getCache[$queryString];
            return;
        }

        parse_str($queryString, $this->data['get']);
        if ($cacheable) {
            static::$getCache[$queryString] = $this->data['get'];
            if (count(static::$getCache) > 256) {
                unset(static::$getCache[key(static::$getCache)]);
            }
        }

    }

    /**
     * Get query string
     * @return mixed
     */
    public function queryString()
    {
        if (!isset($this->data['query_string'])) {
            $this->data['query_string'] = parse_url($this->uri(), PHP_URL_QUERY);
        }
        return $this->data['query_string'];
    }

    /**
     * Get uri
     * @return mixed
     */
    public function uri()
    {
        if (!isset($this->data['uri'])) {
            $this->parseHeadFirstLine();
        }
        return $this->data['uri'];
    }

    /**
     * Parse first line of http header buffer
     * @return void
     */
    protected function parseHeadFirstLine()
    {
        $firstLine = strstr($this->buffer, '\r\n', true);
        $tmp = explode(' ', $firstLine, 3);
        $this->data['method'] = $tmp[0];
        $this->data['uri'] = $tmp[1] ?? '/';
    }


}
