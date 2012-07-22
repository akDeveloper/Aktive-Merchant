<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Http;

use AktiveMerchant\Billing\Exception;

class Request implements Interfaces\Request
{
    const METHOD_GET  = 'GET';
    const METHOD_POST = 'POST';

    protected $url;

    protected $method;

    protected $headers = array();

    protected $body;

    protected $timeout = 0;

    protected $allow_unsafe_ssl = 1;

    protected $user_agent;

    protected $response_body;
    
    protected $response_headers;

    public function __construct($url = null, 
        $method=self::METHOD_GET, $options=array())
    {
        if (!empty($url)) {
            $this->setUrl($url);
        }

        if (!empty($method)) {
            $this->setMethod($method);
        }

        if (isset($options['timeout'])) {
            $this->timeout = $options['timeout'];
        }

        if (isset($options['allow_unsafe_ssl'])) {
            $this->allow_unsafe_ssl = $options['allow_unsafe_ssl'];
        }

        if (isset($options['user_agent'])) {
            $this->user_agent = $options['user_agent'];
        }

        if (isset($options['headers'])) {
            $this->setHeaders($options['headers']);
        }
    }

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
        return $this->curl(); 
    }

    public function getResponseBody()
    {
        return $this->response_body; 
    }

    public function getResponseHeaders()
    {
        return $this->response_headers;
    }

    private function curl()
    {
        $server = parse_url($this->url);

        if (!isset($server['port'])) {
            $server['port'] = ($server['scheme'] == 'https') ? 443 : 80;
        }

        if (!isset($server['path'])) {
            $server['path'] = '/';
        }

        if (isset($server['user']) && isset($server['pass'])) {
            $this->headers[] = 'Authorization: Basic ' 
                . base64_encode($server['user'] . ':' . $server['pass']);
        }

        $transaction_url = $server['scheme'] 
            . '://' . $server['host'] 
            . $server['path'] 
            . (isset($server['query']) ? '?' . $server['query'] : '');

        if (function_exists('curl_init')) {
            $curl = curl_init($transaction_url);

            curl_setopt($curl, CURLOPT_PORT, $server['port']);
            curl_setopt($curl, CURLOPT_HEADER, 1);
            curl_setopt($curl, CURLINFO_HEADER_OUT, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->allow_unsafe_ssl);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
            curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->timeout);
            curl_setopt($curl, CURLOPT_USERAGENT, $this->user_agent);

            if ($this->method == self::METHOD_POST) {
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $this->body);
            } elseif ($this->method == self::METHOD_GET)  {
                curl_setopt($curl, CURLOPT_HTTPGET, 1);
            }

            $response = curl_exec($curl);

            // Check for outright failure
            if ($response === FALSE) {
                $ex = new Exception(curl_error($curl), curl_errno($curl));
                curl_close($curl);
                throw $ex;
            }

            // Now check for an HTTP error
            $curl_info = curl_getinfo($curl);
            
            curl_close($curl);
            
            if (   ($curl_info['http_code'] < 200) 
                && ($curl_info['http_code'] >= 300)
            ) {
                $ex = new Exception(
                    "HTTP Status #" 
                    . $curl_info['http_code']."\n"
                    . "CurlInfo:\n"
                    . print_r($curl_info, TRUE)
                );
                throw $ex;
            }
            
            $this->response_headers = substr($response, 0, $curl_info['header_size']);
            $this->response_body    = substr($response, -$curl_info['size_download']);
            
            // OK, the response was OK at the HTTP level at least!
            return true;
        } else {
            throw new Exception('curl is not installed!');
        }
   
    }
}
