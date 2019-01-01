<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Common\SimpleXmlBuilder;

/**
 * DataCash gateway implementation.
 *
 * @author Andreas Kollaros <andreas@larium.net>
 * @license  MIT License http://www.opensource.org/licenses/mit-license.php
 */
class DataCash extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit
{
    const TEST_URL = 'https://testserver.datacash.com/Transaction';
    const LIVE_URL = 'https://mars.transaction.datacash.com/Transaction';

    const PURCHASE = 'auth';
    const AUTHORIZE = 'pre';
    const CAPTURE = 'fulfill';
    const VOID = 'cancel';
    const CREDIT = 'txn_refund';
    const QUERY = 'query';

    const METHOD_ECOMM = 'ecomm';
    const METHOD_MOTO = 'cnp';

    const SUCCESS = '1';

    /**
     * {@inheritdoc}
     */
    public static $money_format = 'dollars';

    /**
     * {@inheritdoc}
     */
    public static $default_currency = 'GBP';

    /**
     * Additional options needed by gateway
     *
     * @var array
     */
    protected $options;

    /**
     * Contains the main body of the request.
     *
     * @var array
     */
    protected $post;

    protected $xml;

    /**
     * creates gateway instance from given options.
     *
     * @param array $options an array contains login parameters of merchant
     *                       and optional currency.
     *
     * @return Gateway The gateway instance.
     */
    public function __construct($options = array())
    {
        $this->required_options('client, password', $options);

        if (isset($options['currency'])) {
            static::$default_currency = $options['currency'];
        }

        $this->options = $options;
    }

    public function amount($money)
    {
        return number_format($money, 2, '.', '');
    }

    /**
     * {@inheritdoc}
     */
    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $options = new Options($options);

        $this->buildXml();
        $this->addInvoice($money, $options);
        $this->addCreditcard($creditcard, static::AUTHORIZE, $options);

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $options = new Options($options);

