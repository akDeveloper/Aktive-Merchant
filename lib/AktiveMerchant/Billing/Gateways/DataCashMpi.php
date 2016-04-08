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
 * @author Andreas Kollaros <andreas@larium.net>
 * @license MIT License http://www.opensource.org/licenses/mit-license.php
 */
class DataCashMpi extends DataCash
{
    const AUTHORIZATION = 'threedsecure_validate_authentication';

    const SUCCESS_LOOKUP = '150';
    const NOT_ENROLLED = '162';

    const MPI = 'mpi';

    /**
     * {@inheritdoc}
     */
    public static $default_currency = 'EUR';

    public function lookup($money, CreditCard $creditcard, $options)
    {
        $options = new Options($options);

        $this->buildXml($options, function ($xml) use ($money, $creditcard, $options) {
            $this->addInvoice($money, $options, $xml);
            $this->addMpiTransaction($creditcard, static::MPI, $xml);
        });

        return $this->commit();
    }

    public function authenticate(array $options)
    {
        Options::required('reference, pares', $options);

        $options = new Options($options);

        $reference = $options['reference'];
        $pares  = $options['pares'];

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
            $response['acs_url'] = $tds->acs_url->__toString();
            $response['pareq_message'] = $tds->pareq_message->__toString();
            $response['pares_status'] = $tds->status->__toString();
            $response['aav'] = $tds->aav->__toString();
            $response['cardholder_registered'] = $tds->cardholder_registered->__toString();
            $response['cavvAlgorithm'] = $tds->cavvAlgorithm->__toString();
            $response['eci'] = $tds->eci->__toString();
            $response['xid'] = $tds->xid->__toString();
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
        });
    }

    protected function addMpiTransaction($creditcard, $action, $xml)
    {
        $xml->MpiTxn(function ($xml) use ($creditcard, $action) {
            $xml->Card(function ($xml) use ($creditcard) {
                $xml->pan($creditcard->number);
                $year  = $this->cc_format($creditcard->year, 'two_digits');
                $month = $this->cc_format($creditcard->month, 'two_digits');
                $xml->expirydate("{$month}/{$year}");
                $xml->Cv2Avs(function ($xml) use ($creditcard) {
                    $xml->cv2($creditcard->verification_value);
                });
            });
            $xml->method($action);
        });
    }

    /**
     * Adds invoice info if exists.
     *
     * @param array $options
     */
    protected function addInvoice($money, $options, $xml)
    {
        $xml->TxnDetails(function ($xml) use ($money, $options) {
            $xml->merchantreference($options['order_id']);
            $xml->amount($this->amount($money), array('currency' => self::$default_currency));
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
        return in_array(
            $response['status'],
            array(
                static::SUCCESS,
                static::SUCCESS_LOOKUP,
                static::NOT_ENROLLED
            )
        );
    }
}
