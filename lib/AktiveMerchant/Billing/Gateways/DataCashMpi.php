<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Common\XmlBuilder;

/**
 * Support 3D Secure implementation for DataCash gateway.
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license MIT License http://www.opensource.org/licenses/mit-license.php
 */
class DataCashMpi extends DataCash
{
    const AUTHORIZATION = 'threedsecure_authorization_request';

    const SUCCESS_LOOKUP = '150';

    /**
     * {@inheritdoc}
     */
    public static $default_currency = 'EUR';

    public function authorizeMpi($money, CreditCard $creditcard, $options)
    {
        $options = new Options($options);

        $this->buildXml($options, function ($xml) use ($money, $creditcard, $options) {
            $this->addInvoice($money, $options, $xml, true);
            $this->addCreditcard($creditcard, static::PURCHASE, $xml);
        });

        return $this->commit();
    }

    public function authenticateMpi($reference, $pares)
    {
        $this->buildXml([], function ($xml) use ($reference, $pares) {
            $xml->HistoricTxn(function ($xml) use ($reference, $pares) {
                $xml->reference($reference);
                $xml->pares_message($pares);
                $xml->method(static::AUTHORIZATION);
            });
        });

        return $this->commit();
    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    protected function parse($body)
    {
        $response = parent::parse($body);

        $data = simplexml_load_string($body);

        if ($data->CardTxn && $tds = $data->CardTxn->ThreeDSecure) {
        }

        return $response;
    }

    protected function addThreeDSecure($xml, $options)
    {
        Options::required('accept_headers, user_agent, merchant_url, description', $options);

        $xml->ThreeDSecure(function ($xml) use ($options) {
            $xml->Browser(function ($xml) use ($options) {
                $xml->device_category(0);
                $xml->accept_headers($options['accept_headers']);
                $xml->user_agent($options['user_agent']);
            });
            $xml->purchase_datetime(date('Ymd H:i:s'));
            $xml->merchant_url($options['merchant_url']);
            $xml->purchase_desc($options['description']);
            $xml->verify('yes');
        });
    }

    /**
     * Adds invoice info if exists.
     *
     * @param array $options
     */
    protected function addInvoice($money, $options, $xml, $mpi = false)
    {
        $xml->TxnDetails(function ($xml) use ($money, $options, $mpi) {
            $xml->merchantreference($options['order_id']);
            $xml->amount($this->amount($money), array('currency' => self::$default_currency));
            $xml->capturemethod('ecomm');
            $this->addThreeDSecure($xml, $options);
        });
    }

    /**
     * Returns success flag from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    protected function successFrom($response)
    {
        return $response['status'] == static::SUCCESS || static::SUCCESS_LOOKUP;
    }
}
