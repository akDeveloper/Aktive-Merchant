<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Common\Country;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Common\SimpleXmlBuilder;
use AktiveMerchant\Billing\Interfaces as Interfaces;

/**
 * Integration of Iridium gateway.
 *
 * @author Dimitris Giannakakis <Dim.Giannakakis@yahoo.com>
 * @author Andreas Kollaros <andreas@larium.net>
 * @license MIT License http://www.opensource.org/licenses/mit-license.php
 */
class Iridium extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit
{
    const TEST_URL = 'https://gw1.iridiumcorp.net/';
    const LIVE_URL = 'https://gw1.iridiumcorp.net/';

    const PURCHASE = 'SALE';
    const AUTHORIZE = 'PREAUTH';
    const CAPTURE = 'COLLECTION';
    const CREDIT = 'REFUND';
    const VOID = 'VOID';

    public static $money_format = 'cents';

    /**
     * {@inheritdoc}
     */
    public static $supported_countries = array(
        'GB',
        'ES'
    );

    /**
     * {@inheritdoc}
     */
    public static $supported_cardtypes = array(
        'visa',
        'master',
        'american_express',
        'discover',
        'solo',
        'maestro',
        'jcb',
        'diners_club'
    );

    protected $options;

    private $soapCall = array(
        self::PURCHASE => 'CardDetailsTransaction',
        self::AUTHORIZE => 'CardDetailsTransaction',
        self::CAPTURE => 'CrossReferenceTransaction',
        self::CREDIT => 'CrossReferenceTransaction',
        self::VOID => 'CrossReferenceTransaction',
    );

    /**
     * {@inheritdoc}
     */
    public static $homepage_url = 'http://www.iridiumcorp.co.uk/';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'Iridium';

    /**
     * {@inheritdoc}
     */
    public static $default_currency = 'EUR';


    protected $SOAP_ATTRIBUTES = array(
        'xmlns:soap' => 'http://schemas.xmlsoap.org/soap/envelope/',
        'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
        'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema'
    );

    private $xml;

    public function __construct($options = array())
    {
        $this->required_options('merchant_id, password', $options);

        parent::__construct($options);
    }

    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $options = new Options($options);
        $this->createXmlBuilder(self::AUTHORIZE);
        $this->addTransactionDetails($money, self::AUTHORIZE, $options['order_id']);
        $this->addCreditcard($creditcard);
        $this->addCustomerDetails($options);

