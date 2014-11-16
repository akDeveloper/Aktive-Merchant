<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Mock;

use AktiveMerchant\Http\RequestInterface;
use AktiveMerchant\Http\AdapterInterface;

/**
 * Request Mock class
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 *
 */

class Request implements RequestInterface
{

    protected $url;

    protected $method;

    protected $headers = array();

    protected $body;

    protected $adapter;

    protected $options;

    protected $config = array(
        'connect_timeout'   => 10,
        'timeout'           => 0,
        'ssl_verify_peer'   => true,
        'ssl_verify_host'   => 2,
        'user_agent'        => null,
    );

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setMethod($method)
    {
        $this->method = strtoupper($method);
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function send()
    {
        return true;
    }

    public function getResponseBody()
    {

    }

    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    public function getConfig()
    {
        return $this->config;
    }
}
