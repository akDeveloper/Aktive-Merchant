<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways\Paypal;

use AktiveMerchant\Billing\Gateway;

/**
 * Description of PaypalCommon
 *
 * @author Andreas Kollaros <andreas@larium.net>
 * @license http://www.opensource.org/licenses/mit-license.php
 */
abstract class PaypalCommon extends Gateway
{
    const TEST_URL = 'https://api-3t.sandbox.paypal.com/nvp';
    const LIVE_URL = 'https://api-3t.paypal.com/nvp';

    const FAILURE = 'Failure';
    const PENDING = 'Pending';

    protected $SUCCESS_CODES = array('Success', 'SuccessWithWarning');

    const FRAUD_REVIEW_CODE = "11610";

    /**
     *
     * Add final parameters to post data and
     * build $this->post to the format that your payment gateway understands
     *
     * @param string $action
     */
    abstract protected function postData($action);

    /**
     *
     * @param bool $success
     * @param string  $message
     * @param array   $response
     * @param array   $options
     *
     * @return \AktiveMerchant\Billing\Response
     */
    abstract protected function buildResponse($success, $message, $response, $options = array());

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    private function parse($body)
    {
        parse_str($body, $response_array);

        return $response_array;
    }

    /**
     *
     * @param string $action
     * @return \AktiveMerchant\Billing\Response
     */
    protected function commit($action)
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $response = $this->parse(
            $this->ssl_post($url, $this->postData($action))
        );

        $options = array();
        $options['test'] = $this->isTest();
        $options['authorization'] = $this->authorizationFrom($response);
        $options['fraud_review'] = $this->fraudReviewFrom($response);
        $options['avs_result'] = $this->avsResultFrom($response);
        $options['cvv_result'] = isset($response['CVV2MATCH']) ? $response['CVV2MATCH'] : null;

        return $this->buildResponse(
            $this->successFrom($response),
            $this->messageFrom($response),
            $response,
            $options
        );
    }

    /**
     * Returns success flag from gateway response
     *
     * @param array $response
     * @return bool
     */
    private function successFrom($response)
    {
        return (in_array($response['ACK'], $this->SUCCESS_CODES));
    }

    /**
     * Returns message (error explanation  or success) from gateway response
     *
     * @param array $response
     * @return string
     */
    private function messageFrom($response)
    {
        return ( isset($response['L_LONGMESSAGE0']) ? $response['L_LONGMESSAGE0'] : $response['ACK'] );
    }

    /**
     * Returns fraud review from gateway response
     *
     * @param array $response
     * @return bool
     */
    private function fraudReviewFrom($response)
    {
        if (isset($response['L_ERRORCODE0'])) {
            return ($response['L_ERRORCODE0'] == self::FRAUD_REVIEW_CODE);
        }

        return false;
    }

    /**
     *
     * Returns avs result from gateway response
     *
     * @param array $response
     * @return array
     */
    private function avsResultFrom($response)
    {
        return array('code' => isset($response['AVSCODE']) ? $response['AVSCODE'] : null);
    }

    /**
     *
     * @param array $response
     * @return bool
     */
    private function authorizationFrom($response)
    {
        if (isset($response['TRANSACTIONID'])) {
            return $response['TRANSACTIONID'];
        }
        if (isset($response['AUTHORIZATIONID'])) {
            return $response['AUTHORIZATIONID'];
        }
        if (isset($response['REFUNDTRANSACTIONID'])) {
            return $response['REFUNDTRANSACTIONID'];
        }
        if (isset($response['PAYMENTINFO_0_TRANSACTIONID'])) {
            return $response['PAYMENTINFO_0_TRANSACTIONID'];
        }
        return false;
    }
}
