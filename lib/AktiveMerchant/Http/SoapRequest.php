<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Http;

use SoapClient;

class SoapRequest implements RequestInterface
{
    protected $response;

    protected $url;

    protected $action;

    protected $adapter;

    /**
     * {@inheritdoc}
     */
    public function setMethod($method)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function getMethod()
    {

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
    public function setHeaders(array $headers)
    {

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
        $adapter = $this->adapter ?: new SoapClient($this->url,array('trace' => true));

        $this->response = $adapter->__soapCall($this->action, $this->body);

        print_r($adapter->__getLastRequest());
        return $this->response;
    }

    public function getResponseBody()
    {
        return $this->response;
    }

    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Gets SOAP action.
     *
     * @access public
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Sets SOAP action.
     *
     * @param string $action The SOAP action to execute.
     * @access public
     * @return void
     */
    public function setAction($action)
    {
        $this->action = $action;
    }
}
