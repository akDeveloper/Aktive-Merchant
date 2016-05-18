<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Http;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * RequestInterface
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
interface RequestInterface
{
    const METHOD_GET    = 'GET';
    const METHOD_POST   = 'POST';
    const METHOD_PUT    = 'PUT';
    const METHOD_DELETE = 'DELETE';

    /**
     * Sets the method for request.
     * It can be 'GET or 'POST
     *
     * @param  string $method
     * @access public
     * @return void
     */
    public function setMethod($method);

    /**
     * Gets the method of the request
     *
     * @access public
     * @return void
     */
    public function getMethod();

    /**
     * Sets the url to request for.
     *
     * @param  string $url
     * @access public
     * @return void
     */
    public function setUrl($url);

    /**
     * Gets the url
     *
     * @access public
     * @return string
     */
    public function getUrl();

    /**
     * Sets the headers for the request.
     *
     * @param  array $headers
     * @access public
     * @return void
     */
    public function setHeaders(array $headers);

    /**
     * Sets the request body.
     *
     * @param  string $body
     * @access public
     * @return void
     */
    public function setBody($body);

    /**
     * Gets the request body to be sent to endpoint.
     *
     * @access public
     * @return string
     */
    public function getBody();

    /**
     * Sends the request to endpoint.
     *
     * @access public
     * @return boolean
     */
    public function send();

    public function getResponseBody();

    public function setAdapter(AdapterInterface $adapter);

    public function getDispatcher();

    public function setDispatcher(EventDispatcherInterface $dispatcher);
}
