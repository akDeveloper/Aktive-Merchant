<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Http\Adapter;

use AktiveMerchant\Http\RequestInterface;
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
        'ssl_version'     => CURLOPT_SSLVERSION,
    );

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request)
    {
        $this->apply_options($request);

        if ($request->getMethod() == RequestInterface::METHOD_POST) {
            curl_setopt($this->ch, CURLOPT_POST, 1);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $request->getBody());
        } elseif ($request->getMethod() == RequestInterface::METHOD_GET) {
            curl_setopt($this->ch, CURLOPT_HTTPGET, 1);
        } elseif ($request->getMethod() == RequestInterface::METHOD_PUT) {
            curl_setopt($this->ch, CURLOPT_POST, 1);
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $request->getBody());
        } elseif ($request->getMethod() == RequestInterface::METHOD_DELETE) {
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
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

        if ($curl_info['http_code'] == '301') {
            $request->setUrl($curl_info['redirect_url']);
            return $this->sendRequest($request);
        }

        if ($curl_info['http_code'] < 200
            || $curl_info['http_code'] >= 500
        ) {
            $this->response_body = substr($response, -$curl_info['size_download']);
            $ex = new Exception(
                "HTTP Status #"
                . $curl_info['http_code']."\n"
                . "CurlInfo:\n"
                . print_r($curl_info, true)
            );

            $ex->setResponseBody($this->response_body);

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
        $option = isset($this->map_config[$option]) ? $this->map_config[$option] : $option;
        $this->options[$option] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($option)
    {
        $option = isset($this->map_config[$option]) ? $this->map_config[$option] : $option;
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

    protected function init_url(RequestInterface $request)
    {
        $server = parse_url($request->getUrl());

        if (!isset($server['port'])) {
            $server['port'] = ($server['scheme'] == 'https') ? 443 : 80;
        }

        $this->port = $server['port'];

        if (!isset($server['path'])) {
            $server['path'] = '/';
        }

        if (isset($server['user']) && isset($server['pass'])) {
            $credentials = $server['user'] . ':' . $server['pass'];
            $request->addHeader('Authorization', 'Basic ' . base64_encode($credentials));
        }

        $port = !in_array($this->port, array(443, 80)) && !empty($this->port)
            ? ":{$this->port}"
            : null;

        $this->url = $server['scheme']
            . '://' . $server['host']
            . $port
            . $server['path']
            . (isset($server['query']) ? '?' . $server['query'] : '');
    }

    protected function apply_options(RequestInterface $request)
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
            CURLOPT_FRESH_CONNECT   => 1,
            CURLOPT_CONNECTTIMEOUT  => 5,
            CURLOPT_TIMEOUT         => 7
        );

        $config = $this->map_config($request->getConfig());
        $this->options = $this->options + $config + $default;

        curl_setopt_array($this->ch, $this->options);
    }

    protected function map_config($config)
    {
        $map = array();

        foreach ($config as $o => $c) {
            $key = $this->map_config[$o];
            $map[$key] = $c;
        }

        return $map;
    }

    /**
     * Gets options.
     *
     * @access public
     * @return mixed
     */
    public function getOptions()
    {
        return $this->options;
    }
}
