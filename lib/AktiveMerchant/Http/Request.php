<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Http;

use AktiveMerchant\Billing\Exception;
use AktiveMerchant\Http\Adapter\cUrl;
use AktiveMerchant\Common\Options;

/**
 * Request 
 * 
 * @uses    RequestInterface
 * @package Aktive-Merchant 
 * @author  Andreas Kollaros
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
class Request implements RequestInterface
{

    /**
     * The adapter to use for sending the request.
     * 
     * @var    AdapterInterface
     * @access protected
     */
    protected $adapter;

    protected $url;

    protected $method;

    protected $headers = array();

    protected $body;

    protected $options;

    protected $config = array(
        'connect_timeout'   => 10,
        'timeout'           => 0,
        'ssl_verify_peer'   => true,
        'ssl_verify_host'   => 2,
        'user_agent'        => null,
    );

    /**
     * Creates an instance of Request class.
     *
     * Allowed configuration options are
     *
     * connect_timeout: The number of seconds to wait while trying to connect.
     *                  Use 0 to wait indefinitely.
     * timeout        : The maximum number of seconds to allow cURL functions 
     *                  to execute.
     * ssl_verify_peer: FALSE to stop cURL from verifying the peer's certificate.
     * ssl_verify_host: 1 to check the existence of a common name in the SSL 
     *                  peer certificate. 2 to check the existence of a common 
     *                  name and also verify that it matches the hostname 
     *                  provided.
     * user_agent     : The contents of the "User-Agent: " header to be used 
     *                  in a HTTP request.
     *
     * Additional options can be set via adapter directly, using 
     * AdapterInterface::setOptions() method.
     *
     * @oaram  string $url     The endpoint url
     * @oaram  string $method  The request method
     * @oaram  array  $options Configuration options for request.
     * @access public 
     */
    public function __construct(
        $url,
        $method = self::METHOD_GET,
        array $options = array()
    ) {
        
        $this->setUrl($url);

        $this->setMethod($method);

        $this->options = new Options($options);
    }

    public function getAdapter()
    {
        $this->adapter = $this->adapter ?: new cUrl();

        return $this->adapter;
    }

    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Gets configuration options
     * 
     * @access public
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl() 
    {
        return $this->url;
    }

    /**
     * {@inheritdoc}
     */
    public function setMethod($method)
    {
        $this->method = strtoupper($method);
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod() 
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    /**
     * Appends a header type to request.
     * 
     * @param  string $name 
     * @param  string $value 
     * @access public
     * @return void
     */
    public function addHeader($name, $value)
    {
        $this->headers[] = "$name: $value";
    }

    /**
     * {@inheritdoc}
     */
    public function setBody($body) 
    {
        $this->body = $body;
    }

    /**
     * {@inheritdoc}
     */
    public function getBody() 
    {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    public function send() 
    {
        return $this->getAdapter()->sendRequest($this); 
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseBody()
    {
        return $this->getAdapter()->getResponseBody(); 
    }

    /**
     * {@inheritdoc}
     */
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
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // $this->allow_unsafe_ssl);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // $this->allow_unsafe_ssl);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connect_timeout);
            curl_setopt($curl, CURLOPT_TIMEOUT, $this->request_timeout);
            curl_setopt($curl, CURLOPT_USERAGENT, $this->user_agent);

            if ($this->method == self::METHOD_POST) {
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $this->body);
            } elseif ($this->method == self::METHOD_GET)  {
                curl_setopt($curl, CURLOPT_HTTPGET, 1);
            }

            $response = curl_exec($curl);

            // Check for outright failure
            if ($response === false) {
                $ex = new Exception(curl_error($curl), curl_errno($curl));
                curl_close($curl);
                throw $ex;
            }

            // Now check for an HTTP error
            $curl_info = curl_getinfo($curl);
            
            curl_close($curl);
            
            if ($curl_info['http_code'] != 200) {
                $ex = new Exception(
                    "HTTP Status #" 
                    . $curl_info['http_code']."\n"
                    . "CurlInfo:\n"
                    . print_r($curl_info, true)
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
