<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Http;

/**
 * AdapterInterface
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
interface AdapterInterface
{

    /**
     * Sets an option value to adapter.
     *
     * @param  mixed $option
     * @param  mixed $value
     * @access public
     * @return void
     */
    public function setOption($option, $value);

    /**
     * Gets an option value from adapter or null if the option does not exists.
     *
     * @param  mixed $option
     * @access public
     * @return mixed|null
     */
    public function getOption($option);

    /**
     * Gets the raw response
     *
     * @access public
     * @return string
     */
    public function getResponseBody();

    /**
     * Gets headers from response
     *
     * @access public
     * @return string
     */
    public function getResponseHeaders();

    /**
     * Executes the request to endpoint.
     *
     * @param  Request $request
     * @access public
     * @throw  AktiveMerchant\Http\Adapter\Exception
     * @return boolean
     */
    public function sendRequest(RequestInterface $request);
}
