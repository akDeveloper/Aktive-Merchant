<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Http\Adapter;

use AktiveMerchant\Http\Request;
use AktiveMerchant\Http\AdapterInterface;

/**
 * cUrl adapter
 * 
 * @uses    AdapterInterface
 * @package Aktive-Merchant
 * @author  Andreas Kollaros <php@andreaskollaros.com> 
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
class cUrl implements AdapterInterface
{
    protected $ch;

    /**
     * The url endpoint to execute the request.
     * 
     * @var    mixed
     * @access protected
     */
    protected $url;

    protected $port;

    /**
     * Information about the last transfer retrieved from curl_getinfo 
     * function.
     * 
     * @var    array
     * @access protected
     */
    protected $info = array();

    protected $response_body;
    
    protected $response_headers;

    /**
     * Extra options for cUrl configuration.
     * 
     * @var    array
     * @access protected
     */
    protected $options = array();

    protected $map_config = array(
        'connect_timeout' => CURLOPT_CONNECTTIMEOUT,
        'timeout'         => CURLOPT_TIMEOUT,
        'ssl_verify_peer' => CURLOPT_SSL_VERIFYPEER,
        'ssl_verify_host' => CURLOPT_SSL_VERIFYHOST,
        'user_agent'      => CURLOPT_USERAGENT,
    );

    /**
     * {@inheritdoc}
     */
    public function sendRequest(Request $request)
    {
        $this->apply_options($request);
        
        if ($request->getMethod() == Request::METHOD_POST) {
            curl_setopt($this->ch, CURLOPT_POST, 1);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $request->getBody());
        } elseif ($request->getMethod() == Request::METHOD_GET)  {
            curl_setopt($this->ch, CURLOPT_HTTPGET, 1);
        }

        $response = curl_exec($this->ch);

        // Check for outright failure
        if ($response === false) {
            $ex = new Exception(curl_error($this->ch), curl_errno($this->ch));
            curl_close($this->ch);
            throw $ex;
        }
        
        // Now check for an HTTP error
        $curl_info = curl_getinfo($this->ch);

        curl_close($this->ch);

        if ($curl_info['http_code'] != 200) {
            $ex = new Exception(
                "HTTP Status #" 
                . $curl_info['http_code']."\n"
                . "CurlInfo:\n"
                . print_r($curl_info, true)
            );
            throw $ex;
        }

        $this->info = $curl_info;

        $this->response_headers = substr($response, 0, $curl_info['header_size']);

        $this->response_body = substr($response, -$curl_info['size_download']);

        // OK, the response was OK at the HTTP level at least!
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseBody()
    {
        return $this->response_body; 
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseHeaders()
    {
        return $this->response_headers;
    }

    /**
     * {@inheritdoc}
     */
    public function setOption($option, $value)
    {
        $this->options[$option] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($option)
    {
        return isset($this->options[$option]) 
            ? $this->options[$option]
            : null;
    }

    /**
     * Gets Information about the last transfer.
     * 
     * @access public
     * @return array
     */
    public function getInfo()
    {
        return $this->info;
    }

    protected function init_handler() 
    {
        $this->ch = curl_init();
    }

    protected function init_url(Request $request)
    {
        $server = parse_url($request->getUrl());

        if (!isset($server['port'])) {
            $server['port'] = ($server['scheme'] == 'https') ? 443 : 80;
            $this->port = $server['port'];
        }

        if (!isset($server['path'])) {
            $server['path'] = '/';
        }

        if (isset($server['user']) && isset($server['pass'])) {
            $this->request->setHeader(
                'Authorization: Basic '.base64_encode($server['user']),
                $server['pass']
            );
        }

        $this->url = $server['scheme'] 
            . '://' . $server['host'] 
            . $server['path'] 
            . (isset($server['query']) ? '?' . $server['query'] : ''); 
    }

    protected function apply_options(Request $request)
    {
        $this->init_handler();
        
        $this->init_url($request);
        
        $default = array(
            CURLOPT_PORT            => $this->port,
            CURLOPT_HEADER          => true,
            CURLINFO_HEADER_OUT     => true,
            CURLOPT_HTTPHEADER      => $request->getHeaders(),
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_URL             => $this->url,
            //close connection when it has finished, not pooled for reuse
            CURLOPT_FORBID_REUSE    => 1,
            // Do not use cached connection
            CURLOPT_FRESH_CONNECT   => 1
        );

        $config = $this->map_config($request->getConfig());
        $options = $default + $this->options + $config;

        curl_setopt_array($this->ch, $options);
    }

    protected function map_config($config)
    {
        $map = array();
        
        foreach ($config as $o=>$c) {
            $key = $this->map_config[$o];
            $map[$key] = $c;
        }

        return $map;
    }
}
