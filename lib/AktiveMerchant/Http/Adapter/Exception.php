<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Http\Adapter;

/**
 * Exception
 *
 * Exception for errors from adapters.
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
class Exception extends \Exception
{
    protected $response_body;

    /**
     * Gets response_body.
     *
     * @access public
     * @return mixed
     */
    public function getResponseBody()
    {
        return $this->response_body;
    }

    /**
     * Sets response_body.
     *
     * @param mixed $response_body the value to set.
     * @access public
     * @return void
     */
    public function setResponseBody($response_body)
    {
        $this->response_body = $response_body;
    }
}