        return $this->commit(self::AUTHORIZE, $options);
    }

    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $options = new Options($options);
        $this->createXmlBuilder(self::PURCHASE);
        $this->addTransactionDetails($money, self::PURCHASE, $options['order_id']);
        $this->addCreditcard($creditcard);
        $this->addCustomerDetails($options);

        return $this->commit(self::PURCHASE, $options);
    }

    public function capture($money, $authorization, $options = array())
    {
        $options = new Options($options);
        list($orderId, $crossReference, $authCode) = $this->splitAuthorization($authorization);

        $this->createXmlBuilder(self::CAPTURE);
        $this->addTransactionDetails($money, self::CAPTURE, $options['order_id'] ?: $orderId, $crossReference);

        return $this->commit(self::CAPTURE, $options);
    }

    public function credit($money, $authorization, $options = array())
    {
        $options = new Options($options);
        list($orderId, $crossReference, $authCode) = $this->splitAuthorization($authorization);

        $this->createXmlBuilder(self::CREDIT);
        $this->addTransactionDetails($money, self::CREDIT, $options['order_id'] ?: $orderId, $crossReference);

        return $this->commit(self::CREDIT, $options);
    }

    public function void($authorization, $options = array())
    {
        $options = new Options($options);
        list($orderId, $crossReference, $authCode) = $this->splitAuthorization($authorization);

        $this->createXmlBuilder(self::VOID);
        $this->addTransactionDetails(0, self::VOID, $options['order_id'] ?: $orderId, $crossReference);

        return $this->commit(self::VOID, $options);
    }

    private function commit($action, Options $options)
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $call = $this->soapCall[$action];

        $headers = array(
            'headers' => array(
                'Content-Type: text/xml; charset=utf-8;',
                'SOAPAction: https://www.thepaymentgateway.net/'.$call
            ),
            'request_timeout' => 10
        );

        $data = $this->ssl_post($url, $this->xml->__toString(), $headers);

        $response = new Options($this->parse($data));

        return new Response(
            $response['StatusCode'] == "0",
            $response['Message'],
            $response->getArrayCopy(),
            array(
                'test' => $this->isTest(),
                'authorization' => $this->authorizationFrom($response, $options),
            )
        );
    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     * @param array reference $post
     */
    private function addCreditcard(CreditCard $creditcard)
    {
        $this->xml->CardDetails(null, 'PaymentMessage');
        $this->xml->CardName($creditcard->name(), 'CardDetails');
        $this->xml->CV2($creditcard->verification_value, 'CardDetails');
        $this->xml->CardNumber($creditcard->number, 'CardDetails');
        $this->xml->ExpiryDate(null, 'CardDetails', array(
            'Month' => $creditcard->month,
            'Year' => $this->cc_format($creditcard->year, 'two_digits'),
        ));
    }

    private function addCustomerDetails($options)
    {
        $billingAddress = $options['billingAddress'] ?: $options['address'];

        $country = null;
        if ($billingAddress['country']) {
            $country = Country::find($billingAddress['country'])->getCode('numeric');
        }

        $this->xml->CustomerDetails(null, 'PaymentMessage');
        $this->xml->BillingAddress(null, 'CustomerDetails');
        $this->xml->Address1($billingAddress['address1'], 'BillingAddress');
        $this->xml->City($billingAddress['city'], 'BillingAddress');
        $this->xml->State($billingAddress['state'], 'BillingAddress');
        $this->xml->PostCode($billingAddress['zip'], 'BillingAddress');
        $this->xml->CountryCode($country, 'BillingAddress');
        $this->xml->EmailAddress($options['email'], 'CustomerDetails');
        $this->xml->CustomerIPAddress($options['ip'], 'CustomerDetails');
    }

    private function splitAuthorization($authorization)
    {
        return explode(';', $authorization);
    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    private function parse($body)
    {
        $xml = simplexml_load_string($body);

        $data = $xml->xpath('//soap:Body');

        $response = array();

        foreach ($data as $node) {
            $this->parseElement($response, $node);
        }

        return $response;
    }

    private function parseElement(&$response, $node)
    {
        foreach ($node->attributes() as $k => $v) {
            $response[$node->getName() . '_' . $k] = trim($v->__toString());
        }

        if ($node->count() > 0) {
            if ($node->getName()) {
                $response[$node->getName()] = true;
                foreach ($node as $n) {
                    $this->parseElement($response, $n);
                }
            }
        } else {
            $response[$node->getName()] = trim($node->__toString());
        }
    }

    private function authorizationFrom(Options $response, Options $options)
    {
        if ($response['StatusCode'] == "0") {
            $authCode = $response['AuthCode'] ?: null;

            return implode(';', array(
                $options['order_id'],
                $response['TransactionOutputData_CrossReference'],
                $authCode,
            ));
        }
    }

    private function createXmlBuilder($action)
    {
        $call = $this->soapCall[$action];

        $this->xml = new SimpleXmlBuilder('1.0', 'UTF-8');

        $this->xml->{'soap:Envelope'}(null, null, $this->SOAP_ATTRIBUTES);
        $this->xml->{'soap:Body'}(null, 'soap:Envelope');

        $this->xml->$call(null, 'soap:Body', array(), 'https://www.thepaymentgateway.net/');
        $this->xml->PaymentMessage(null, $call);

        $this->xml->MerchantAuthentication(
            null,
            'PaymentMessage',
            array(
                'MerchantID' => $this->options['merchant_id'],
                'Password' => $this->options['password']
            )
        );
    }

    private function addTransactionDetails($money, $action, $orderId, $crossReference = null)
    {
        $this->xml->TransactionDetails(
            null,
            'PaymentMessage',
            array(
                'Amount' => $this->amount($money),
                'CurrencyCode' => $this->currency_lookup(self::$default_currency),
            )
        );

        $messageDetails = array('TransactionType' => $action);
        if ($crossReference) {
            $messageDetails['CrossReference'] = $crossReference;
        }
        $this->xml->MessageDetails(null, 'TransactionDetails', $messageDetails);

        $this->xml->OrderID($orderId, 'TransactionDetails');
        if (null == $crossReference) {
            $this->xml->TransactionControl(null, 'TransactionDetails');
            $this->xml->ThreeDSecureOverridePolicy('FALSE', 'TransactionControl');
            $this->xml->EchoAVSCheckResult('TRUE', 'TransactionControl');
            $this->xml->EchoCV2CheckResult('TRUE', 'TransactionControl');
        }
    }
}