        $this->buildXml();
        $this->addInvoice($money, $options);
        $this->addCreditcard($creditcard, static::PURCHASE, $options);

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function capture($money, $authorization, $options = array())
    {
        $options = new Options($options);

        $reference = $this->parseAuthorization($authorization);

        $this->buildXml();
        $this->addInvoice($money, $options);
        $this->xml->HistoricTxn(null, 'Transaction')
            ->reference($reference['datacash_reference'], 'HistoricTxn')
            ->authcode($reference['authcode'], 'HistoricTxn')
            ->method(static::CAPTURE, 'HistoricTxn');

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function void($authorization, $options = array())
    {
        $reference = $this->parseAuthorization($authorization);

        $this->buildXml();
        $this->xml->HistoricTxn(null, 'Transaction')
            ->reference($reference['datacash_reference'], 'HistoricTxn')
            ->method(static::VOID, 'HistoricTxn');

        return $this->commit();
    }

    /**
     *
     * @param  number $money
     * @param  string $identification
     * @param  array  $options
     *
     * @return Response
     */
    public function credit($money, $identification, $options = array())
    {
        $reference = $this->parseAuthorization($identification);

        $this->buildXml();
        $this->xml->TxnDetails(null, 'Transaction')
            ->amount($this->amount($money), 'TxnDetails');
        $this->xml->HistoricTxn(null, 'Transaction')
            ->reference($reference['datacash_reference'], 'HistoricTxn')
            ->method(static::CREDIT, 'HistoricTxn');

        return $this->commit();
    }

    public function store(CreditCard $creditcard, $options = array())
    {
        Options::required('order_id', $options);

        $options = new Options($options);

        $this->buildXml();
        $this->xml->TokenizeTxn(null, 'Transaction')
            ->Card(null, 'TokenizeTxn')
            ->pan($creditcard->number, 'Card')
            ->method('tokenize', 'TokenizeTxn')
            ->TxnDetails(null, 'Transaction')
            ->merchantreference($options['order_id'], 'TxnDetails');

        return $this->commit();
    }

    public function query($authorization)
    {
        $this->buildXml();
        $this->xml->HistoricTxn(null, 'Transaction')
            ->reference($authorization, 'HistoricTxn')
            ->method(static::QUERY, 'HistoricTxn');

        return $this->commit();
    }

    /**
     *
     * @param  string $action
     * @param  number $money
     * @param  array  $parameters
     *
     * @return Response
     */
    protected function commit()
    {
        $url = $this->isTest() ? static::TEST_URL : static::LIVE_URL;

        $postData = $this->postData();

        $data = $this->ssl_post($url, $postData);

        $response = $this->parse($data);

        $test_mode = $this->isTest();

        return new Response(
            $this->successFrom($response),
            $this->messageFrom($response),
            $response,
            array(
                'test' => $test_mode,
                'authorization' => $response['authorization_id'],
                'fraud_review' => $this->fraudReviewFrom($response),
                'avs_result' => $this->avsResultFrom($response),
                'cvv_result' => $response['card_code']
            )
        );
    }

    protected function buildXml()
    {
        $this->xml = new SimpleXmlBuilder();
        $this->xml->Request(null, null, array('version' => '2'))
            ->Authentication(null, 'Request')
            ->client($this->options['client'], 'Authentication')
            ->password($this->options['password'], 'Authentication')
            ->Transaction(null, 'Request');
    }

    /**
     * Customer data like e-mail, ip, web browser used for transaction etc
     *
     * @param array $options
     */
    protected function addCustomerData($xml, $options)
    {

    }

    /**
     * Options key can be 'shipping address' and 'billing_address' or 'address'
     *
     * Each of these keys must have an address array like:
     * <code>
     *      $address['name']
     *      $address['company']
     *      $address['address1']
     *      $address['address2']
     *      $address['city']
     *      $address['state']
     *      $address['country']
     *      $address['zip']
     *      $address['phone']
     * </code>
     * common pattern for address is
     * <code>
     * $billing_address = isset($options['billing_address'])
     *      ? $options['billing_address']
     *      : $options['address'];
     * $shipping_address = $options['shipping_address'];
     * </code>
     *
     * @param  array $options
     *
     * @return void
     */
    protected function addAddress($options)
    {

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
            ->amount($this->amount($money), 'TxnDetails', array('currency'=> static::$default_currency));
        $captureMethod = static::METHOD_ECOMM; # For 3D Secure or No 3D Secure transactions.
        if ($options['moto']) {# MOTO transactions.
            $captureMethod = static::METHOD_MOTO;
        }
        $this->xml->capturemethod($captureMethod, 'TxnDetails');
        if ($options['installments']) {
            $this->xml->Instalments(null, 'TxnDetails');
            $this->xml->number($options['installments'], 'Installments');
        }
    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     */
    protected function addCreditcard(CreditCard $creditcard, $action, $options = array())
    {
        $this->xml->CardTxn(null, 'Transaction');

        if ($options['reference']) {
            $this->xml->card_details(
                $options['reference'],
                'CardTxn',
                array('type' => 'from_mpi')
            );
        } else {
            $this->xml->Card(null, 'CardTxn');
            $token = $options['token'] ? array('type' => 'token') : null;
            $this->xml->pan($creditcard->number, 'Card', $token);
            $year = $this->cc_format($creditcard->year, 'two_digits');
            $month = $this->cc_format($creditcard->month, 'two_digits');
            $this->xml->expirydate("{$month}/{$year}", 'Card');
            if (null == $options['token']) {
                $this->xml->Cv2Avs(null, 'Card')
                    ->cv2($creditcard->verification_value, 'Cv2Avs');
            }
        }
        $this->xml->method($action, 'CardTxn');
    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    protected function parse($body)
    {
        $response = array();

        $data = simplexml_load_string($body);

        foreach ($data as $node) {
            $this->parseElement($response, $node);
        }

        $response['authorization_id'] = null;
        $response['avs_result_code'] = null;
        $response['card_code'] = null;

        $this->setAuthorization($response);

        return $response;
    }

    protected function parseElement(&$response, $node)
    {
        if ($node->count() > 0) {
            foreach ($node as $n) {
                $this->parseElement($response, $n);
            }
        } else {
            $response[$node->getName()] = $node->__toString();
        }
    }

    private function setAuthorization(&$response)
    {
        if (isset($response['datacash_reference'])
            && isset($response['authcode'])
        ) {
            $response['authorization_id'] = sprintf(
                '%s;%s',
                $response['datacash_reference'],
                $response['authcode']
            );

            return;
        }

        if (isset($response['datacash_reference'])) {
            $response['authorization_id'] = $response['datacash_reference'];
        }

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
        return $response['status'] == static::SUCCESS;
    }

    /**
     * Returns message (error explanation  or success) from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    protected function messageFrom($response)
    {
        return $response['reason'];
    }

    /**
     * Returns fraud review from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    protected function fraudReviewFrom($response)
    {

    }

    /**
     *
     * Returns avs result from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    protected function avsResultFrom($response)
    {
        return array('code' => $response['avs_result_code']);
    }

    /**
     * Adds final parameters to post data and
     * build $this->post to the format that your payment gateway understands
     *
     * @return string
     */
    protected function postData()
    {
        $xml = $this->xml->__toString();

        return $xml;
    }

    protected function parseAuthorization($authorization)
    {
        list($datacash_reference, $authcode) = explode(';', $authorization);

        return array(
            'datacash_reference' => $datacash_reference,
            'authcode' => $authcode
        );
    }
}
