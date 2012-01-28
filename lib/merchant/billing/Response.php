<?php

/**
 * Response to a merchant request.
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Merchant_Billing_Response
{

    private $success;
    private $message;
    protected $params;
    private $test;
    private $authorization;
    private $avs_result;
    private $cvv_result;
    private $fraud_review;

    /**
     *
     * @param boolean $success Whether the request was successfull or not
     * @param string $message Human-readable message provided
     * @param array $params Parameters that should be copied into the properties as-is
     * @param array $options Options that should be interepreted by the response.  Valid
     *                       options are:
     *                       <ul>
     *                       	<li>test - If true, this is a test transaction.
     *                          <li>authorization - Set the authorization property
     *                          <li>fraud_review - Set the fraud_review property
     *                          <li>avs_result - If set, passed to the {@link Merchant_Billing_AvsResult} constructor, 
     *                              and the resulting object is stored in the avs_result property 
     *                          <li>cvv_result - If set, passed to the {@link Merchant_Billing_CvvResult} constructor,
     *                              and the resulting object is stored in the cvv_result property
     *                       </ul>
     */
    public function __construct($success, $message, $params = array(), $options = array())
    {
        $this->success = $success;
        $this->message = $message;
        $this->params = $params;

        $this->test = isset($options['test']) ? $options['test'] : false;
        $this->authorization = isset($options['authorization']) ? $options['authorization'] : null;
        $this->fraud_review = isset($options['fraud_review']) ? $options['fraud_review'] : null;
        $this->avs_result = isset($options['avs_result']) ? new Merchant_Billing_AvsResult($options['avs_result']) : null;
        $this->cvv_result = isset($options['cvv_result']) ? new Merchant_Billing_CvvResult($options['cvv_result']) : null;
    }

    public function __get($name)
    {
        return isset($this->params[$name]) ? $this->params[$name] : null;
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

    /** @return boolean Whether the request was flagged for fraud review */
    public function fraud_review()
    {
        return $this->fraud_review;
    }

    /** @return string|null Authorization identifier, or null if unavailable.
     *
     *  This string should contain any information required to complete, refund, or
     *  void a transaction.  If multiple identifiers are required, they should all be
     *  encoded into one string here, and decoded as necessary.
     */
    public function authorization()
    {
        return $this->authorization;
    }

    /** @return string Human-readable message provided with the response */
    public function message()
    {
        return $this->message;
    }

    /** @return Merchant_Billing_AvsResult|null Address verification result, or null if unavailable */
    public function avs_result()
    {
        return $this->avs_result;
    }

    /** @return Merchant_Billing_CvvResult|null Card verification value result, or null if unavailable */
    public function cvv_result()
    {
        return $this->cvv_result;
    }
    
    /** @return array All additional parameters available for this response */
    public function params()
    {
      return $this->params;
    }
}

?>
