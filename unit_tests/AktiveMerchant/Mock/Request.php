<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Mock;

/**
 * Request Mock class 
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 *
 */

class Request
{
     
    protected $url;

    protected $method;

    protected $headers = array();   
    
    protected $body;
    
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
    
    public function setHeaders($headers) 
    {
        $this->headers = $headers;
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
}
