<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Http\Adapter;

use AktiveMerchant\Http\AdapterInterface;
use AktiveMerchant\Http\RequestInterface;
use SoapClient;
use SoapFault;

/**
 * SoapClient adapter
 *
 * @uses AdapterInterface
 * @package Aktive-Merchant
 * @author  Andreas Kollaros <andreaskollaros@ymail.com>
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
class SoapClientAdapter implements AdapterInterface
{
    protected $client;

    protected $response_body;

    protected $response_headers;

    protected $request_body_xml;

    protected $response_body_xml;

    protected $options = array(
        'trace' => true,
        'exceptions' => true
    );

    protected $map_config = array(
        'connect_timeout' => 'connection_timeout',
        'user_agent'      => 'user_agent',
        'ssl_version'     => 'ssl_method',
    );

    /**
     * {@inheritdoc}
     */
    public function setOption($option, $value)
    {
        $option = isset($this->map_config[$option])
            ? $this->map_config[$option]
            : $option;
        $this->options[$option] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($option)
    {
        $option = isset($this->map_config[$option])
            ? $this->map_config[$option]
            : $option;
        return isset($this->options[$option])
            ? $this->options[$option]
            : null;
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
        $this->response_headers;
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request)
    {
        $action = $this->options['action'];
        unset($this->options['action']);
        $this->client = new SoapClient($request->getUrl(), $this->options);

        try {
            $this->response_body = $this->client->__soapCall($action, $request->getBody());
        } catch (SoapFault $e) {
            $this->response_body = null;

            throw new Exception($e->getMessage(), $e->getCode());
            return false;
        }

        $this->response_headers = $this->client->__getLastResponseHeaders();

        $this->request_body_xml = $this->client->__getLastRequest();
        $this->response_body_xml = $this->client->__getLastResponse();



        return true;
    }

    /**
     * Gets request_body_xml.
     *
     * @access public
     * @return mixed
     */
    public function getRequestBodyXml()
    {
        return $this->request_body_xml;
    }

    /**
     * Gets response_body_xml.
     *
     * @access public
     * @return mixed
     */
    public function getResponseBodyXml()
    {
        return $this->response_body_xml;
    }
}
