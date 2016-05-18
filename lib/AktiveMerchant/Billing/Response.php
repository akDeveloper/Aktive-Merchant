<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing;

use AktiveMerchant\Common\Options;

/**
 * Response to a merchant request.
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Response
{
    protected $success;
    protected $message;

    /**
     * Available parameters returned by gateway response.
     *
     * @var Options
     */
    protected $params;

    protected $test;
    protected $authorization;
    protected $avs_result;
    protected $cvv_result;
    protected $fraud_review;

    /**
     *
     * @param boolean $success Whether the request was successfull or not
     * @param string $message Human-readable message provided
     * @param array $params Parameters that should be copied into the properties as-is
     * @param array $options Options that should be interepreted by the response.  Valid
     *                       options are:
     *                       <ul>
     *                          <li>test - If true, this is a test transaction.
     *                          <li>authorization - Set the authorization property
     *                          <li>fraud_review - Set the fraud_review property
     *                          <li>avs_result - If set, passed to the {@link AktiveMerchant\Billing\AvsResult} constructor,
     *                              and the resulting object is stored in the avs_result property
     *                          <li>cvv_result - If set, passed to the {@link AktiveMerchant\Billing\CvvResult} constructor,
     *                              and the resulting object is stored in the cvv_result property
     *                       </ul>
     */
    public function __construct($success, $message, $params = array(), $options = array())
    {
        $this->success = $success;
        $this->message = $message;
        $this->params  = new Options($params);

        $this->test          = isset($options['test'])          ? $options['test'] : false;
        $this->authorization = isset($options['authorization']) ? $options['authorization'] : null;
        $this->fraud_review  = isset($options['fraud_review'])  ? $options['fraud_review'] : null;
        $this->avs_result    = isset($options['avs_result'])    ? new \AktiveMerchant\Billing\AvsResult($options['avs_result']) : null;
        $this->cvv_result    = isset($options['cvv_result'])    ? new \AktiveMerchant\Billing\CvvResult($options['cvv_result']) : null;
    }

    public function __get($name)
    {
        return $this->params[$name];
    }

    /**
     * Check if the transaction was successful or not
     *
     * @return boolean Whether this transaction was successful or not
     */
    public function success()
    {
        return $this->success;
    }

    /**
     *
     * @return boolean Whether this was a test transaction
     */
    public function test()
    {
        return $this->test;
    }

    /**
     *
     * @return boolean Whether the request was flagged for fraud review
     */
    public function fraud_review()
    {
        return $this->fraud_review;
    }

    /**
     * This string should contain any information required to complete, refund, or
     * void a transaction.  If multiple identifiers are required, they should all be
     * encoded into one string here, and decoded as necessary.
     *
     * @return string|null Authorization identifier, or null if unavailable.
     */
    public function authorization()
    {
        return $this->authorization;
    }

    /**
     *
     * @return string Human-readable message provided with the response
     */
    public function message()
    {
        return $this->message;
    }

    /**
     *
     * @return \AktiveMerchant\Billing\AvsResult|null Address verification result, or null if unavailable
     */
    public function avs_result()
    {
        return $this->avs_result;
    }

    /**
     *
     * @return \AktiveMerchant\Billing\CvvResult|null Card verification value result, or null if unavailable
     */
    public function cvv_result()
    {
        return $this->cvv_result;
    }

    /**
     *
     * @return Options All additional parameters available for this response
     */
    public function params()
    {
        return $this->params;
    }
}
