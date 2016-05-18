<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Common\Options;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Common\SimpleXmlBuilder;

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
    public static $default_currency = 'GBP';

    public function lookup($money, CreditCard $creditcard, $options)
    {
        $options = new Options($options);

        $this->buildXml();
        $this->addInvoice($money, $options);
        $this->addMpiTransaction($creditcard, static::MPI);

        return $this->commit();
    }

    public function authenticate(array $options)
    {
        Options::required('reference, pares', $options);

        $options = new Options($options);

        $reference = $options['reference'];
        $pares  = $options['pares'];

        $this->buildXml();
        $this->xml->HistoricTxn(null, 'Transaction')
            ->reference($reference, 'HistoricTxn')
            ->pares_message($pares, 'HistoricTxn')
            ->method(static::AUTHORIZATION, 'HistoricTxn');

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

    protected function addThreeDSecure($options)
    {
        Options::required('accept_headers, user_agent, merchant_url, description', $options);

        $this->xml->ThreeDSecure(null, 'TxnDetails')
            ->Browser(null, 'ThreeDSecure')
            ->device_category(0, 'Browser')
            ->accept_headers($options['accept_headers'], 'Browser')
            ->user_agent($options['user_agent'], 'Browser')
            ->purchase_datetime(date('Ymd H:i:s'), 'ThreeDSecure')
            ->merchant_url($options['merchant_url'], 'ThreeDSecure')
            ->purchase_desc($options['description'], 'ThreeDSecure');
    }

    protected function addMpiTransaction($creditcard, $action)
    {
        $this->xml->MpiTxn(null, 'Transaction')
            ->Card(null, 'MpiTxn')
            ->pan($creditcard->number, 'Card');
        $year = $this->cc_format($creditcard->year, 'two_digits');
        $month = $this->cc_format($creditcard->month, 'two_digits');
        $this->xml->expirydate("{$month}/{$year}", 'Card')
            ->Cv2Avs(null, 'Card')
            ->cv2($creditcard->verification_value, 'Cv2Avs');
        $this->xml->method($action, 'MpiTxn');
    }

    /**
     * Adds invoice info if exists.
     *
     * @param array $options
     */
    protected function addInvoice($money, $options)
    {
        $this->xml->TxnDetails(null, 'Transaction')
            ->merchantreference($options['order_id'], 'TxnDetails')
            ->amount(
                $this->amount($money),
                'TxnDetails',
                array('currency' => self::$default_currency)
            );
        $this->addThreeDSecure($options);
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
